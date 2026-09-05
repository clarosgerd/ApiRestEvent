<?php

namespace App\Support;

use App\Models\Participante;

/**
 * Edición restringida a solo souvenirs/talleres (04/09/2026) — pedido de
 * organizadores de congresos: cuando `form_types.edicion_solo_extras` está
 * activo, el participante puede editar su inscripción (pendiente o pagada)
 * únicamente para agregar souvenirs/talleres — no puede tocar sus datos
 * personales ni cambiar de categoría.
 *
 * En vez de rechazar la edición si el cliente manda esos campos cambiados,
 * se ignoran en silencio: se sobreescribe cada campo bloqueado con el valor
 * que el participante ya tenía, ANTES de que el resto del flujo (validación
 * de categoría, cálculo de delta, etc.) toque el array — así el resto del
 * código ni se entera de que hubo un intento de cambio, se comporta como si
 * el participante hubiera mandado exactamente lo mismo que ya tenía. Mismo
 * criterio "no confiar en lo que manda el cliente para lo sensible" que ya
 * usan `ActualizarInscripcionPagadaAction` (talleres/categoría/souvenirs
 * ya pagados) y `App\Support\Categoria\ValidarCategoriaAction`.
 *
 * Usado por `ActualizarInscripcionAction` (edición pendiente) y
 * `ActualizarInscripcionPagadaAction` (edición pagada) — mismo criterio en
 * los dos flujos, sin distinción.
 */
class EdicionSoloExtrasData
{
    /**
     * @param array $participantData Un elemento de $data['participantes'], tal cual llega del cliente.
     * @param Participante $anterior El participante existente, correlacionado por posición
     *                                (requiere `contactoEmergenciaParticipante` eager-cargado
     *                                por el caller para no hacer N+1).
     *
     * @return array El mismo array, con los campos bloqueados sobreescritos.
     */
    public static function aplicar(array $participantData, Participante $anterior): array
    {
        $contacto = $anterior->contactoEmergenciaParticipante;
        $nacimiento = $anterior->fecha_nacimiento;

        return array_merge($participantData, [
            'nombre'           => $anterior->nombre,
            'apellido'         => $anterior->apellido,
            'alias'            => $anterior->alias,
            'genero'           => $anterior->genero,
            'tipoDocumento'    => $anterior->tipo_documento,
            'numeroDocumento'  => $anterior->numero_documento,
            'nacimiento'       => $nacimiento ? [
                'anio' => (int) $nacimiento->format('Y'),
                'mes'  => (int) $nacimiento->format('m'),
                'dia'  => (int) $nacimiento->format('d'),
            ] : ($participantData['nacimiento'] ?? null),
            'edad'             => $anterior->edad,
            'correo'           => $anterior->correo,
            'direccion'        => $anterior->direccion,
            'ciudad'           => $anterior->ciudad,
            'telefono'         => $anterior->telefono,
            'contacto_emergencia' => [
                'nombre'   => $contacto->nombre ?? '',
                'celular'  => $contacto->celular ?? '',
                'relacion' => $contacto->relacion ?? '',
            ],
            // Categoría bloqueada igual que los datos personales (decisión
            // explícita del usuario) — al dejarla idéntica a la anterior,
            // ValidarCategoriaAction/EdicionPagadaCategoriaData la ven como
            // "sin cambios" y no hace falta ningún chequeo adicional acá.
            'categoria'        => (string) $anterior->categoria,
            'precioCategoria'  => (float) $anterior->precio_categoria,
        ]);
    }
}
