<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Sync periódico (pull) de participantes de un evento con registro propio
 * externo (17/09/2026) — ver EventoSyncExternoConfig/SyncExternoConfigController.
 * `token` es opcional en el request (un campo vacío = "no tocar el token
 * actual", ver controller) — nunca se exige de nuevo en cada guardado.
 */
class StoreEventoSyncExternoConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'form_types_id' => 'required|integer|exists:form_types,id',
            'nombre_fuente' => 'nullable|string|max:255',
            'url' => 'required|url|max:2000',
            'token' => 'nullable|string|max:255',
            'activo' => 'nullable|boolean',
        ];
    }
}
