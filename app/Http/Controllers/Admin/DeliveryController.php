<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\DeliveryController as ApiDeliveryController;
use App\Http\Controllers\EventoController as ApiEventoController;
use App\Models\Evento;
use Illuminate\View\View;

/**
 * Consolidación monolito (21/08/2026), Fase 1e-iii — mapa de ubicación de
 * delivery de un evento (solo lectura). Portado 1:1 de admin-eventos,
 * delegando en DeliveryController::indexForAdmin() de la API. Ver
 * ApiRestEvent/brain/api_rest_event/PLAN-CONSOLIDACION-MONOLITO-21082026.md.
 */
class DeliveryController extends Controller
{
    public function index(Evento $event, ApiEventoController $apiEvento, ApiDeliveryController $apiDelivery): View
    {
        $this->assertCanViewEvento($event->id);

        $eventoData = $apiEvento->show($event)->getData(true)['eventos'] ?? null;
        abort_if(!$eventoData, 404);

        $payload = $apiDelivery->indexForAdmin($event)->getData(true);

        return view('admin.eventos.delivery', [
            'evento' => $eventoData,
            'participantes' => $payload['participantes'] ?? [],
            'resumen' => $payload['resumen'] ?? [],
        ]);
    }

    /**
     * Mismo criterio que Admin\NumeracionController::assertCanViewEvento.
     */
    private function assertCanViewEvento(int $evento): void
    {
        $admin = session('admin_user');

        if (($admin['rol'] ?? null) !== 'super_admin' && (int) ($admin['evento_id'] ?? 0) !== $evento) {
            abort(403, 'No tiene acceso a este evento.');
        }
    }
}
