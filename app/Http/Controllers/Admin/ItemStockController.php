<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ItemStockController as ApiItemStockController;
use App\Http\Requests\StoreItemStockRequest;
use App\Http\Requests\UpdateItemStockRequest;
use App\Models\ItemStock;
use App\Models\Souvenir;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Consolidación monolito (21/08/2026), Fase 1e-iii — stock por talla/sexo
 * de un ítem del kit. Portado 1:1 de admin-eventos, delegando en
 * ItemStockController de la API. `evento_id`/`nombre` viajan por
 * querystring solo para el breadcrumb (no hay endpoint liviano para
 * resolver el evento desde un souvenir_id) — mismo criterio que el
 * original, sin scoping de evento acá (la API tampoco lo exige para este
 * endpoint). Ver
 * ApiRestEvent/brain/api_rest_event/PLAN-CONSOLIDACION-MONOLITO-21082026.md.
 */
class ItemStockController extends Controller
{
    public function index(Request $request, Souvenir $souvenir, ApiItemStockController $api): View
    {
        $stock = $api->index($souvenir)->getData(true)['stock'] ?? [];

        return view('admin.souvenirs.stock', [
            'souvenirId' => $souvenir->id,
            'eventoId'   => $request->query('evento_id'),
            'nombre'     => $request->query('nombre', 'Ítem'),
            'stock'      => $stock,
        ]);
    }

    public function store(StoreItemStockRequest $request, Souvenir $souvenir, ApiItemStockController $api): RedirectResponse
    {
        $payload = $api->store($request, $souvenir)->getData(true);

        if (!($payload['success'] ?? false)) {
            return back()->withErrors($this->extractErrors($payload));
        }

        return redirect($this->volverUrl($souvenir->id, $request))->with('status', 'Stock agregado correctamente.');
    }

    public function update(UpdateItemStockRequest $request, ItemStock $itemStock, ApiItemStockController $api): RedirectResponse
    {
        $payload = $api->update($request, $itemStock)->getData(true);

        if (!($payload['success'] ?? false)) {
            return back()->withErrors($this->extractErrors($payload));
        }

        return redirect($this->volverUrl((int) $request->input('souvenir_id'), $request))->with('status', 'Stock actualizado correctamente.');
    }

    public function destroy(Request $request, ItemStock $itemStock, ApiItemStockController $api): RedirectResponse
    {
        $payload = $api->destroy($itemStock)->getData(true);

        if (!($payload['success'] ?? false)) {
            return back()->withErrors($this->extractErrors($payload));
        }

        return redirect($this->volverUrl((int) $request->input('souvenir_id'), $request))->with('status', 'Stock eliminado correctamente.');
    }

    private function volverUrl(int $souvenir, Request $request): string
    {
        return route('admin.souvenirs.stock.index', $souvenir) . '?' . http_build_query([
            'evento_id' => $request->input('evento_id'),
            'nombre'    => $request->input('nombre'),
        ]);
    }

    private function extractErrors(array $payload): array
    {
        $errors = $payload['errors'] ?? null;
        if (is_array($errors)) {
            return array_map(fn ($messages) => is_array($messages) ? implode(' ', $messages) : $messages, $errors);
        }

        return ['general' => $payload['error'] ?? 'Ocurrió un error.'];
    }
}
