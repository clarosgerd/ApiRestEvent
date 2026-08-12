<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesEventoScope;
use App\Models\AsistenciaSesion;
use App\Models\Category;
use App\Models\Evento;
use App\Models\Participante;
use App\Models\Registration;
use App\Models\SesionCongreso;
use App\Services\AdminAuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Check-in de staff por sesión de congreso (individual y masivo) +
 * reporte de asistencia. Ver PRD-Agenda-sessiones-onlycongresos.md.
 * Mismo molde que RegistrationController::checkinLookup /
 * ParticipanteController::checkin (idempotente, gate real de pago
 * server-side, AdminAuditLogger), pero scoped a sesión en vez de solo
 * evento, y con cupo propio por sesión.
 */
class AsistenciaSesionController extends Controller
{
    use AuthorizesEventoScope;

    /**
     * Busca una inscripción por referencia para acreditar en ESTA
     * sesión — mismo criterio que checkinLookup: si la referencia es de
     * otro evento, se trata como "no existe" (404), para no filtrar
     * datos ajenos a través de un QR equivocado.
     */
    public function lookup(Evento $event, SesionCongreso $sesion, string $reference): JsonResponse
    {
        $this->assertCanWriteEvento($event->id);
        $this->assertSesionPerteneceAEvento($event, $sesion);

        $registration = Registration::with('participants')
            ->where('referencia', $reference)
            ->where('evento_id', $event->id)
            ->firstOrFail();

        $categoriasPorId = Category::where('event_id', $event->id)->get()->keyBy(fn ($c) => (string) $c->id);
        $participanteIds = $registration->participants->pluck('id');
        $asistenciasPorParticipante = AsistenciaSesion::where('sesion_congreso_id', $sesion->id)
            ->whereIn('participante_id', $participanteIds)
            ->get()
            ->keyBy('participante_id');

        return response()->json([
            'success' => true,
            'referencia' => $registration->referencia,
            'pagoStatus' => $registration->pago_status,
            'sesion' => ['id' => $sesion->id, 'titulo' => $sesion->titulo],
            'participantes' => $registration->participants->map(function (Participante $p) use ($categoriasPorId, $asistenciasPorParticipante) {
                $asistencia = $asistenciasPorParticipante->get($p->id);

                return [
                    'id' => $p->id,
                    'nombre' => $p->nombre,
                    'apellido' => $p->apellido,
                    'categoria' => $categoriasPorId->get($p->categoria)->name ?? $p->categoria,
                    'asistioSesion' => $asistencia !== null,
                    'checkinAt' => $asistencia ? optional($asistencia->checkin_at)->toIso8601String() : null,
                ];
            }),
        ]);
    }

    /**
     * Check-in individual — reescanear a alguien ya acreditado en esta
     * sesión NO es un error (mismo criterio que ParticipanteController::checkin).
     */
    public function checkin(Evento $event, SesionCongreso $sesion, Participante $participante): JsonResponse
    {
        $this->assertCanWriteEvento($event->id);
        $this->assertSesionPerteneceAEvento($event, $sesion);

        $error = $this->validarParticipanteParaSesion($event, $sesion, $participante);
        if ($error) {
            return response()->json(['success' => false, 'error' => $error['motivo']], $error['status']);
        }

        $existente = AsistenciaSesion::where('sesion_congreso_id', $sesion->id)
            ->where('participante_id', $participante->id)
            ->first();

        if ($existente) {
            return response()->json([
                'success' => true,
                'alreadyCheckedIn' => true,
                'checkinAt' => optional($existente->checkin_at)->toIso8601String(),
            ]);
        }

        if ($this->cupoLleno($sesion)) {
            return response()->json([
                'success' => false,
                'error' => "Esta sesión llegó a su cupo máximo ({$sesion->cupo}).",
            ], 409);
        }

        $asistencia = AsistenciaSesion::create([
            'sesion_congreso_id' => $sesion->id,
            'participante_id' => $participante->id,
            'checkin_at' => now(),
            'staff_admin_user_id' => auth('admins')->id(),
        ]);

        AdminAuditLogger::log('checkin_sesion', 'AsistenciaSesion', $asistencia->id, $event->id, null, $asistencia->toArray());

        return response()->json([
            'success' => true,
            'alreadyCheckedIn' => false,
            'checkinAt' => $asistencia->checkin_at->toIso8601String(),
        ], 201);
    }

    /**
     * Check-in masivo — no es todo-o-nada: cada participante se evalúa
     * por separado (ya acreditado / pago no confirmado / cupo lleno /
     * acreditado ahora), y se reporta el resultado de cada uno.
     */
    public function checkinBulk(Request $request, Evento $event, SesionCongreso $sesion): JsonResponse
    {
        $this->assertCanWriteEvento($event->id);
        $this->assertSesionPerteneceAEvento($event, $sesion);

        $data = $request->validate([
            'participante_ids' => 'required|array|min:1',
            'participante_ids.*' => 'integer',
        ]);

        $acreditados = [];
        $yaAcreditados = [];
        $rechazados = [];

        DB::transaction(function () use ($data, $event, $sesion, &$acreditados, &$yaAcreditados, &$rechazados) {
            foreach ($data['participante_ids'] as $participanteId) {
                $participante = Participante::with('registration')->find($participanteId);

                if (!$participante) {
                    $rechazados[] = ['participanteId' => $participanteId, 'motivo' => 'Participante no encontrado.'];
                    continue;
                }

                $error = $this->validarParticipanteParaSesion($event, $sesion, $participante);
                if ($error) {
                    $rechazados[] = ['participanteId' => $participanteId, 'motivo' => $error['motivo']];
                    continue;
                }

                $existente = AsistenciaSesion::where('sesion_congreso_id', $sesion->id)
                    ->where('participante_id', $participanteId)
                    ->first();

                if ($existente) {
                    $yaAcreditados[] = $participanteId;
                    continue;
                }

                if ($this->cupoLleno($sesion)) {
                    $rechazados[] = ['participanteId' => $participanteId, 'motivo' => 'Cupo lleno.'];
                    continue;
                }

                $asistencia = AsistenciaSesion::create([
                    'sesion_congreso_id' => $sesion->id,
                    'participante_id' => $participanteId,
                    'checkin_at' => now(),
                    'staff_admin_user_id' => auth('admins')->id(),
                ]);

                AdminAuditLogger::log('checkin_sesion', 'AsistenciaSesion', $asistencia->id, $event->id, null, $asistencia->toArray());

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

    public function asistencia(Evento $event, SesionCongreso $sesion): JsonResponse
    {
        $this->assertCanWriteEvento($event->id);
        $this->assertSesionPerteneceAEvento($event, $sesion);

        $asistencias = AsistenciaSesion::with(['participante', 'staff'])
            ->where('sesion_congreso_id', $sesion->id)
            ->orderByDesc('checkin_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $asistencias,
        ]);
    }

    /**
     * Reporte de concurrencia por sesión — el denominador es el total de
     * participantes PAGADOS del evento (no hay pre-inscripción por
     * sesión en esta ronda para usar como base más precisa), así que el
     * % mide "de todos los que pagaron, cuántos pasaron por esta sesión"
     * — comparable entre sesiones del mismo evento, no un % de aforo real
     * de la sesión salvo que se compare también contra `cupo`.
     */
    public function reporte(Evento $event): JsonResponse
    {
        $this->assertCanWriteEvento($event->id);

        $totalPagados = Participante::whereHas('registration', fn ($q) => $q->where('evento_id', $event->id)->where('pago_status', 'paid'))->count();

        $sesiones = SesionCongreso::where('evento_id', $event->id)
            ->orderBy('fecha')->orderBy('hora_inicio')
            ->withCount('asistencias')
            ->get()
            ->map(function (SesionCongreso $sesion) use ($totalPagados) {
                return [
                    'id' => $sesion->id,
                    'titulo' => $sesion->titulo,
                    'sala' => $sesion->sala,
                    'fecha' => $sesion->fecha->toDateString(),
                    'cupo' => $sesion->cupo,
                    'asistieron' => $sesion->asistencias_count,
                    'porcentajeConcurrencia' => $totalPagados > 0
                        ? round($sesion->asistencias_count / $totalPagados * 100, 1)
                        : 0.0,
                ];
            });

        return response()->json([
            'success' => true,
            'totalParticipantesPagados' => $totalPagados,
            'data' => $sesiones,
        ]);
    }

    private function assertSesionPerteneceAEvento(Evento $event, SesionCongreso $sesion): void
    {
        abort_if($sesion->evento_id !== $event->id, 404);
    }

    private function cupoLleno(SesionCongreso $sesion): bool
    {
        if ($sesion->cupo === null) {
            return false;
        }

        return AsistenciaSesion::where('sesion_congreso_id', $sesion->id)->count() >= $sesion->cupo;
    }

    /**
     * Valida que el participante pertenezca al mismo evento de la sesión
     * y que su inscripción esté pagada — devuelve null si está todo bien,
     * o ['motivo' => string, 'status' => int] si hay que rechazarlo.
     */
    private function validarParticipanteParaSesion(Evento $event, SesionCongreso $sesion, Participante $participante): ?array
    {
        $participante->loadMissing('registration');

        if (!$participante->registration || (int) $participante->registration->evento_id !== $event->id) {
            return ['motivo' => 'Este participante no pertenece a este evento.', 'status' => 404];
        }

        if ($participante->registration->pago_status !== 'paid') {
            return ['motivo' => 'No se puede acreditar: el pago no está confirmado.', 'status' => 422];
        }

        return null;
    }
}
