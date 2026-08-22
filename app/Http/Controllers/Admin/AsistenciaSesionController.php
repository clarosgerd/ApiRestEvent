<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\AsistenciaSesionController as ApiAsistenciaSesionController;
use App\Http\Controllers\Controller;
use App\Http\Controllers\EventoController as ApiEventoController;
use App\Http\Controllers\ParticipanteController as ApiParticipanteController;
use App\Http\Controllers\SesionCongresoController as ApiSesionCongresoController;
use App\Models\Evento;
use App\Models\Participante;
use App\Models\SesionCongreso;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Consolidación monolito (21/08/2026), Fase 1e-ii — check-in de staff por
 * sesión de congreso (individual y masivo) + reporte de asistencia.
 * Portado 1:1 de admin-eventos, delegando en AsistenciaSesionController de
 * la API. Mismo criterio que Admin\AcreditacionController — lookup/
 * checkin/checkinBulk son endpoints JSON consumidos por fetch(). Ver
 * ApiRestEvent/brain/api_rest_event/PLAN-CONSOLIDACION-MONOLITO-21082026.md.
 */
class AsistenciaSesionController extends Controller
{
    public function index(
        Evento $event,
        SesionCongreso $sesion,
        ApiEventoController $apiEvento,
        ApiSesionCongresoController $apiSesion,
        ApiParticipanteController $apiParticipante,
        Request $request
    ): View {
        $this->assertCanViewEvento($event->id);

        $eventoData = $apiEvento->show($event)->getData(true)['eventos'] ?? null;
        abort_if(!$eventoData, 404);

        $sesiones = $apiSesion->index($event)->getData(true)['data'] ?? [];
        $sesionData = collect($sesiones)->firstWhere('id', $sesion->id);
        abort_if(!$sesionData, 404);

        $payload = $apiParticipante->porEvento($request, $event)->getData(true);
        $participantesPagados = array_values(array_filter(
            $payload['participantes'] ?? [],
            fn ($p) => ($p['pagoStatus'] ?? null) === 'paid'
        ));

        return view('admin.eventos.sesiones.acreditacion', [
            'evento' => $eventoData,
            'sesion' => $sesionData,
            'participantesPagados' => $participantesPagados,
        ]);
    }

    public function lookup(Request $request, Evento $event, SesionCongreso $sesion, ApiAsistenciaSesionController $api): JsonResponse
    {
        $this->assertCanViewEvento($event->id);

        $referencia = trim((string) $request->input('referencia'));
        if ($referencia === '') {
            return response()->json(['success' => false, 'error' => 'Falta la referencia.'], 400);
        }

        return $api->lookup($event, $sesion, $referencia);
    }

    public function checkin(Evento $event, SesionCongreso $sesion, Participante $participante, ApiAsistenciaSesionController $api): JsonResponse
    {
        $this->assertCanViewEvento($event->id);

        return $api->checkin($event, $sesion, $participante);
    }

    public function checkinBulk(Request $request, Evento $event, SesionCongreso $sesion, ApiAsistenciaSesionController $api): JsonResponse
    {
        $this->assertCanViewEvento($event->id);

        return $api->checkinBulk($request, $event, $sesion);
    }

    public function reporte(Evento $event, ApiEventoController $apiEvento, ApiAsistenciaSesionController $api): View
    {
        $this->assertCanViewEvento($event->id);

        $eventoData = $apiEvento->show($event)->getData(true)['eventos'] ?? null;
        abort_if(!$eventoData, 404);

        $payload = $api->reporte($event)->getData(true);
        abort_if(!($payload['success'] ?? false), 502, 'No se pudo cargar el reporte de asistencia.');

        return view('admin.eventos.sesiones.reporte', [
            'evento' => $eventoData,
            'totalParticipantesPagados' => $payload['totalParticipantesPagados'] ?? 0,
            'sesiones' => $payload['data'] ?? [],
        ]);
    }

    /**
     * Mismo criterio que Admin\NumeracionController::assertCanViewEvento.
     */
    private function assertCanViewEvento(int $evento): void
    {
        $admin = session('admin_user');

        if (($admin['rol'] ?? null) !== 'super_admin' && (int) ($admin['evento_id'] ?? 0) !== $evento) {
            abort(403, 'No tiene acceso a este evento.');
        }
    }
}
