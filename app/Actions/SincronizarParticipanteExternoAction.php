<?php

namespace App\Actions;

use App\Models\Evento;
use App\Models\FormType;
use App\Models\Participante;
use App\Models\Registration;
use App\Models\RegistrationTotal;
use Illuminate\Support\Facades\DB;

/**
 * Sync de participantes de un congreso externo (07/09/2026) — ver
 * brain/PLAN-SYNC-CONGRESO-EXTERNO-07092026.md. Primer caso: COLABIOCLI
 * 2026, cuyas inscripciones viven en un Google Sheet propio del organizador
 * (fuera de nuestro sistema), sincronizadas acá solo para poder reusar
 * Retiro en sitio (entrega de kit — que ya incluye la credencial, así que
 * también cubre acreditación) — NO para consolidación de balance.
 *
 * A propósito NO reusa `CrearInscripcionAction`: esa valida precio contra
 * `categories`, stock, fee%, moneda, promo — nada de eso aplica, no es una
 * transacción real nuestra (el dinero ya se cobró en la plataforma del
 * organizador). Este Action inserta directo, con su propia validación
 * mínima.
 *
 * Su formulario no pide documento de identidad — se usa el CORREO como
 * `numero_documento` (único, se pide individualmente incluso en
 * inscripción grupal). Es también la clave de idempotencia: reenviar la
 * misma fila (o el sheet completo, sin filtrar "lo nuevo") actualiza en vez
 * de duplicar.
 */
class SincronizarParticipanteExternoAction
{
    /**
     * @param array{nombre?: string, apellido?: string, correo?: string, telefono?: string, categoria?: string, ubicacion?: string} $fila
     * @return array{resultado: 'creado'|'actualizado'|'omitido', motivo?: string, participanteId?: int}
     */
    public function run(Evento $evento, FormType $formType, array $fila): array
    {
        $nombre = trim((string) ($fila['nombre'] ?? ''));
        $apellido = trim((string) ($fila['apellido'] ?? ''));
        $correo = strtolower(trim((string) ($fila['correo'] ?? '')));

        if ($nombre === '' || $apellido === '') {
            return ['resultado' => 'omitido', 'motivo' => 'Falta nombre o apellido.'];
        }
        if ($correo === '' || !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            return ['resultado' => 'omitido', 'motivo' => "Correo faltante o inválido: \"{$correo}\"."];
        }

        $categoria = trim((string) ($fila['categoria'] ?? ''));
        $telefono = trim((string) ($fila['telefono'] ?? ''));
        $ubicacion = trim((string) ($fila['ubicacion'] ?? ''));

        return DB::transaction(function () use ($evento, $formType, $nombre, $apellido, $correo, $categoria, $telefono, $ubicacion) {
            $participante = Participante::whereHas(
                'registration',
                fn ($q) => $q->where('evento_id', $evento->id)
            )->where('numero_documento', $correo)->first();

            if ($participante) {
                $participante->update([
                    'nombre' => $nombre,
                    'apellido' => $apellido,
                    'categoria' => $categoria !== '' ? $categoria : $participante->categoria,
                    'telefono' => $telefono !== '' ? $telefono : $participante->telefono,
                    'ciudad' => $ubicacion !== '' ? $ubicacion : $participante->ciudad,
                ]);

                return ['resultado' => 'actualizado', 'participanteId' => $participante->id];
            }

            $referencia = 'EXT-' . strtoupper(substr(md5(uniqid((string) mt_rand(), true)), 0, 8));

            $registration = Registration::create([
                'referencia' => $referencia,
                'fecha' => now(),
                'evento_id' => $evento->id,
                'form_types_id' => $formType->id,
                'evento_nombre' => $evento->nombre,
                // 'externo': no es un cobro real nuestro — visible en
                // cualquier reporte/export para que no se confunda con un
                // pago procesado por nuestras pasarelas.
                'tipo_pago' => 'externo',
                'pago_status' => 'paid',
            ]);

            $participante = Participante::create([
                'registration_id' => $registration->id,
                'nombre' => $nombre,
                'apellido' => $apellido,
                // genero/fecha_nacimiento/edad: NOT NULL en el esquema pero
                // no los pide el formulario del congreso — sentinels
                // explícitos, no un dato inventado que parezca real.
                'genero' => 'Otro',
                'tipo_documento' => 'EMAIL',
                'numero_documento' => $correo,
                'fecha_nacimiento' => '1900-01-01',
                'edad' => 0,
                'correo' => $correo,
                'direccion' => '',
                'ciudad' => $ubicacion,
                'telefono' => $telefono,
                'categoria' => $categoria !== '' ? $categoria : 'Sin categoría',
                'subtotal' => 0,
            ]);

            // En cero, explícito — no es consolidación de balance (ver
            // docblock de la clase). `registration.totals` lo asumen no
            // nulo varios lugares del sistema (BalanceEventoData, edición
            // pagada), así que igual hace falta la fila.
            RegistrationTotal::create([
                'registration_id' => $registration->id,
                'inscripcion' => 0,
                'donacion' => 0,
                'souvenirs' => 0,
                'talleres' => 0,
                'fee' => 0,
                'descuento' => 0,
                'descuento_registrante' => 0,
                'grand_total' => 0,
                'costo_edicion_acumulado' => 0,
            ]);

            return ['resultado' => 'creado', 'participanteId' => $participante->id];
        });
    }
}
