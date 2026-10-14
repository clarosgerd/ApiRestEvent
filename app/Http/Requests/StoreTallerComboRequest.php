<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Combos de talleres (12/10/2026, pedido del usuario) — "Taller A + Taller
 * B por el precio de 1" o "por $300 fijo". `fecha_desde`/`fecha_hasta` son
 * OPCIONALES (a diferencia de un período de precio, obligatorio) — un
 * combo puede no tener vigencia por fecha. El chequeo de "pertenece al
 * mismo evento" y "horario compatible" vive en el controller (depende de
 * las filas ya guardadas, no es una regla de forma).
 *
 * El precio NO se carga acá (13/10/2026, pedido del usuario) — el mismo
 * combo puede costar distinto según la categoría del participante, se
 * carga aparte vía TallerComboPrecioController una vez creado el combo.
 */
class StoreTallerComboRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre'       => 'required|string|max:255',
            'fecha_desde'  => 'nullable|date',
            'fecha_hasta'  => ['nullable', 'date', Rule::when(
                fn () => $this->filled('fecha_desde'),
                ['after_or_equal:fecha_desde']
            )],
            'activo'       => 'nullable|boolean',
            'taller_ids'   => 'required|array|min:2',
            'taller_ids.*' => 'required|integer|exists:talleres,id',
        ];
    }
}
