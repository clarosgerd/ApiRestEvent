<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\DelegatesToApiJson;
use App\Http\Controllers\Controller;
use App\Http\Controllers\FormTypeController as ApiFormTypeController;
use App\Http\Requests\StoreFormTypeRequest;
use App\Http\Requests\UpdateFormTypeRequest;
use App\Models\FormType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Consolidación monolito (21/08/2026), Fase 1c — llama al endpoint SUELTO
 * de la API (`FormTypeController`, snake_case + event_id explícito), no al
 * nested que usa Admin\EventoController::store() (camelCase, sin
 * event_id). Ver FormTypeController de admin-eventos (mismo comentario
 * original) y
 * ApiRestEvent/brain/api_rest_event/PLAN-CONSOLIDACION-MONOLITO-21082026.md.
 */
class FormTypeController extends Controller
{
    use DelegatesToApiJson;

    private const FIELDS = [
        'name', 'icon', 'imagen_url', 'description', 'tipo', 'cupo_total', 'precio_base',
        'costo_edicion', 'tiempo_expiracion_min', 'color',
    ];

    private const BOOLEAN_FIELDS = [
        'requiere_categoria', 'has_team', 'has_delivery', 'has_donation', 'has_promo_code',
        'es_staff', 'es_ponente', 'requiere_contacto_emergencia',
    ];

    public function store(Request $request, int $evento, ApiFormTypeController $api): RedirectResponse
    {
        $merge = ['event_id' => $evento];
        foreach (self::BOOLEAN_FIELDS as $field) {
            $merge[$field] = $request->boolean($field);
        }

        $validated = $this->mergeAndValidate(StoreFormTypeRequest::class, $request, $merge);

        return $this->redirectToEventoTab($api->store($validated), $evento, 'tipos', 'Tipo de formulario creado correctamente.');
    }

    public function update(Request $request, FormType $formType, ApiFormTypeController $api): RedirectResponse
    {
        $merge = [];
        foreach (self::BOOLEAN_FIELDS as $field) {
            $merge[$field] = $request->boolean($field);
        }

        $validated = $this->mergeAndValidate(UpdateFormTypeRequest::class, $request, $merge);

        return $this->redirectToEventoTab($api->update($validated, $formType), $request->input('evento_id'), 'tipos', 'Tipo de formulario actualizado correctamente.');
    }

    public function destroy(Request $request, FormType $formType, ApiFormTypeController $api): RedirectResponse
    {
        return $this->redirectToEventoTab($api->destroy($formType), $request->input('evento_id'), 'tipos', 'Tipo de formulario eliminado correctamente.');
    }
}
