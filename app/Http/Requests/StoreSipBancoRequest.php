<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * SIP multi-banco (28/08/2026) — ver
 * brain/api_rest_event/PLAN-SIP-MULTIBANCO-28082026.md. La autorización
 * real la hace SipBancoController::assertIsSuperAdmin() (mismo criterio
 * que StoreAdminUserRequest), no `authorize()`.
 */
class StoreSipBancoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'organizador_id' => 'nullable|integer|exists:organizadores,id',
            'nombre' => 'required|string|max:100',
            'sip_username' => 'required|string|max:255',
            'sip_password' => 'required|string|max:255',
            'sip_apikey' => 'required|string|max:255',
            'sip_apikey_servicio' => 'required|string|max:255',
            'sip_base_auth_url' => 'nullable|url|max:255',
            'sip_base_api_url' => 'nullable|url|max:255',
            'callback_basic_user' => 'required|string|max:255',
            'callback_basic_password' => 'required|string|max:255',
            'activo' => 'nullable|boolean',
        ];
    }
}
