<?php

namespace App\Actions;

use App\Models\Evento;
use App\Models\EventoNotification;
use App\Services\AdminAuditLogger;

class PublicarEventoAction
{
    /**
     * Publica un evento — pasa de borrador (el contrato con el organizador
     * todavía no está firmado) a elegible para mostrarse/inscribirse. Este
     * es el único momento en que se envía el correo con el link del
     * dashboard de organizador (y de delivery, si aplica): un evento recién
     * creado (borrador) todavía puede cambiar de forma, así que no tiene
     * sentido avisarle al organizador hasta que esté publicado.
     *
     * Lanza \DomainException si el evento ya está publicado — el
     * controller la atrapa y devuelve un 422 (mismo criterio que el resto
     * de la app, ver RegistrationService).
     */
    public function handle(Evento $event): Evento
    {
        if ($event->publicado) {
            throw new \DomainException('Este evento ya está publicado.');
        }

        $event->update(['publicado' => true]);

        app(EnviarDashboardOrganizadorAction::class)->handle($event->fresh(['formTypes', 'organizador']));
        EventoNotification::create([
            'evento_id'  => $event->id,
            'tipo'       => 'evento_publicado_dashboard_organizador',
            'canal'      => 'email',
            'enviado_at' => now(),
        ]);

        AdminAuditLogger::log('publicar', 'evento', $event->id, $event->id, ['publicado' => false], ['publicado' => true]);

        return $event;
    }
}
