<?php

namespace App\Actions;

use App\Mail\SeguimientoExpositorMail;
use App\Models\LeadCapturado;
use App\Models\Persona;
use App\Models\SeguimientoBaja;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * SmartStand fase 4 — correo de seguimiento de una empresa expositora al
 * asistente que acaba de capturar. Deja el resultado en el propio lead
 * (`seguimiento_estado`: enviado | fallido | omitido, con su motivo) y NUNCA
 * lanza: una falla de SMTP no debe romper la captura ni el reintento.
 *
 * Cuándo se omite (no se manda y no se reintenta): evento u empresa sin el
 * seguimiento encendido, asistente sin correo válido, asistente dado de baja
 * (por el link del correo o por la baja de marketing de su Persona) o tope de
 * correos por asistente en el evento.
 */
class EnviarSeguimientoLeadAction
{
    public const ESTADO_ENVIADO = 'enviado';
    public const ESTADO_FALLIDO = 'fallido';
    public const ESTADO_OMITIDO = 'omitido';

    public const TOPE_DEFAULT = 10;
    public const MAX_INTENTOS = 3;

    public function ejecutar(LeadCapturado $lead): LeadCapturado
    {
        // Ya resuelto (enviado u omitido): nunca se reenvía. Solo `fallido` y sin estado avanzan.
        if (in_array($lead->seguimiento_estado, [self::ESTADO_ENVIADO, self::ESTADO_OMITIDO], true)) {
            return $lead;
        }

        $empresa = $lead->empresa;
        $participante = $lead->participante;
        $evento = $empresa?->evento;

        if (! $empresa || ! $participante || ! $evento) {
            return $lead;
        }

        $config = $evento->expositores_config ?? [];
        if (! ($config['seguimiento_habilitado'] ?? false) || ! $empresa->seguimiento_activo) {
            // No es un "omitido" del lead: simplemente el seguimiento no aplica.
            return $lead;
        }

        $correo = mb_strtolower(trim((string) $participante->correo));
        if ($correo === '' || ! filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            return $this->marcar($lead, self::ESTADO_OMITIDO, 'sin_correo');
        }

        if ($this->estaDeBaja($correo, (string) $participante->numero_documento)) {
            return $this->marcar($lead, self::ESTADO_OMITIDO, 'baja');
        }

        if ($this->superaTope($lead, $correo, (int) ($config['seguimiento_max_por_asistente'] ?? self::TOPE_DEFAULT))) {
            return $this->marcar($lead, self::ESTADO_OMITIDO, 'tope');
        }

        $lead->seguimiento_intentos = (int) $lead->seguimiento_intentos + 1;

        try {
            Mail::to($correo)->send(new SeguimientoExpositorMail($lead));

            return $this->marcar($lead, self::ESTADO_ENVIADO, null);
        } catch (Throwable $e) {
            Log::warning('SmartStand: falló el correo de seguimiento', [
                'lead_id' => $lead->id,
                'error'   => $e->getMessage(),
            ]);

            return $this->marcar($lead, self::ESTADO_FALLIDO, 'error');
        }
    }

    private function estaDeBaja(string $correo, string $documento): bool
    {
        if (SeguimientoBaja::where('email', $correo)->exists()) {
            return true;
        }

        // Baja de marketing existente (personas.acepta_marketing = false). No hay
        // persona_id en participantes: se cruza por correo y luego por documento.
        return Persona::where('acepta_marketing', false)
            ->where(function ($q) use ($correo, $documento) {
                $q->whereRaw('LOWER(email) = ?', [$correo])
                    ->orWhereRaw('LOWER(correo) = ?', [$correo]);
                if ($documento !== '') {
                    $q->orWhere('numero_documento', $documento);
                }
            })
            ->exists();
    }

    /** Correos de seguimiento ya enviados a este correo en el evento (de cualquier empresa). */
    private function superaTope(LeadCapturado $lead, string $correo, int $tope): bool
    {
        $tope = max(1, $tope);

        $enviados = DB::table('leads_capturados')
            ->join('participantes', 'participantes.id', '=', 'leads_capturados.participante_id')
            ->join('empresas_expositoras', 'empresas_expositoras.id', '=', 'leads_capturados.empresa_expositora_id')
            ->where('empresas_expositoras.evento_id', $lead->empresa->evento_id)
            ->where('leads_capturados.seguimiento_estado', self::ESTADO_ENVIADO)
            ->whereRaw('LOWER(TRIM(participantes.correo)) = ?', [$correo])
            ->count();

        return $enviados >= $tope;
    }

    private function marcar(LeadCapturado $lead, string $estado, ?string $motivo): LeadCapturado
    {
        $lead->seguimiento_estado = $estado;
        $lead->seguimiento_motivo = $motivo;
        $lead->seguimiento_at = now();
        $lead->save();

        return $lead;
    }
}
