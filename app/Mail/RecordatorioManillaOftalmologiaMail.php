<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Recordatorio de recojo de manilla — XXXI Congreso Boliviano de
 * Oftalmología (22/10/2026, pedido puntual del usuario). Envío
 * manual, único, disparado por `notificaciones:recordatorio-manilla-oftalmologia`
 * — no pasa por NotificacionService porque no es parte del flujo
 * automático de notificaciones de una inscripción, es un aviso
 * logístico aparte con lugar/horario que el organizador recién
 * confirmó.
 */
class RecordatorioManillaOftalmologiaMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $eventoNombre,
        public string $fecha,
        public string $lugar,
        public string $horaInicio,
        public string $horaCierre,
    ) {
    }

    public function build(): self
    {
        return $this->subject("Recordatorio: recoge tu manilla — {$this->eventoNombre}")
            ->view('emails.recordatorio-manilla-oftalmologia')
            ->with([
                'eventoNombre' => $this->eventoNombre,
                'fecha'        => $this->fecha,
                'lugar'        => $this->lugar,
                'horaInicio'   => $this->horaInicio,
                'horaCierre'   => $this->horaCierre,
            ]);
    }
}
