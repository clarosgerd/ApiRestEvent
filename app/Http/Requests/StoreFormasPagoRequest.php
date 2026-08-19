<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Catálogo global de formas de pago (19/08/2026) — ver
 * brain/PLAN-INTEGRACION-PAGO-MERU-19082026.md. La autorización real la
 * hace FormasPagoController::assertIsSuperAdmin() (mismo criterio que
 * StorePaisRequest/StoreOrganizadorRequest), no este `authorize()`.
 *
 * `pasarela` no está restringida a una lista fija a propósito: crear una
 * fila con `pasarela` = un slug sin código real detrás (p.ej. "meru" antes
 * de implementarlo) es intencionalmente inofensivo — elascenso/event
 * (registro.php) ya rechaza con 503 cualquier `tipo=integrado` cuya
 * `pasarela` no esté en su lista de pasarelas soportadas.
 */
class StoreFormasPagoRequest extends FormRequest
{
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
            'slug' => 'required|string|max:50|alpha_dash|unique:formas_pagos,slug',
            'nombre' => 'required|string|max:100',
            'descripcion' => 'nullable|string|max:255',
            'pasarela' => 'nullable|string|max:50',
            'tipo' => ['required', Rule::in(['integrado', 'manual'])],
            'config' => 'nullable|array',
            'activo' => 'nullable|boolean',
        ];
    }
}
