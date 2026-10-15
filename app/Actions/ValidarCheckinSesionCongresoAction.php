<?php

namespace App\Actions;

use App\Models\AsistenciaSesion;
use App\Models\Evento;
use App\Models\Participante;
use App\Models\ParticipanteTallerSesion;
use App\Models\SesionCongreso;

/**
 * Validaciones compartidas de "¿puede este participante acreditarse en
 * ESTA sesión?" — extraída para no duplicarlas entre
 * AsistenciaSesionController (panel admin) y
 * StaffAppController::sesionCheckinBulk() (app de staff offline), que
 * hasta el 15/10/2026 repetían el mismo criterio línea por línea. Mismo
 * molde que CheckinParticipanteAction.
 *
 * `requiere_inscripcion` (15/10/2026, pedido real del usuario) — cierra un
 * hueco real de control de acceso: cualquier participante pagado del
 * evento podía acreditarse en CUALQUIER sesión, incluidas sesiones de
 * talleres de pago/cupo limitado que nunca compró ni seleccionó
 * (`participante_taller_sesion` registra esa selección/compra). El campo
 * `sesiones_congreso.requiere_inscripcion` ya existía en BD/modelo/CRUD
 * desde su creación (11/08/2026) pensado para esto, pero ningún controller
 * de check-in lo leía. Opt-in: cuando es `false` (default, y el valor de
 * TODAS las sesiones existentes hasta que un organizador lo prenda a
 * mano), el comportamiento es exactamente el de siempre — cero cambio
 * para quien no use el flag.
 *
 * `sesion.taller_id` nulo (15/10/2026, encontrado al revisar el fix de
 * arriba) — una sesión "suelta" de un congreso SIN talleres (107 en la BD
 * actual) nunca puede tener una fila en `participante_taller_sesion`:
 * `CrearInscripcionAction`/`RegistrationService` solo crean esa fila
 * cuando la sesión pertenece a un taller (`$sesion->taller_id` no nulo) —
 * no existe ningún mecanismo de "selección" para una sesión suelta. Si
 * `requiere_inscripcion` quedara activo en una de estas, NADIE podría
 * acreditarse nunca — un lockout total sin salida. Por eso el flag solo
 * se evalúa cuando la sesión tiene taller; en una sesión suelta queda
 * siempre libre, sin importar el valor del flag. Mismo criterio aplica
 * si el taller de una sesión se borra después (`nullOnDelete` en
 * `sesiones_congreso.taller_id`): la sesión pasa a "suelta" y el check-in
 * se abre solo, en vez de quedar bloqueada para siempre.
 */
class ValidarCheckinSesionCongresoAction
{
    /**
     * Devuelve null si el participante puede acreditarse, o
     * ['motivo' => string, 'status' => int] si hay que rechazarlo.
     */
    public function validarParticipante(Evento $event, SesionCongreso $sesion, Participante $participante): ?array
    {
        $participante->loadMissing('registration');

        if (!$participante->registration || (int) $participante->registration->evento_id !== $event->id) {
            return ['motivo' => 'Este participante no pertenece a este evento.', 'status' => 404];
        }

        if ($participante->registration->pago_status !== 'paid') {
            return ['motivo' => 'No se puede acreditar: el pago no está confirmado.', 'status' => 422];
        }

        if ($sesion->requiere_inscripcion && $sesion->taller_id !== null) {
            $inscrito = ParticipanteTallerSesion::where('participante_id', $participante->id)
                ->where('sesion_congreso_id', $sesion->id)
                ->exists();

            if (!$inscrito) {
                return ['motivo' => 'Este participante no está inscrito a esta sesión.', 'status' => 403];
            }
        }

        return null;
    }

    public function cupoLleno(SesionCongreso $sesion): bool
    {
        if ($sesion->cupo === null) {
            return false;
        }

        return AsistenciaSesion::where('sesion_congreso_id', $sesion->id)->count() >= $sesion->cupo;
    }
}
