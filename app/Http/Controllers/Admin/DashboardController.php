<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\EventoController as ApiEventoController;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Consolidación monolito (21/08/2026), Fase 1e-i — portado 1:1 de
 * admin-eventos. super_admin ve el listado paginado/buscable de todos los
 * eventos (delega en EventoController::index() de la API, el mismo que usa
 * el sitio público); un admin scoped ve solo el suyo. Ver
 * ApiRestEvent/brain/api_rest_event/PLAN-CONSOLIDACION-MONOLITO-21082026.md.
 */
class DashboardController extends Controller
{
    public function index(Request $request, ApiEventoController $api): View
    {
        $admin = session('admin_user');
        $search = trim((string) $request->query('search', ''));

        if ($admin['rol'] === 'super_admin') {
            $request->merge(array_filter([
                'per_page' => 20,
                'page'     => (int) $request->query('page', 1),
                'search'   => $search !== '' ? $search : null,
            ]));

            $payload = $api->index($request)->getData(true);
            $eventos = $payload['eventos'] ?? [];
            $pagination = $payload['pagination'] ?? null;
        } else {
            // Admin de evento asignado a varios eventos (28/08/2026,
            // sincronizado 14/09/2026) — 'eventoIds' es evento principal +
            // adicionales, deduplicado (ver AdminUser::eventoIds()); un
            // admin sin eventos adicionales sigue viendo exactamente su
            // único evento, como antes.
            $eventos = [];
            foreach ($admin['eventoIds'] ?? [] as $eventoId) {
                $evento = $api->show(\App\Models\Evento::findOrFail($eventoId))->getData(true)['eventos'] ?? null;
                if ($evento) {
                    $eventos[] = $evento;
                }
            }
            $pagination = null;
        }

        return view('admin.dashboard', compact('eventos', 'pagination', 'admin', 'search'));
    }
}
