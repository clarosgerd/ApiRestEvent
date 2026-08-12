<?php

namespace App\Actions;

use App\Mail\CertificadoCongresoMail;
use App\Models\AsistenciaSesion;
use App\Models\CertificadoCongresoEnviado;
use App\Models\Evento;
use App\Models\Participante;
use App\Models\SesionCongreso;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Certificados automáticos de congreso — ver
 * PRD-Agenda-sessiones-onlycongresos.md (Fase 2) y elascenso/event/brain/
 * (sesión 11/08/2026). Un solo certificado por participante por evento
 * (no uno por sesión), disparado cuando el evento completo cierra — ver
 * el comando `certificados:enviar-congreso`, que llama a `handle()` por
 * cada evento congreso cerrado.
 */
class EnviarCertificadosCongresoAction
{
    public function handle(Evento $evento): int
    {
        $participanteIdsConAsistencia = AsistenciaSesion::query()
            ->whereHas('sesion', fn ($q) => $q->where('evento_id', $evento->id))
            ->distinct()
            ->pluck('participante_id');

        $yaEnviados = CertificadoCongresoEnviado::where('evento_id', $evento->id)->pluck('participante_id');

        $pendientes = $participanteIdsConAsistencia->diff($yaEnviados);

        if ($pendientes->isEmpty()) {
            return 0;
        }

        $enviados = 0;

        Participante::whereIn('id', $pendientes)
            ->with('registration')
            ->chunkById(100, function ($participantes) use ($evento, &$enviados) {
                foreach ($participantes as $participante) {
                    if ($this->enviarCertificado($evento, $participante)) {
                        $enviados++;
                    }
                }
            });

        return $enviados;
    }

    /**
     * @return bool true si el certificado se envió y se registró la
     *              idempotencia — false si se saltó (sin correo) o el
     *              envío falló (no se registra, se reintenta la
     *              próxima corrida — ver la migración de la tabla para
     *              la justificación de esta decisión).
     */
    private function enviarCertificado(Evento $evento, Participante $participante): bool
    {
        if (empty($participante->correo)) {
            return false;
        }

        $sesiones = SesionCongreso::where('evento_id', $evento->id)
            ->whereHas('asistencias', fn ($q) => $q->where('participante_id', $participante->id))
            ->orderBy('fecha')->orderBy('hora_inicio')
            ->get();

        if ($sesiones->isEmpty()) {
            return false;
        }

        try {
            Mail::to($participante->correo)->send(new CertificadoCongresoMail($evento, $participante, $sesiones));
        } catch (\Throwable $e) {
            Log::error("No se pudo enviar certificado de congreso a {$participante->correo} (evento {$evento->id}, participante {$participante->id}): {$e->getMessage()}");

            return false;
        }

        CertificadoCongresoEnviado::create([
            'evento_id' => $evento->id,
            'participante_id' => $participante->id,
            'enviado_at' => now(),
        ]);

        return true;
    }
}
