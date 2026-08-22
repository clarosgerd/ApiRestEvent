<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\EventoController as ApiEventoController;
use App\Http\Controllers\ParticipanteController as ApiParticipanteController;
use App\Http\Controllers\RegistrationController as ApiRegistrationController;
use App\Models\Evento;
use App\Models\Participante;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Consolidación monolito (21/08/2026), Fase 1e-ii — Acreditación (check-in)
 * escaneando el QR de referencia. Portado 1:1 de admin-eventos, delegando
 * en RegistrationController::checkinLookup() / ParticipanteController::
 * checkin() de la API. Ver
 * ApiRestEvent/brain/api_rest_event/PLAN-CONSOLIDACION-MONOLITO-21082026.md.
 */
class AcreditacionController extends Controller
{
    public function index(Request $request, Evento $event, ApiEventoController $apiEvento, ApiParticipanteController $apiParticipante): View
    {
        $this->assertCanViewEvento($event->id);

        $eventoData = $apiEvento->show($event)->getData(true)['eventos'] ?? null;
        abort_if(!$eventoData, 404);

        $payload = $apiParticipante->porEvento($request, $event)->getData(true);
        $participantes = $payload['participantes'] ?? [];

        $pagados = array_filter($participantes, fn ($p) => ($p['pagoStatus'] ?? null) === 'paid');
        $totalPagados = count($pagados);
        $totalAcreditados = count(array_filter($pagados, fn ($p) => !empty($p['checkedInAt'])));

        return view('admin.eventos.acreditacion', [
            'evento' => $eventoData,
            'totalPagados' => $totalPagados,
            'totalAcreditados' => $totalAcreditados,
        ]);
    }

    public function lookup(Request $request, Evento $event, ApiRegistrationController $api): JsonResponse
    {
        $this->assertCanViewEvento($event->id);

        $referencia = trim((string) $request->input('referencia'));
        if ($referencia === '') {
            return response()->json(['success' => false, 'error' => 'Falta la referencia.'], 400);
        }

        return $api->checkinLookup($request, $event, $referencia);
    }

    public function checkin(Evento $event, Participante $participante, ApiParticipanteController $api): JsonResponse
    {
        $this->assertCanViewEvento($event->id);

        return $api->checkin($participante);
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
