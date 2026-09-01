<?php

namespace App\Http\Requests;

use App\Models\Genero;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Caja de cobro presencial (14/08/2026) — alta de un participante nuevo
 * desde el mostrador. Mismo detalle de campos que el formulario público
 * (no un subset tipo carga CSV) — ver PLAN-CAJA-COBRO-PRESENCIAL-14082026.md,
 * "Actualización 2".
 */
class StoreInscripcionCajaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'form_types_id'                         => ['required', 'integer'],

            'participante.nombre'                    => ['required', 'string'],
            'participante.apellido'                  => ['required', 'string'],
            'participante.alias'                     => ['nullable', 'string'],
            // Género por catálogo (31/08/2026) — ver StoreRegistrationRequest.
            'participante.genero'                    => ['nullable', 'string', Rule::in(Genero::where('activo', true)->pluck('nombre'))],
            'participante.tipoDocumento'              => ['nullable', 'string'],
            'participante.numeroDocumento'            => ['required', 'string'],
            'participante.polera'                     => ['nullable', 'string'],
            'participante.precioPolera'               => ['nullable', 'numeric'],
            'participante.nacimiento'                 => ['required', 'array'],
            'participante.nacimiento.dia'             => ['required', 'numeric'],
            'participante.nacimiento.mes'             => ['required', 'numeric'],
            'participante.nacimiento.anio'            => ['required', 'numeric'],
            'participante.edad'                       => ['required', 'numeric'],
            'participante.correo'                     => ['required', 'email'],
            'participante.direccion'                  => ['nullable', 'string'],
            'participante.ciudad'                     => ['nullable', 'string'],
            'participante.telefono'                   => ['nullable', 'string'],
            'participante.categoria'                  => ['required'],
            'participante.equipoId'                   => ['nullable', 'integer'],
            'participante.quiereDelivery'              => ['nullable', 'boolean'],
            'participante.deliveryLat'                 => ['nullable', 'numeric'],
            'participante.deliveryLng'                 => ['nullable', 'numeric'],
            'participante.precioCategoria'            => ['required', 'numeric'],
            'participante.donacion'                   => ['nullable', 'numeric'],
            'participante.promoDescuento'             => ['nullable', 'numeric'],
            'participante.promoCodigo'                => ['nullable', 'string'],
            'participante.subtotal'                   => ['required', 'numeric'],
            // Contacto de emergencia — ya nunca obligatorio (31/08/2026),
            // ver StoreRegistrationRequest.
            'participante.contacto_emergencia'        => ['nullable', 'array'],
            'participante.contacto_emergencia.nombre' => ['nullable', 'string'],
            'participante.contacto_emergencia.celular' => ['nullable', 'string'],
            'participante.contacto_emergencia.relacion' => ['nullable', 'string'],
            'participante.souvenirs'                  => ['nullable', 'array'],
            'participante.answers'                    => ['nullable', 'array'],
            // Talleres de congreso en Caja (20/08/2026) — mismas reglas
            // permisivas que StoreRegistrationRequest/Update*Request: el
            // detalle de pertenencia/cupo/solape lo revalida
            // CrearInscripcionAction del lado servidor.
            'participante.talleres'                   => ['nullable', 'array'],

            'totales'                                => ['required', 'array'],
            'totales.inscripcion'                    => ['required', 'numeric'],
            'totales.donacion'                        => ['required', 'numeric'],
            'totales.souvenirs'                       => ['required', 'numeric'],
            'totales.talleres'                        => ['nullable', 'numeric'],
            'totales.fee'                             => ['required', 'numeric'],
            'totales.descuento'                       => ['required', 'numeric'],
            'totales.descuento_registrante'            => ['nullable', 'numeric'],
            'totales.grand_total'                     => ['required', 'numeric'],
        ];
    }

    public function messages(): array
    {
        return [
            'participante.correo.email' => 'Correo inválido.',
        ];
    }
}
