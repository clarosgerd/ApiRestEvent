<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesEventoScope;
use App\Http\Requests\StoreFormularioCamposRequest;
use App\Http\Requests\UpdateFormularioCamposRequest;
use App\Http\Resources\FormTypeResource;
use App\Models\FormularioCampos;
use App\Models\FormType;
use App\Services\AdminAuditLogger;
use Illuminate\Http\JsonResponse;

/**
 * CRUD de preguntas adicionales del formulario de inscripción (tabla
 * `questions`, modelo FormularioCampos) — 20/08/2026. El consumo público
 * ya existía desde antes (elascenso/event/index.php::
 * renderDynamicQuestions() las renderiza, CrearInscripcionAction guarda
 * las respuestas en `answers`) pero nunca hubo forma de administrarlas:
 * este controller y QuestionOptionsController eran scaffolds vacíos de
 * `make:controller --resource`, sin rutas registradas. Mismo scoping que
 * Souvenir/CategoryPricePeriod (admin de su propio evento, o
 * super_admin). Sin index() propio: la lista ya viaja embebida en
 * FormTypeResource.preguntas (GET /event/{id}).
 *
 * Las opciones (select/radio/checkbox) se mandan anidadas en el mismo
 * payload en vez de tener su propio CRUD — más simple para el formulario
 * del panel (un solo "Guardar" por pregunta). En update() se reemplazan
 * todas (delete+create) en vez de diffear una por una: seguro acá porque
 * `answers.value` guarda el texto elegido, no una FK a QuestionOptions,
 * así que no queda nada huérfano al borrar las viejas.
 */
class FormularioCamposController extends Controller
{
    use AuthorizesEventoScope;

    public function store(StoreFormularioCamposRequest $request, FormType $formType): JsonResponse
    {
        $this->assertCanWriteEvento((int) $formType->event_id);

        $data = $request->validated();
        $options = $data['options'] ?? [];
        unset($data['options']);

        $data['form_types_id'] = $formType->id;
        // 'placeholder'/'opciones' son NOT NULL sin default en la migración
        // (columnas legado) — 'opciones' (JSON suelto en la misma tabla)
        // quedó sin usar desde que se agregó la tabla `question_options`
        // relacional (ver FormularioCampos::options()); el frontend público
        // solo lee esta última.
        $data['placeholder'] = $data['placeholder'] ?? '';
        $data['opciones'] = '[]';

        $pregunta = FormularioCampos::create($data);
        $this->syncOptions($pregunta, $options);

        AdminAuditLogger::log('create', 'pregunta', $pregunta->id, (int) $formType->event_id, null, $pregunta->fresh('options')->toArray());

        return response()->json([
            'success'  => true,
            'message'  => 'Pregunta creada correctamente.',
            'formType' => new FormTypeResource($formType->fresh('formularioCampos.options')),
        ], 201);
    }

    public function update(UpdateFormularioCamposRequest $request, FormularioCampos $pregunta): JsonResponse
    {
        $formType = $pregunta->formType;
        $this->assertCanWriteEvento((int) $formType->event_id);

        $data = $request->validated();
        $options = $data['options'] ?? null;
        unset($data['options']);
        if (array_key_exists('placeholder', $data)) {
            $data['placeholder'] = $data['placeholder'] ?? '';
        }

        $before = $pregunta->toArray();
        $pregunta->update($data);

        if ($options !== null) {
            $this->syncOptions($pregunta, $options);
        }

        AdminAuditLogger::log('update', 'pregunta', $pregunta->id, (int) $formType->event_id, $before, $pregunta->fresh('options')->toArray());

        return response()->json([
            'success'  => true,
            'message'  => 'Pregunta actualizada correctamente.',
            'formType' => new FormTypeResource($formType->fresh('formularioCampos.options')),
        ]);
    }

    /**
     * Borrar una pregunta se lleva en cascada sus opciones y — importante —
     * las respuestas ya guardadas de participantes que la contestaron
     * (`answers.question_id` tiene FK cascadeOnDelete, ver la migración).
     * Mismo criterio de "borrado en cascada real" ya aceptado en
     * SesionCongreso (borra asistencia) — el aviso de esta pérdida de
     * datos va en el `confirm()` del panel, no bloqueado acá.
     */
    public function destroy(FormularioCampos $pregunta): JsonResponse
    {
        $formType = $pregunta->formType;
        $this->assertCanWriteEvento((int) $formType->event_id);

        $before = $pregunta->toArray();
        $pregunta->delete();

        AdminAuditLogger::log('delete', 'pregunta', $before['id'], (int) $formType->event_id, $before, null);

        return response()->json([
            'success'  => true,
            'message'  => 'Pregunta eliminada correctamente.',
            'formType' => new FormTypeResource($formType->fresh('formularioCampos.options')),
        ]);
    }

    private function syncOptions(FormularioCampos $pregunta, array $options): void
    {
        $pregunta->options()->delete();
        foreach (array_values($options) as $i => $opt) {
            $pregunta->options()->create([
                'option_text' => $opt['option_text'],
                'order'       => $opt['order'] ?? $i,
            ]);
        }
    }
}
