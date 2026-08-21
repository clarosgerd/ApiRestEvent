<?php

namespace App\Http\Requests\Concerns;

use App\Models\FormType;

/**
 * Caja para eventos tipo congreso (20/08/2026) — el contacto de
 * emergencia dejó de ser obligatorio de forma incondicional; ahora
 * depende de `form_types.requiere_contacto_emergencia` (default true, ver
 * migración add_requiere_contacto_emergencia_to_form_types_table). Las
 * reglas base de cada Request pasan a `nullable` y este trait hace el
 * chequeo real en `withValidator()`, porque la condición depende de una
 * consulta a `form_types` que no se puede expresar con las reglas
 * declarativas de Laravel para arrays anidados.
 *
 * Usado por StoreRegistrationRequest, StoreInscripcionCajaRequest,
 * UpdateRegistrationRequest y UpdatePaidRegistrationRequest.
 */
trait ValidaContactoEmergenciaCondicional
{
    protected function formTypeRequiereContactoEmergencia(mixed $formTypeId): bool
    {
        if (!$formTypeId) {
            return true;
        }

        return (bool) (FormType::find($formTypeId)?->requiere_contacto_emergencia ?? true);
    }

    /**
     * @return string[] nombres de los campos faltantes ('nombre'|'celular'|'relacion')
     */
    protected function camposContactoEmergenciaFaltantes(array $contacto): array
    {
        return array_values(array_filter(
            ['nombre', 'celular', 'relacion'],
            fn (string $campo) => empty($contacto[$campo] ?? null)
        ));
    }
}
