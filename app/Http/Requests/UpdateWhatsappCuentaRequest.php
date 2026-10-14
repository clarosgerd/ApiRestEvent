<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * WhatsApp Business API oficial por organizador (08/10/2026) — `access_token`
 * "sometimes|nullable" a propósito: el form de edición en admin-eventos deja
 * ese campo vacío por default ("dejar vacío para no cambiar", mismo criterio
 * que UpdateSipBancoRequest) — si no viene, no se toca.
 */
class UpdateWhatsappCuentaRequest extends FormRequest
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
            'phone_number_id' => 'sometimes|required|string|max:255',
            'business_account_id' => 'sometimes|nullable|string|max:255',
            'access_token' => 'sometimes|nullable|string|max:1000',
            'template_name' => 'sometimes|nullable|string|max:255',
            'template_lang' => 'sometimes|nullable|string|max:10',
            'activo' => 'sometimes|boolean',
        ];
    }
}
