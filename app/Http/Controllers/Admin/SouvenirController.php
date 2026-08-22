<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\DelegatesToApiJson;
use App\Http\Controllers\Controller;
use App\Http\Controllers\SouvenirController as ApiSouvenirController;
use App\Http\Requests\StoreSouvenirRequest;
use App\Http\Requests\UpdateSouvenirRequest;
use App\Models\FormType;
use App\Models\Souvenir;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Consolidación monolito (21/08/2026), Fase 1c — ítems del kit de un
 * form_type. Redirige a `#tipos` (viven anidados en esa pestaña, sin
 * pestaña propia — mismo criterio que admin-eventos). Ver
 * ApiRestEvent/brain/api_rest_event/PLAN-CONSOLIDACION-MONOLITO-21082026.md.
 */
class SouvenirController extends Controller
{
    use DelegatesToApiJson;

    private const BOOLEAN_FIELDS = ['incluido', 'requiere_talla', 'requiere_sexo'];

    public function store(Request $request, FormType $formType, ApiSouvenirController $api): RedirectResponse
    {
        $merge = ['form_types_id' => $formType->id];
        foreach (self::BOOLEAN_FIELDS as $field) {
            $merge[$field] = $request->boolean($field);
        }

        $validated = $this->mergeAndValidate(StoreSouvenirRequest::class, $request, $merge);

        return $this->redirectToEventoTab($api->store($validated), $request->input('evento_id'), 'tipos', 'Ítem creado correctamente.');
    }

    public function update(Request $request, Souvenir $souvenir, ApiSouvenirController $api): RedirectResponse
    {
        $merge = [];
        foreach (self::BOOLEAN_FIELDS as $field) {
            $merge[$field] = $request->boolean($field);
        }

        $validated = $this->mergeAndValidate(UpdateSouvenirRequest::class, $request, $merge);

        return $this->redirectToEventoTab($api->update($validated, $souvenir), $request->input('evento_id'), 'tipos', 'Ítem actualizado correctamente.');
    }

    public function destroy(Request $request, Souvenir $souvenir, ApiSouvenirController $api): RedirectResponse
    {
        return $this->redirectToEventoTab($api->destroy($souvenir), $request->input('evento_id'), 'tipos', 'Souvenir eliminado correctamente.');
    }
}
