<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\DelegatesToApiJson;
use App\Http\Controllers\Controller;
use App\Http\Controllers\EventoController as ApiEventoController;
use App\Http\Controllers\TallerCongresoController as ApiTallerCongresoController;
use App\Http\Requests\StoreTallerRequest;
use App\Http\Requests\UpdateTallerRequest;
use App\Models\Evento;
use App\Models\Taller;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Consolidación monolito (21/08/2026), Fase 1e-ii — CRUD de talleres de
 * congreso. Portado 1:1 de admin-eventos, delegando en
 * TallerCongresoController de la API. Ver
 * ApiRestEvent/brain/api_rest_event/PLAN-CONSOLIDACION-MONOLITO-21082026.md.
 */
class TallerCongresoController extends Controller
{
    use DelegatesToApiJson;

    public function index(Evento $event, ApiEventoController $apiEvento, ApiTallerCongresoController $apiTaller): View
    {
        $this->assertCanViewEvento($event->id);

        $eventoData = $apiEvento->show($event)->getData(true)['eventos'] ?? null;
        abort_if(!$eventoData, 404);

        $talleres = $this->dataFrom($apiTaller->index($event));

        return view('admin.eventos.talleres.index', [
            'evento' => $eventoData,
            'talleres' => $talleres,
        ]);
    }

    public function store(StoreTallerRequest $request, Evento $event, ApiTallerCongresoController $api): RedirectResponse
    {
        $this->assertCanViewEvento($event->id);

        return $this->redirectFromApiResponse($api->store($request, $event), 'admin.talleres.index', [$event->id]);
    }

    public function update(UpdateTallerRequest $request, Evento $event, Taller $taller, ApiTallerCongresoController $api): RedirectResponse
    {
        $this->assertCanViewEvento($event->id);

        return $this->redirectFromApiResponse($api->update($request, $event, $taller), 'admin.talleres.index', [$event->id]);
    }

    public function destroy(Evento $event, Taller $taller, ApiTallerCongresoController $api): RedirectResponse
    {
        $this->assertCanViewEvento($event->id);

        return $this->redirectFromApiResponse($api->destroy($event, $taller), 'admin.talleres.index', [$event->id]);
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
