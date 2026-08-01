<?php

namespace App\Actions;

use App\Mail\EventoDashboardMail;
use App\Models\Evento;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

class EnviarDashboardOrganizadorAction
{
    /**
     * Envía el link firmado del dashboard de organizador (y el de delivery,
     * si el evento tiene algún form_type con hasDelivery) al organizador del
     * evento y a los usuarios admin/superadmin del sistema.
     */
    public function handle(Evento $evento): void
    {
        $dashboardUrl = URL::signedRoute('organizador.dashboard', ['evento' => $evento->id]);

        $deliveryUrl = $evento->formTypes->contains('has_delivery', true)
            ? URL::signedRoute('delivery.dashboard', ['evento' => $evento->id])
            : null;

        $destinatarios = collect();
        if ($evento->organizador && $evento->organizador->email) {
            $destinatarios->push($evento->organizador->email);
        }
        $destinatarios = $destinatarios
            ->merge(User::whereIn('role', ['admin', 'superadmin'])->pluck('email'))
            ->filter()
            ->unique();

        foreach ($destinatarios as $correo) {
            try {
                // Instancia nueva por destinatario — reusar la misma Mailable en
                // varios Mail::to()->send() acumula destinatarios (mismo motivo
                // documentado en RecordatorioDashboardOrganizador).
                Mail::to($correo)->send(new EventoDashboardMail($evento, $dashboardUrl, $deliveryUrl));
            } catch (\Throwable $e) {
                Log::error("No se pudo enviar dashboard de organizador a {$correo} para evento #{$evento->id}: {$e->getMessage()}");
            }
        }
    }
}
