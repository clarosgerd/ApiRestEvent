<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\DelegatesToApiJson;
use App\Http\Controllers\Controller;
use App\Http\Controllers\EventoController as ApiEventoController;
use App\Http\Controllers\ItemBodegaController as ApiItemBodegaController;
use App\Http\Requests\AsignarItemBodegaRequest;
use App\Http\Requests\StoreItemBodegaRequest;
use App\Http\Requests\UpdateItemBodegaRequest;
use App\Models\Evento;
use App\Models\ItemBodega;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Consolidación monolito (21/08/2026), Fase 1e-iii — Bodega de stock por
 * evento. Portado 1:1 de admin-eventos, delegando en ItemBodegaController
 * de la API. Ver
 * ApiRestEvent/brain/api_rest_event/PLAN-CONSOLIDACION-MONOLITO-21082026.md
 * y PLAN-BODEGA-STOCK-EVENTO-14082026.md (feature original).
 */
class ItemBodegaController extends Controller
{
    use DelegatesToApiJson;

    public function index(Evento $event, ApiEventoController $apiEvento, ApiItemBodegaController $apiItemBodega): View
    {
        $this->assertCanViewEvento($event->id);

        $eventoData = $apiEvento->show($event)->getData(true)['eventos'] ?? null;
        abort_if(!$eventoData, 404);

        $itemBodega = $apiItemBodega->index($event)->getData(true)['item_bodega'] ?? [];

        return view('admin.eventos.bodega', [
            'evento'     => $eventoData,
            'itemBodega' => $itemBodega,
            'formTypes'  => $eventoData['formTypes'] ?? [],
        ]);
    }

    public function store(StoreItemBodegaRequest $request, Evento $event, ApiItemBodegaController $api): RedirectResponse
    {
        $this->assertCanViewEvento($event->id);

        return $this->redirectFromApiResponse($api->store($request, $event), 'admin.bodega.index', [$event->id]);
    }

    public function update(UpdateItemBodegaRequest $request, Evento $event, ItemBodega $itemBodega, ApiItemBodegaController $api): RedirectResponse
    {
        $this->assertCanViewEvento($event->id);

        return $this->redirectFromApiResponse($api->update($request, $itemBodega), 'admin.bodega.index', [$event->id]);
    }

    public function destroy(Evento $event, ItemBodega $itemBodega, ApiItemBodegaController $api): RedirectResponse
    {
        $this->assertCanViewEvento($event->id);

        return $this->redirectFromApiResponse($api->destroy($itemBodega), 'admin.bodega.index', [$event->id]);
    }

    /**
     * Crea la asignación (Souvenir) a un form_type y redirige a la pestaña
     * "Tipos" del evento — mismo destino que crear un ítem del kit suelto.
     */
    public function asignar(AsignarItemBodegaRequest $request, Evento $event, ItemBodega $itemBodega, ApiItemBodegaController $api): RedirectResponse
    {
        $this->assertCanViewEvento($event->id);

        $payload = $api->asignar($request, $itemBodega)->getData(true);

        if (!($payload['success'] ?? false)) {
            return back()->withErrors($this->extractErrorsPublic($payload));
        }

        return redirect(route('admin.eventos.edit', $event->id) . '#tipos')
            ->with('status', 'Ítem asignado — cargá su precio, si viene incluido, y su stock propio desde ahí.');
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

    /** Igual que DelegatesToApiJson::extractErrors(), ver EventoController. */
    private function extractErrorsPublic(array $payload): array
    {
        $errors = $payload['errors'] ?? null;
        if (is_array($errors)) {
            return array_map(fn ($messages) => is_array($messages) ? implode(' ', $messages) : $messages, $errors);
        }

        return ['general' => $payload['error'] ?? $payload['message'] ?? 'Ocurrió un error.'];
    }
}
