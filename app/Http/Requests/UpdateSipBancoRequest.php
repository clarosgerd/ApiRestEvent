<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * SIP multi-banco (28/08/2026) — todos los campos "sometimes" a
 * propósito: el form de edición en admin-eventos deja los campos de
 * secretos vacíos por default ("dejar vacío para no cambiar", mismo
 * criterio que la contraseña de AdminUser) — si no vienen, no se tocan.
 */
class UpdateSipBancoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'organizador_id' => 'sometimes|nullable|integer|exists:organizadores,id',
            'nombre' => 'sometimes|required|string|max:100',
            'sip_username' => 'sometimes|required|string|max:255',
            'sip_password' => 'sometimes|nullable|string|max:255',
            'sip_apikey' => 'sometimes|nullable|string|max:255',
            'sip_apikey_servicio' => 'sometimes|nullable|string|max:255',
            'sip_base_auth_url' => 'sometimes|nullable|url|max:255',
            'sip_base_api_url' => 'sometimes|nullable|url|max:255',
            'callback_basic_user' => 'sometimes|required|string|max:255',
            'callback_basic_password' => 'sometimes|nullable|string|max:255',
            'activo' => 'sometimes|boolean',
        ];
    }
}
