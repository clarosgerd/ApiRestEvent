<?php

namespace App\Http\Controllers;

use App\Actions\CheckinParticipanteAction;
use App\Models\AsistenciaSesion;
use App\Models\Evento;
use App\Models\SesionCongreso;
use App\Models\Participante;
use App\Services\AdminAuditLogger;
use App\Support\ParticipanteExportData;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * App de staff offline (02/10/2026) — para una app móvil externa (Android/
 * iOS) usada por el staff de un evento en ubicaciones remotas sin internet
 * confiable: el staff se loguea con su cuenta de Persona (/persona/login,
 * mismo Sanctum de siempre), descarga TODOS los participantes del evento de
 * una vez, y sube de vuelta los check-ins que hizo offline cuando recupera
 * conexión.
 *
 * OJO — "staff" acá es un concepto DISTINTO de `SesionCongresoStaffController`
 * (vincular un participante como ayudante de una sesión de congreso
 * puntual). Comparten el mismo flag `form_types.es_staff`, pero no tienen
 * relación: este controller es acreditación GENERAL del evento
 * (`participantes.checked_in_at`), no de una sesión.
 *
 * Autorización: NO es un secreto compartido tipo `internal/*` (eso es para
 * integraciones server-to-server sin usuario humano) — acá el staff es una
 * Persona real autenticada, y la autorización ("¿es staff de ESTE evento,
 * pagado?") se revalida en cada request vía
 * `Persona::participanteStaffParaEvento()`, nunca cacheada en el token.
 */
class StaffAppController extends Controller
{
    private function assertEsStaffDelEvento(Request $request, Evento $event): ?JsonResponse
    {
        $persona = $request->user();
        $participante = $persona?->participanteStaffParaEvento($event);

        if (! $participante) {
            return response()->json([
                'success' => false,
                'error'   => 'No sos staff de este evento.',
            ], 403);
        }

        return null;
    }

    /**
     * Descarga completa de participantes del evento, para uso offline.
     * Mismo shape que `ParticipanteController::porEvento()` (reusa
     * `ParticipanteExportData`) — solo inscripciones `pago_status='paid'`,
     * sin paginar.
     */
    public function participantes(Request $request, Evento $event): JsonResponse
    {
        if ($error = $this->assertEsStaffDelEvento($request, $event)) {
            return $error;
        }

        $participantes = Participante::whereHas('registration', fn ($q) => $q->where('evento_id', $event->id)
                ->where('pago_status', 'paid'))
            ->with(['registration:id,referencia,pago_status,fecha,tipo_pago,moneda_pago', 'talleresSesiones.sesionCongreso', 'talleresSesiones.taller', 'souvenirParticipante', 'answers', 'equipo', 'registration.totals'])
            ->orderBy('categoria')
            ->orderBy('apellido')
            ->get(ParticipanteExportData::COLUMNS);

        $exportData = ParticipanteExportData::paraEvento($event);

        return response()->json([
            'success'       => true,
            'participantes' => $participantes->map([$exportData, 'mapear']),
        ]);
    }

    /**
     * Sync-back de check-ins hechos offline — no es todo-o-nada: cada ítem
     * se evalúa por separado (mismo molde que
     * AsistenciaSesionController::checkinBulk(), pero sobre
     * `participantes.checked_in_at`, no `asistencia_sesion`). Timestamp
     * por ítem (no uno global para todo el lote): dos dispositivos, o el
     * mismo dispositivo en momentos distintos, pueden traer checkins con
     * horas distintas en la misma subida.
     *
     * Conflicto entre dispositivos (02/10/2026, decisión explícita): gana
     * el primero en llegar al servidor — CheckinParticipanteAction ya es
     * idempotente (no pisa un checked_in_at existente), mismo criterio que
     * el check-in individual en vivo, sin cambiar esa semántica acá.
     */
    public function checkinBulk(Request $request, Evento $event, CheckinParticipanteAction $action): JsonResponse
    {
        if ($error = $this->assertEsStaffDelEvento($request, $event)) {
            return $error;
        }

        $persona = $request->user();

        $data = $request->validate([
            'checkins'               => ['required', 'array', 'min:1'],
            'checkins.*.participanteId' => ['required', 'integer'],
            'checkins.*.checkedInAt'    => ['required', 'date'],
        ]);

        $acreditados = [];
        $yaAcreditados = [];
        $rechazados = [];

        DB::transaction(function () use ($data, $event, $persona, $action, &$acreditados, &$yaAcreditados, &$rechazados) {
            foreach ($data['checkins'] as $item) {
                $participanteId = (int) $item['participanteId'];
                $checkedInAt = CarbonImmutable::parse($item['checkedInAt']);

                if ($checkedInAt->isFuture()) {
                    $rechazados[] = ['participanteId' => $participanteId, 'motivo' => 'La fecha/hora del check-in es futura.'];
                    continue;
                }

                $participante = Participante::whereHas('registration', fn ($q) => $q->where('evento_id', $event->id))
                    ->with('registration')
                    ->find($participanteId);

                if (! $participante) {
                    $rechazados[] = ['participanteId' => $participanteId, 'motivo' => 'Participante no encontrado en este evento.'];
                    continue;
                }

                $resultado = $action->handle($participante, $persona, $checkedInAt);

                match ($resultado['status']) {
                    CheckinParticipanteAction::REJECTED_UNPAID => $rechazados[] = ['participanteId' => $participanteId, 'motivo' => 'El pago no está confirmado.'],
                    CheckinParticipanteAction::ALREADY => $yaAcreditados[] = $participanteId,
                    CheckinParticipanteAction::CHECKED_IN => $acreditados[] = $participanteId,
                };
            }
        });

        return response()->json([
            'success'       => true,
            'acreditados'   => $acreditados,
            'yaAcreditados' => $yaAcreditados,
            'rechazados'    => $rechazados,
        ]);
    }

    /**
     * Sesiones del evento para la app de staff (05/10/2026): id, horario, sala
     * y cuántas personas llevan acreditadas, para elegir la sesión offline.
     * Mismo control que la descarga de participantes (staff del evento).
     */
    public function sesiones(Request $request, Evento $event): JsonResponse
    {
        if ($error = $this->assertEsStaffDelEvento($request, $event)) {
            return $error;
        }

        $sesiones = SesionCongreso::where('evento_id', $event->id)
            ->withCount('asistencias')
            ->orderBy('fecha')
            ->orderBy('hora_inicio')
            ->get()
            ->map(fn (SesionCongreso $sesion) => [
                'id' => $sesion->id,
                'titulo' => $sesion->titulo,
                'ponente' => $sesion->ponente,
                'sala' => $sesion->sala,
                'fecha' => optional($sesion->fecha)->format('Y-m-d'),
                'horaInicio' => substr((string) $sesion->hora_inicio, 0, 5),
                'horaFin' => substr((string) $sesion->hora_fin, 0, 5),
                'cupo' => $sesion->cupo,
                'acreditados' => $sesion->asistencias_count,
            ]);

        return response()->json(['success' => true, 'sesiones' => $sesiones]);
    }

    /**
     * Check-in masivo a una sesión desde la app de staff (05/10/2026). Mismas
     * reglas que el check-in por sesión del admin (AsistenciaSesionController):
     * participante del evento, pago confirmado, sin duplicar, cupo. Cada ítem se
     * evalúa por separado. El autor queda como Persona (staff_persona_id).
     */
    public function sesionCheckinBulk(Request $request, Evento $event, SesionCongreso $sesion): JsonResponse
    {
        if ($error = $this->assertEsStaffDelEvento($request, $event)) {
            return $error;
        }

        if ((int) $sesion->evento_id !== (int) $event->id) {
            return response()->json(['success' => false, 'error' => 'La sesión no pertenece a este evento.'], 404);
        }

        $data = $request->validate([
            'participante_ids' => 'required|array|min:1',
            'participante_ids.*' => 'integer',
        ]);

        $persona = $request->user();
        $acreditados = [];
        $yaAcreditados = [];
        $rechazados = [];

        DB::transaction(function () use ($data, $event, $sesion, $persona, &$acreditados, &$yaAcreditados, &$rechazados) {
            foreach ($data['participante_ids'] as $participanteId) {
                $participante = Participante::with('registration')
                    ->whereHas('registration', fn ($q) => $q->where('evento_id', $event->id))
                    ->find($participanteId);

                if (! $participante) {
                    $rechazados[] = ['participanteId' => $participanteId, 'motivo' => 'Este participante no pertenece a este evento.'];
                    continue;
                }

                if ($participante->registration->pago_status !== 'paid') {
                    $rechazados[] = ['participanteId' => $participanteId, 'motivo' => 'No se puede acreditar: el pago no está confirmado.'];
                    continue;
                }

                $existe = AsistenciaSesion::where('sesion_congreso_id', $sesion->id)
                    ->where('participante_id', $participanteId)
                    ->exists();
                if ($existe) {
                    $yaAcreditados[] = $participanteId;
                    continue;
                }

                if ($sesion->cupo !== null
                    && AsistenciaSesion::where('sesion_congreso_id', $sesion->id)->count() >= $sesion->cupo) {
                    $rechazados[] = ['participanteId' => $participanteId, 'motivo' => 'Esta sesión llegó a su cupo máximo.'];
                    continue;
                }

                $asistencia = AsistenciaSesion::create([
                    'sesion_congreso_id' => $sesion->id,
                    'participante_id' => $participanteId,
                    'checkin_at' => now(),
                    'staff_persona_id' => $persona->id,
                ]);

                AdminAuditLogger::log('checkin_sesion', 'AsistenciaSesion', $asistencia->id, $event->id, null, $asistencia->toArray(), $persona);

                $acreditados[] = $participanteId;
            }
        });

        return response()->json([
            'success' => true,
            'acreditados' => $acreditados,
            'yaAcreditados' => $yaAcreditados,
            'rechazados' => $rechazados,
        ]);
    }
}

