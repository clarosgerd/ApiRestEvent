<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * WhatsApp Business API oficial por organizador (08/10/2026) — ver
 * WhatsappCuentaController::assertIsSuperAdmin(), no `authorize()` (mismo
 * criterio que StoreSipBancoRequest).
 */
class StoreWhatsappCuentaRequest extends FormRequest
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
            'phone_number_id' => 'required|string|max:255',
            'business_account_id' => 'nullable|string|max:255',
            'access_token' => 'required|string|max:1000',
            'template_name' => 'nullable|string|max:255',
            'template_lang' => 'nullable|string|max:10',
            'activo' => 'nullable|boolean',
        ];
    }
}
