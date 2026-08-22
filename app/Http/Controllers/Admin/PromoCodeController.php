<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\DelegatesToApiJson;
use App\Http\Controllers\Controller;
use App\Http\Controllers\PromoCodeController as ApiPromoCodeController;
use App\Http\Requests\StorePromoCodeRequest;
use App\Http\Requests\UpdatePromoCodeRequest;
use App\Models\PromoCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Consolidación monolito (21/08/2026), Fase 1c — ver CategoriaController
 * (mismo patrón). Ver
 * ApiRestEvent/brain/api_rest_event/PLAN-CONSOLIDACION-MONOLITO-21082026.md.
 */
class PromoCodeController extends Controller
{
    use DelegatesToApiJson;

    public function store(Request $request, int $evento, ApiPromoCodeController $api): RedirectResponse
    {
        $validated = $this->mergeAndValidate(StorePromoCodeRequest::class, $request, ['event_id' => $evento]);

        return $this->redirectToEventoTab($api->store($validated), $evento, 'promos', 'Código de promoción creado correctamente.');
    }

    public function update(UpdatePromoCodeRequest $request, PromoCode $promoCode, ApiPromoCodeController $api): RedirectResponse
    {
        return $this->redirectToEventoTab($api->update($request, $promoCode), $request->input('evento_id'), 'promos', 'Código de promoción actualizado correctamente.');
    }

    public function destroy(Request $request, PromoCode $promoCode, ApiPromoCodeController $api): RedirectResponse
    {
        return $this->redirectToEventoTab($api->destroy($promoCode), $request->input('evento_id'), 'promos', 'Código de promoción eliminado correctamente.');
    }
}
