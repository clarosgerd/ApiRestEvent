<?php

namespace App\Actions;

use App\Mail\RecordatorioManillaOftalmologiaMail;
use App\Models\Registration;
use Illuminate\Support\Facades\Mail;

/**
 * Lógica compartida entre el comando manual
 * (`notificaciones:recordatorio-manilla-oftalmologia`) y el envío
 * parametrizado por archivo que dispara el cron existente — ver
 * routes/console.php. Separada en su propia Action para no duplicarla
 * entre los dos caminos.
 */
class EnviarRecordatorioManillaOftalmologiaAction
{
    /**
     * @return array{evento_nombre: string, inscripciones: int, destinatarios: \Illuminate\Support\Collection, enviados: int, fallidos: array<string>}
     */
    public function destinatarios(int $eventoId): array
    {
        $registrations = Registration::where('evento_id', $eventoId)
            ->where('pago_status', 'paid')
            ->with('participants', 'evento')
            ->get();

        $eventoNombre = $registrations->first()?->evento?->nombre
            ?? $registrations->first()?->evento_nombre
            ?? '';

        $destinatarios = $registrations
            ->flatMap(fn (Registration $r) => $r->participants->pluck('correo'))
            ->filter()
            ->unique()
            ->values();

        return [
            'evento_nombre' => $eventoNombre,
            'inscripciones' => $registrations->count(),
            'destinatarios' => $destinatarios,
        ];
    }

    public function enviar(string $eventoNombre, \Illuminate\Support\Collection $destinatarios, string $fecha, string $lugar, string $horaInicio, string $horaCierre): array
    {
        $enviados = 0;
        $fallidos = [];

        foreach ($destinatarios as $correo) {
            try {
                Mail::to($correo)->send(new RecordatorioManillaOftalmologiaMail(
                    eventoNombre: $eventoNombre,
                    fecha: $fecha,
                    lugar: $lugar,
                    horaInicio: $horaInicio,
                    horaCierre: $horaCierre,
                ));
                $enviados++;
            } catch (\Throwable $e) {
                $fallidos[] = "{$correo}: {$e->getMessage()}";
            }
        }

        return ['enviados' => $enviados, 'fallidos' => $fallidos];
    }
}
