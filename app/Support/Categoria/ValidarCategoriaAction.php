<?php

namespace App\Support\Categoria;

use App\DTOs\ParticipantDTO;
use App\DTOs\RegistrationDTO;
use App\Models\Category;
use App\Models\FormType;
use App\Support\PrecioVigenteData;

/**
 * Validación de la categoría elegida por un participante — extraída
 * (04/09/2026) del antiguo `CrearInscripcionAction::validatePrecioCategoria()`
 * (método privado, solo corría en el alta) a una clase compartida, mismo
 * espíritu que `App\Support\Taller\ValidarSeleccionesTallerAction`: se
 * necesitaba la MISMA regla en el alta (`CrearInscripcionAction`) y en la
 * edición de una inscripción pendiente (`ActualizarInscripcionAction`, que
 * antes no revalidaba categoría en absoluto). Ver
 * brain/api_rest_event/PLAN-CATEGORIA-PERMITE-INSCRIPCION-04092026.md.
 *
 * Sin "grandfather clause" acá — ni el alta ni la edición pendiente la
 * tienen para talleres tampoco (`ValidarSeleccionesTallerAction::run()` se
 * llama sin `$sesionIdsPreviasPorIndice` en ambos flujos): revalidación
 * completa siempre. La edición de una inscripción YA PAGADA es la única
 * que exime "sin cambios" — eso vive aparte, en
 * `App\Support\EdicionPagadaCategoriaData::resolver()`.
 */
class ValidarCategoriaAction
{
    /**
     * Precios por período (12/08/2026) — ver PRD-precios-periodos-fechas.md,
     * Hallazgo #2 y sección 0. No se confía en `precio_categoria` tal cual
     * llegue del proxy (`elascenso/event`) — mismo criterio que
     * `CrearInscripcionAction::validateFeePct()`. Ramifica por
     * `formType.requiere_categoria`:
     * - true: el precio esperado es el vigente de la categoría (períodos
     *   o `categories.price`, ver PrecioVigenteData::paraCategoria()), y la
     *   categoría debe tener `permite_inscripcion=true` (04/09/2026 — ver
     *   docblock de la clase).
     * - false: no hay categoría real que resolver — el precio esperado es
     *   `form_types.precio_base` directo.
     * Aplica siempre, con o sin períodos configurados — defensa en
     * profundidad real, no placebo.
     */
    public static function run(RegistrationDTO $registrationDTO, ParticipantDTO $participantDTO): void
    {
        $formType = FormType::find($registrationDTO->formId);
        if (!$formType) {
            return; // ya se validó que el form_type existe antes de llegar acá
        }

        if ($formType->requiere_categoria) {
            // Categorías por form_type (27/08/2026) — ver
            // PLAN-CATEGORIAS-POR-FORM-TYPE-27082026.md. `formulario_id`
            // null = categoría compartida por todos los form_types del
            // evento (comportamiento previo, sin cambios); si tiene un
            // valor, solo es válida para ESE form_type. Antes de este
            // cambio, el server solo chequeaba `event_id` — una categoría
            // pensada para el form_type "10K" se aceptaba igual al
            // inscribirse por "5K" del mismo evento.
            $category = Category::where('id', $participantDTO->category)
                ->where('event_id', $registrationDTO->eventId)
                ->where(fn ($q) => $q->whereNull('formulario_id')->orWhere('formulario_id', $registrationDTO->formId))
                ->first();

            if (!$category) {
                throw new \DomainException(
                    "La categoría '{$participantDTO->category}' no es válida para este evento."
                );
            }

            $precioVigente = PrecioVigenteData::paraCategoria($category)['precio'];

            if (abs($precioVigente - $participantDTO->categoryPrice) > 0.01) {
                throw new \DomainException(
                    'El precio de la categoría no coincide con el vigente. Recargá la página e intentá de nuevo.'
                );
            }

            // Deshabilitar una categoría sin ocultarla (04/09/2026) — mismo
            // tratamiento que Taller::permite_inscripcion en
            // ValidarSeleccionesTallerAction::runPorParticipante(): sigue
            // siendo una categoría válida (existe, precio correcto), pero
            // el organizador la marcó como no elegible en este momento.
            if (!$category->permite_inscripcion) {
                throw new \DomainException(
                    "La categoría '{$category->name}' no está disponible para inscripción en este momento."
                );
            }

            return;
        }

        if (abs((float) $formType->precio_base - $participantDTO->categoryPrice) > 0.01) {
            throw new \DomainException(
                'El precio de inscripción no coincide con el vigente. Recargá la página e intentá de nuevo.'
            );
        }
    }
}
