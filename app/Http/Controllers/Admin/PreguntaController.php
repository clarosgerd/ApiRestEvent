<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\DelegatesToApiJson;
use App\Http\Controllers\Controller;
use App\Http\Controllers\FormularioCamposController as ApiFormularioCamposController;
use App\Http\Requests\StoreFormularioCamposRequest;
use App\Http\Requests\UpdateFormularioCamposRequest;
use App\Models\FormType;
use App\Models\FormularioCampos;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Consolidación monolito (21/08/2026), Fase 1c — preguntas adicionales del
 * formulario de inscripción. `opciones_texto` (un textarea, una opción por
 * línea) se parsea a `options[]` acá, igual que en admin-eventos — es
 * puro formateo de UI, no lógica de negocio. Ver
 * ApiRestEvent/brain/api_rest_event/PLAN-CONSOLIDACION-MONOLITO-21082026.md.
 */
class PreguntaController extends Controller
{
    use DelegatesToApiJson;

    public function store(Request $request, FormType $formType, ApiFormularioCamposController $api): RedirectResponse
    {
        $merge = [
            'obligatorio' => $request->boolean('obligatorio'),
            'visible_en_reporte' => $request->boolean('visible_en_reporte'),
            'options' => $this->parseOpciones($request->input('opciones_texto', '')),
        ];

        $validated = $this->mergeAndValidate(StoreFormularioCamposRequest::class, $request, $merge);

        return $this->redirectToEventoTab($api->store($validated, $formType), $request->input('evento_id'), 'tipos', 'Pregunta creada correctamente.');
    }

    public function update(Request $request, FormularioCampos $pregunta, ApiFormularioCamposController $api): RedirectResponse
    {
        $merge = [
            'obligatorio' => $request->boolean('obligatorio'),
            'visible_en_reporte' => $request->boolean('visible_en_reporte'),
            'options' => $this->parseOpciones($request->input('opciones_texto', '')),
        ];

        $validated = $this->mergeAndValidate(UpdateFormularioCamposRequest::class, $request, $merge);

        return $this->redirectToEventoTab($api->update($validated, $pregunta), $request->input('evento_id'), 'tipos', 'Pregunta actualizada correctamente.');
    }

    public function destroy(Request $request, FormularioCampos $pregunta, ApiFormularioCamposController $api): RedirectResponse
    {
        return $this->redirectToEventoTab($api->destroy($pregunta), $request->input('evento_id'), 'tipos', 'Pregunta eliminada correctamente.');
    }

    private function parseOpciones(string $texto): array
    {
        $lineas = array_values(array_filter(array_map('trim', explode("\n", $texto)), fn ($l) => $l !== ''));

        return array_map(fn ($l, $i) => ['option_text' => $l, 'order' => $i], $lineas, array_keys($lineas));
    }
}
