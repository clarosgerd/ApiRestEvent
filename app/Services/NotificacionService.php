<?php

namespace App\Services;

use App\Jobs\SendWhatsappMessageJob;
use App\Mail\CupoRevertidoMail;
use App\Mail\InscripcionPendienteMail;
use App\Mail\PagoAdicionalConfirmadoMail;
use App\Mail\PagoConfirmadoMail;
use App\Mail\RecordatorioKitMail;
use App\Mail\RecordatorioPagoMail;
use App\Models\PagoAdicionalInscripcion;
use App\Models\Registration;
use App\Models\RegistrationNotification;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

/**
 * Punto único de envío de notificaciones (ver brain/PLAN-NOTIFICACIONES.md).
 * Cada método es idempotente vía registration_notifications: si ya se envió
 * ese tipo+canal para la inscripción, no reenvía (protege contra reintentos
 * de webhooks de pago o corridas de más del scheduler).
 */
class NotificacionService
{
    /**
     * Código de 3 letras para mensaje.tipo (§2.4) — pago_confirmado no está
     * acá a propósito: ese correo va con PDF adjunto, no por WhatsApp.
     */
    private const WHATSAPP_TIPO_CODES = [
        'pendiente_creada' => 'PEN',
        'recordatorio_30'  => 'R30',
        'recordatorio_15'  => 'R15',
        'reversion_cupo'   => 'REV',
        'recordatorio_kit' => 'KIT',
    ];

    public function __construct(
        private readonly WhatsappExternoService $whatsappExterno
    ) {
    }

    public function notificarInscripcionPendiente(Registration $registration): void
    {
        $this->enviarEmailSiNoEnviado(
            $registration,
            'pendiente_creada',
            fn () => new InscripcionPendienteMail($registration)
        );

        $this->notificarWhatsappSiNoEnviado(
            $registration,
            'pendiente_creada',
            fn () => "Hola, tu inscripción a {$registration->evento_nombre} (ref: {$registration->referencia}) fue registrada y está pendiente de pago."
        );
    }

    public function notificarPagoConfirmado(Registration $registration): void
    {
        $this->enviarEmailSiNoEnviado(
            $registration,
            'pago_confirmado',
            fn () => new PagoConfirmadoMail($registration)
        );
    }

    /**
     * Correo de confirmación por pago adicional (02/09/2026) — hueco real
     * encontrado en un incidente de UAT: `ConfirmarPagoAdicionalAction`
     * nunca avisaba nada al participante, ni siquiera cuando el pago se
     * aplicaba bien (ver PLAN-COBRO-SIP-ADICIONAL-26082026.md, nunca tuvo
     * este correo desde el principio).
     *
     * Idempotencia por `notificado_at` en la propia fila de
     * `PagoAdicionalInscripcion` — NO usa `registration_notifications`
     * como el resto de esta clase: esa tabla es única por
     * (registration_id, tipo, canal), y una misma inscripción puede tener
     * varios pagos adicionales a lo largo del tiempo (talleres agregados
     * en ediciones distintas) — ese UNIQUE bloquearía el segundo aviso.
     */
    public function notificarPagoAdicionalConfirmado(PagoAdicionalInscripcion $pago): void
    {
        if ($pago->notificado_at !== null) {
            return;
        }

        $registration = $pago->registration;
        if (! $registration) {
            return;
        }

        $registration->loadMissing([
            'participants.souvenirParticipante',
            'participants.talleresSesiones.taller',
            'evento', 'formType',
        ]);

        $destinatarios = $registration->participants->pluck('correo')->filter()->unique();

        if ($destinatarios->isEmpty()) {
            return;
        }

        // Reserva atómica ANTES de mandar nada (03/09/2026 — mismo bug
        // real que enviarEmailSiNoEnviado(), ver ahí; acá con UPDATE
        // condicional en vez de insertOrIgnore porque la fila ya existe
        // de antes — `pagos_adicionales_inscripcion` no tiene un UNIQUE
        // por tipo/canal como registration_notifications). El chequeo
        // `$pago->notificado_at !== null` de arriba sigue sirviendo para
        // salir rápido en el caso común (ya notificado hace rato); esto
        // cubre la ventana real entre ese chequeo en memoria y el UPDATE.
        $reservado = PagoAdicionalInscripcion::where('id', $pago->id)
            ->whereNull('notificado_at')
            ->update(['notificado_at' => now()]);

        if (! $reservado) {
            return;
        }

        foreach ($destinatarios as $correo) {
            try {
                // Instancia nueva por destinatario, mismo motivo que en
                // enviarEmailSiNoEnviado(): Mailable::to() acumula en vez
                // de reemplazar.
                Mail::to($correo)->send(new PagoAdicionalConfirmadoMail($pago));
            } catch (\Throwable $e) {
                Log::error("No se pudo enviar email (pago_adicional_confirmado) a {$correo} para {$pago->referencia}: {$e->getMessage()}");
            }
        }
    }

    /**
     * @param int $tier 1 = primer aviso (30 días), 2 = segundo aviso (15
     *                  días, incluye advertencia de reversión de cupo)
     */
    public function notificarRecordatorioPendiente(Registration $registration, int $tier, int $diasGracia): void
    {
        $tipo = $tier === 1 ? 'recordatorio_30' : 'recordatorio_15';

        $this->enviarEmailSiNoEnviado(
            $registration,
            $tipo,
            fn () => new RecordatorioPagoMail($registration, $tier, $diasGracia)
        );

        $this->notificarWhatsappSiNoEnviado(
            $registration,
            $tipo,
            fn () => $tier === 1
                ? "Recordatorio: tu inscripción a {$registration->evento_nombre} (ref: {$registration->referencia}) sigue pendiente de pago."
                : "Urgente: tu inscripción a {$registration->evento_nombre} (ref: {$registration->referencia}) sigue pendiente de pago. Si no se paga, el cupo se reasignará en {$diasGracia} día(s)."
        );
    }

    public function notificarReversionCupo(Registration $registration): void
    {
        $this->enviarEmailSiNoEnviado(
            $registration,
            'reversion_cupo',
            fn () => new CupoRevertidoMail($registration)
        );

        $this->notificarWhatsappSiNoEnviado(
            $registration,
            'reversion_cupo',
            fn () => "Tu inscripción a {$registration->evento_nombre} (ref: {$registration->referencia}) fue cancelada por falta de pago."
        );
    }

    public function notificarRecordatorioKit(Registration $registration): void
    {
        $this->enviarEmailSiNoEnviado(
            $registration,
            'recordatorio_kit',
            fn () => new RecordatorioKitMail($registration)
        );

        $this->notificarWhatsappSiNoEnviado(
            $registration,
            'recordatorio_kit',
            fn () => "Recordatorio: recoge tu KIT (numeración, polera, souvenirs) para {$registration->evento_nombre} (ref: {$registration->referencia})."
        );
    }

    private function enviarEmailSiNoEnviado(Registration $registration, string $tipo, \Closure $mailableFactory): void
    {
        // formType/participants.talleresSesiones.taller (19/08/2026) — el
        // partial de participantes del email necesita `formType.tipo` para
        // ocultar "Camiseta" en congresos, y la relación real de talleres
        // (antes el partial mostraba `souvenirParticipante` bajo la
        // etiqueta "Taller:" por error — ver participantes.blade.php).
        $registration->loadMissing([
            'participants.souvenirParticipante',
            'participants.talleresSesiones.taller',
            'totals', 'evento.organizador', 'formType',
        ]);

        $destinatarios = $registration->participants->pluck('correo')->filter()->unique();

        if ($destinatarios->isEmpty()) {
            return;
        }

        // Reserva atómica ANTES de mandar nada (03/09/2026 — bug real en
        // UAT, registration_id 90314: "Duplicate entry ... for key
        // registration_notifications_registration_id_tipo_canal_unique").
        // Antes acá se chequeaba yaEnviado() y recién se registraba el
        // envío DESPUÉS de mandar el correo — ventana de carrera real:
        // dos requests casi simultáneos (típicamente el webhook de pago +
        // el polling del frontend detectando el mismo pago un instante
        // después) pasaban el chequeo LOS DOS, mandaban el correo LOS
        // DOS, y recién el segundo INSERT crasheaba — para ese punto el
        // correo duplicado ya se había mandado, el crash solo avisaba
        // tarde. reservarNotificacion() usa el UNIQUE de la tabla como
        // mutex real: si ya estaba reservado (el otro request ganó la
        // carrera), no se manda nada acá.
        if (! $this->reservarNotificacion($registration, $tipo, 'email')) {
            return;
        }

        foreach ($destinatarios as $correo) {
            try {
                // Instancia nueva por destinatario: reusar la misma Mailable en
                // varios Mail::to()->send() acumula destinatarios (Mailable::to()
                // hace append, no replace), exponiendo cada correo a los demás.
                Mail::to($correo)->send($mailableFactory());
            } catch (\Throwable $e) {
                // No dejamos que una falla de SMTP tumbe el flujo de registro/pago
                // que disparó esta notificación — se loguea y sigue.
                Log::error("No se pudo enviar email ({$tipo}) a {$correo} para {$registration->referencia}: {$e->getMessage()}");
            }
        }

        $this->guardarCopiaAuditoria($registration, $tipo, $mailableFactory());
    }

    /**
     * $textoFactory se evalúa perezosamente (solo si el organizador tiene un
     * canal de WhatsApp activo y todavía no se envió este tipo por ese
     * canal) para no armar el texto en los casos donde no hace falta. Los
     * canales openwa/externo son mutuamente excluyentes (whatsapp_canal es
     * un solo campo, no dos booleans) — nunca se disparan los dos.
     */
    private function notificarWhatsappSiNoEnviado(Registration $registration, string $tipo, \Closure $textoFactory): void
    {
        $registration->loadMissing(['participants', 'evento.organizador']);

        $codigoTipo = self::WHATSAPP_TIPO_CODES[$tipo] ?? null;
        if ($codigoTipo === null) {
            return;
        }

        match ($registration->evento?->organizador?->whatsapp_canal) {
            'externo' => $this->encolarWhatsappExterno($registration, $tipo, $codigoTipo, $textoFactory),
            'openwa'  => $this->dispatchWhatsappOpenwa($registration, $tipo, $textoFactory),
            default   => null,
        };
    }

    private function encolarWhatsappExterno(Registration $registration, string $tipo, string $codigoTipo, \Closure $textoFactory): void
    {
        $destinatarios = $registration->participants->filter(fn ($p) => filled($p->telefono));
        if ($destinatarios->isEmpty()) {
            return;
        }

        // Reserva atómica ANTES de encolar nada — mismo motivo/bug real
        // que enviarEmailSiNoEnviado() (03/09/2026, ver
        // reservarNotificacion()); mismo UNIQUE de tabla, misma ventana
        // de carrera posible acá aunque todavía no se haya visto en los
        // logs.
        if (! $this->reservarNotificacion($registration, $tipo, 'whatsapp_externo')) {
            return;
        }

        $texto = $textoFactory();
        foreach ($destinatarios as $participante) {
            $this->whatsappExterno->encolar(
                $participante->telefono,
                $texto,
                $codigoTipo,
                $participante->correo,
            );
        }
    }

    /**
     * OpenWA espera un chatId tipo "59178441410@c.us" (código de país +
     * número + sufijo fijo) — se arma a partir del teléfono del
     * participante, limpiando todo lo que no sea dígito.
     */
    private function dispatchWhatsappOpenwa(Registration $registration, string $tipo, \Closure $textoFactory): void
    {
        $destinatarios = $registration->participants
            ->map(fn ($p) => preg_replace('/\D+/', '', $p->telefono ?? ''))
            ->filter(fn ($digitos) => $digitos !== '');

        if ($destinatarios->isEmpty()) {
            return;
        }

        // Reserva atómica ANTES de despachar nada — mismo motivo que
        // encolarWhatsappExterno()/enviarEmailSiNoEnviado() arriba.
        if (! $this->reservarNotificacion($registration, $tipo, 'whatsapp_openwa')) {
            return;
        }

        $texto = $textoFactory();
        foreach ($destinatarios as $digitos) {
            SendWhatsappMessageJob::dispatch("{$digitos}@c.us", $texto);
        }
    }

    /**
     * Reserva atómica de un (registration_id, tipo, canal) — reemplaza al
     * viejo par yaEnviado()+registrarEnvio() (03/09/2026, ver bug real en
     * UAT documentado en enviarEmailSiNoEnviado()). `insertOrIgnore()`
     * usa el UNIQUE de la tabla como mutex a nivel de BD: dos requests
     * casi simultáneos para el mismo (registration_id, tipo, canal) — uno
     * inserta, el otro lo pisa en silencio (0 filas afectadas) en vez de
     * que ambos pasen un SELECT previo y after uno de los dos INSERT
     * termine chocando después de ya haber mandado el correo/WhatsApp.
     *
     * @return bool true si esta llamada ganó la reserva (nadie la tenía
     *   todavía) — el caller debe mandar la notificación. false si ya
     *   estaba reservada — no hay que mandar nada.
     */
    private function reservarNotificacion(Registration $registration, string $tipo, string $canal): bool
    {
        return (bool) RegistrationNotification::query()->insertOrIgnore([[
            'registration_id' => $registration->id,
            'tipo'            => $tipo,
            'canal'           => $canal,
            'enviado_at'      => now(),
        ]]);
    }

    private function guardarCopiaAuditoria(Registration $registration, string $tipo, Mailable $mailable): void
    {
        try {
            $html     = $mailable->render();
            $filename = "{$registration->referencia}_{$tipo}_" . now()->format('Ymd_His') . '.html';
            Storage::disk('local')->put("emails/{$filename}", $html);
        } catch (\Throwable $e) {
            Log::warning("No se pudo guardar copia de auditoría del email ({$tipo}) para {$registration->referencia}: {$e->getMessage()}");
        }
    }
}
