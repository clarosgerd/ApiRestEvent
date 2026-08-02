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
            'rol'       => 'sometimes|string|in:super_admin,admin',
            'evento_id' => 'sometimes|nullable|integer|exists:eventos,id',
            'activo'    => 'sometimes|boolean',
        ];
    }
}
