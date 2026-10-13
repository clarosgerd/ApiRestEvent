<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Precio por categoría de un combo de talleres (13/10/2026, pedido del
 * usuario) — `category_id` nullable: vacío = fila DEFAULT (aplica a
 * cualquier categoría sin fila propia). El chequeo de "pertenece al mismo
 * evento del combo" y "no hay otra fila para esta misma categoría/default"
 * vive en el controller.
 */
class StoreTallerComboPrecioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category_id' => 'nullable|integer|exists:categories,id',
            'precio'      => 'required|numeric|min:0',
        ];
    }
}
