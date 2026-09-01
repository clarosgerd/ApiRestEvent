<?php

namespace App\Actions;

use App\Models\Evento;
use App\Models\Participante;
use App\Models\Persona;
use App\Models\Registration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Purgar datos de Persona/Participante en inscripciones canceladas
 * (01/09/2026) — ver PLAN-PURGAR-DATOS-PERSONA-CANCELADA-01092026.md.
 * Pedido del usuario: cuando una inscripción termina `cancelled` (nunca
 * se completó el pago) y el organizador NO quiere retener esos datos
 * personales para ese evento (`eventos.mantener_datos_persona = false`),
 * se borra tanto el `Participante` de esa inscripción como la cuenta
 * `Persona` asociada.
 *
 * Se dispara SOLO al pasar a `cancelled` (nunca sobre un `pending` en
 * curso — alguien podría estar pagando en ese instante), desde
 * RegistrationService::updatePaymentStatus(), después de
 * notificarReversionCupo() (el email todavía necesita el correo del
 * participante).
 *
 * `Persona` es una cuenta GLOBAL (Authenticatable con su propio
 * login/token, matcheada por numero_documento/email, sin FK a evento ni
 * a registration — ver Persona.php) compartida por TODAS las
 * inscripciones que esa persona hizo en cualquier evento. Por eso, antes
 * de borrarla, se chequea que no tenga ninguna OTRA inscripción
 * paid/pending vigente en NINGÚN evento — si la tiene, se borra el
 * Participante de esta inscripción cancelada, pero Persona sobrevive
 * (borrarla rompería su login para siempre, en cualquier otro evento).
 *
 * No retroactivo — solo aplica hacia adelante desde que se activa el
 * flag, nunca sobre datos históricos ya pending/cancelled (decisión
 * explícita del usuario).
 */
class PurgarDatosPersonaCanceladaAction
{
    public function handle(Registration $registration): void
    {
        $evento = Evento::find($registration->evento_id);
        // Default seguro: si el evento no existe (no debería pasar) o
        // mantiene datos (default true), no se toca nada.
        if (!$evento || $evento->mantener_datos_persona) {
            return;
        }

        $identidadesBorradas = [];

        DB::transaction(function () use ($registration, &$identidadesBorradas) {
            foreach ($registration->participants as $participante) {
                // Seguridad: un participante con un resultado real cargado
                // (ej. sincronizado desde ChronoTrack) no se toca — sería
                // borrar un dato de carrera legítimo, no solo datos de una
                // inscripción que nunca se completó.
                if ($participante->resultado) {
                    continue;
                }

                $identidadesBorradas[] = [
                    'documento' => $participante->numero_documento,
                    'correo'    => $participante->correo,
                ];

                // Las 4 tablas hijas (contacto de emergencia, souvenirs,
                // talleres, answers) y la pivot de staff/ponente ya tienen
                // cascadeOnDelete() a nivel de BD (confirmado en sus
                // migraciones) — borrar el participante alcanza, mismo
                // criterio que ya usa ActualizarInscripcionPagadaAction
                // (`$registration->participants()->delete()`).
                $participante->delete();
            }

            foreach ($identidadesBorradas as $id) {
                if (empty($id['documento']) && empty($id['correo'])) {
                    continue;
                }

                // Vigente = tiene otra inscripción (en cualquier evento,
                // no solo este) que no sea cancelled/failed — mismo
                // criterio que FormType::inscritosVigentes().
                $tieneOtraValida = Participante::where(function ($q) use ($id) {
                        $q->where('numero_documento', $id['documento'])
                          ->orWhere('correo', $id['correo']);
                    })
                    ->whereHas('registration', fn ($q) => $q->whereNotIn('pago_status', ['cancelled', 'failed']))
                    ->exists();

                if ($tieneOtraValida) {
                    continue;
                }

                Persona::where('numero_documento', $id['documento'])
                    ->orWhere('email', $id['correo'])
                    ->first()
                    ?->delete();
            }
        });

        if (!empty($identidadesBorradas)) {
            Log::info('purgar-datos-persona-cancelada', [
                'referencia' => $registration->referencia,
                'evento_id'  => $registration->evento_id,
                'participantes_purgados' => count($identidadesBorradas),
            ]);
        }
    }
}
