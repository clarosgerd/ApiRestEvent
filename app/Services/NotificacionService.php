<?php

namespace App\Services;

use App\Jobs\SendWhatsappMessageJob;
use App\Mail\CupoRevertidoMail;
use App\Mail\InscripcionPendienteMail;
use App\Mail\PagoConfirmadoMail;
use App\Mail\RecordatorioKitMail;
use App\Mail\RecordatorioPagoMail;
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
        if ($this->yaEnviado($registration, $tipo, 'email')) {
            return;
        }

        $registration->loadMissing(['participants.souvenirParticipante', 'totals', 'evento.organizador']);

        $destinatarios = $registration->participants->pluck('correo')->filter()->unique();

        if ($destinatarios->isEmpty()) {
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

        $this->registrarEnvio($registration, $tipo, 'email');
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
        if ($this->yaEnviado($registration, $tipo, 'whatsapp_externo')) {
            return;
        }

        $texto    = $textoFactory();
        $encolado = false;

        foreach ($registration->participants as $participante) {
            if (empty($participante->telefono)) {
                continue;
            }

            $this->whatsappExterno->encolar(
                $participante->telefono,
                $texto,
                $codigoTipo,
                $participante->correo,
            );
            $encolado = true;
        }

        if ($encolado) {
            $this->registrarEnvio($registration, $tipo, 'whatsapp_externo');
        }
    }

    /**
     * OpenWA espera un chatId tipo "59178441410@c.us" (código de país +
     * número + sufijo fijo) — se arma a partir del teléfono del
     * participante, limpiando todo lo que no sea dígito.
     */
    private function dispatchWhatsappOpenwa(Registration $registration, string $tipo, \Closure $textoFactory): void
    {
        if ($this->yaEnviado($registration, $tipo, 'whatsapp_openwa')) {
            return;
        }

        $texto      = $textoFactory();
        $despachado = false;

        foreach ($registration->participants as $participante) {
            $digitos = preg_replace('/\D+/', '', $participante->telefono ?? '');
            if ($digitos === '') {
                continue;
            }

            SendWhatsappMessageJob::dispatch("{$digitos}@c.us", $texto);
            $despachado = true;
        }

        if ($despachado) {
            $this->registrarEnvio($registration, $tipo, 'whatsapp_openwa');
        }
    }

    private function yaEnviado(Registration $registration, string $tipo, string $canal): bool
    {
        return RegistrationNotification::where('registration_id', $registration->id)
            ->where('tipo', $tipo)
            ->where('canal', $canal)
            ->exists();
    }

    private function registrarEnvio(Registration $registration, string $tipo, string $canal): void
    {
        RegistrationNotification::create([
            'registration_id' => $registration->id,
            'tipo'            => $tipo,
            'canal'           => $canal,
            'enviado_at'      => now(),
        ]);
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
