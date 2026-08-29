<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAdminUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $adminUser = $this->route('user');

        return [
            'nombre'    => 'sometimes|string|max:255',
            'email'     => ['sometimes', 'email', Rule::unique('admin_users', 'email')->ignore($adminUser?->id)],
            'password'  => 'sometimes|string|min:8',
            'rol'       => 'sometimes|string|in:super_admin,admin,cajero',
            'evento_id' => 'sometimes|nullable|integer|exists:eventos,id',
            // Admin de evento asignado a varios eventos (28/08/2026) — ver
            // PLAN-ADMIN-MULTI-EVENTO-28082026.md.
            'evento_ids_adicionales'   => 'prohibited_unless:rol,admin|nullable|array',
            'evento_ids_adicionales.*' => 'integer|exists:eventos,id',
            'activo'    => 'sometimes|boolean',
        ];
    }
}
