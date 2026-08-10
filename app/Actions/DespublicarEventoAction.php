<?php

namespace App\Actions;

use App\Models\Evento;
use App\Services\AdminAuditLogger;

class DespublicarEventoAction
{
    /**
     * Revierte un evento publicado a borrador — a diferencia de
     * PublicarEventoAction, no dispara ningún correo (es solo un cambio de
     * estado interno).
     *
     * El chequeo de "¿ya tiene participantes inscritos?" (409, no 422)
     * queda deliberadamente en el controller y no acá — es una guarda de
     * "no se puede tocar este evento", no parte del state-machine
     * publicado/borrador que valida esta Action, y mapea a un código HTTP
     * distinto. Acá solo se valida el estado (publicado=true) antes de
     * mutar, mismo criterio que PublicarEventoAction.
     *
     * Lanza \DomainException si el evento no está publicado.
     */
    public function handle(Evento $event): Evento
    {
        if (!$event->publicado) {
            throw new \DomainException('Este evento no está publicado.');
        }

        $event->update(['publicado' => false]);

        AdminAuditLogger::log('despublicar', 'evento', $event->id, $event->id, ['publicado' => true], ['publicado' => false]);

        return $event;
    }
}
