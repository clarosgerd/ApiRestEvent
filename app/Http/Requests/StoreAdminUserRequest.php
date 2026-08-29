<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAdminUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre'    => 'required|string|max:255',
            'email'     => 'required|email|unique:admin_users,email',
            'password'  => 'required|string|min:8',
            'rol'       => 'required|string|in:super_admin,admin,cajero',
            'evento_id' => 'required_if:rol,admin,cajero|prohibited_if:rol,super_admin|integer|exists:eventos,id',
            // Admin de evento asignado a varios eventos (28/08/2026) — ver
            // PLAN-ADMIN-MULTI-EVENTO-28082026.md. Solo tiene sentido para
            // rol admin (cajero sigue con un único evento, evento_id).
            'evento_ids_adicionales'   => 'prohibited_unless:rol,admin|nullable|array',
            'evento_ids_adicionales.*' => 'integer|exists:eventos,id',
            'activo'    => 'nullable|boolean',
        ];
    }
}
