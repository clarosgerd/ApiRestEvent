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
 * estado_delivery, subtotal, numero_corredor ni chip — esos campos son de
 * precio/negocio, intocables desde este endpoint.
 *
 * `numero_documento` (04/09/2026) — antes estaba excluido a propósito acá
 * ("identidad, anti-fraude"). Pedido explícito del usuario: un admin
 * necesita poder corregir un documento mal cargado por el participante.
 * Sigue sin poder tocarse desde el autoservicio (elascenso/event) — ese
 * flujo ya lo permitía sin querer desde antes de este cambio y se decidió
 * dejarlo así (ver App\Actions\RegistrationService::createParticipantFromData()),
 * esto solo habilita el camino admin, que queda auditado
 * (ver ParticipanteController::update(), AdminAuditLogger).
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
            'numero_documento' => ['sometimes', 'string', 'max:50'],
        ];
    }
}
