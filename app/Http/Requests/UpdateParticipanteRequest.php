<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Edición restringida de un participante desde el panel de administración
 * (admin-eventos) — solo datos no sensibles de contacto/identidad, más la
 * talla de camiseta (únicamente si el participante ya tiene una asignada,
 * ver ParticipanteController::update()). Esta lista de reglas ES el
 * whitelist real: no agregar aquí categoria, precio_categoria, donacion,
 * promo_codigo, promo_descuento, equipo_id, quiere_delivery,
 * estado_delivery, subtotal, numero_corredor, chip ni numero_documento —
 * esos campos son de precio/negocio o de identidad anti-fraude,
 * intocables desde este endpoint.
 */
class UpdateParticipanteRequest extends FormRequest
{
    /**
     * La autorización real la hace AuthorizesEventoScope::assertCanWriteEvento()
     * en el controller (mismo patrón que Category/FormType/PromoCode de Fase 0).
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'nombre'           => ['sometimes', 'string', 'max:255'],
            'apellido'         => ['sometimes', 'string', 'max:255'],
            'alias'            => ['sometimes', 'nullable', 'string', 'max:255'],
            'correo'           => ['sometimes', 'email', 'max:255'],
            'telefono'         => ['sometimes', 'string', 'max:30'],
            'direccion'        => ['sometimes', 'string', 'max:255'],
            'ciudad'           => ['sometimes', 'string', 'max:255'],
            'genero'           => ['sometimes', 'in:Masculino,Femenino,Otro'],
            'fecha_nacimiento' => ['sometimes', 'date'],
            'polera'           => ['sometimes', 'nullable', 'string', 'max:50'],
        ];
    }
}
