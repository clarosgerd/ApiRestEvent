<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\EventoController as ApiEventoController;
use App\Http\Controllers\ParticipanteController as ApiParticipanteController;
use App\Http\Requests\UpdateParticipanteRequest;
use App\Models\Evento;
use App\Models\Participante;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Consolidación monolito (21/08/2026), Fase 1e-i — edición restringida de
 * datos de contacto/identidad de un participante (whitelist real la
 * aplica UpdateParticipanteRequest de la API, acá no se reimplementa
 * nada). Ver ApiRestEvent/brain/api_rest_event/PLAN-CONSOLIDACION-MONOLITO-21082026.md.
 */
class ParticipantesController extends Controller
{
    public function index(Request $request, Evento $event, ApiEventoController $apiEvento, ApiParticipanteController $apiParticipante): View
    {
        $this->assertCanViewEvento($event->id);

        $eventoData = $apiEvento->show($event)->getData(true)['eventos'] ?? null;
        abort_if(!$eventoData, 404);

        $categoria = $request->query('categoria', '');
        $request->merge(array_filter(['categoria' => $categoria !== '' ? $categoria : null]));

        $payload = $apiParticipante->porEvento($request, $event)->getData(true);
        abort_if(!($payload['success'] ?? false), 502, 'No se pudo cargar la lista de participantes.');

        return view('admin.eventos.participantes', [
            'evento' => $eventoData,
            'categoriaSeleccionada' => $categoria,
            'participantes' => $payload['participantes'] ?? [],
        ]);
    }

    public function update(UpdateParticipanteRequest $request, Evento $event, Participante $participante, ApiParticipanteController $api): RedirectResponse
    {
        $this->assertCanViewEvento($event->id);

        $payload = $api->update($request, $participante)->getData(true);

        if (!($payload['success'] ?? false)) {
            return back()->withErrors($this->extractErrors($payload));
        }

        return redirect()->route('admin.participantes.index', ['event' => $event->id, 'categoria' => $request->input('categoria') ?: null])
            ->with('status', 'Participante actualizado correctamente.');
    }

    /**
     * Mismo criterio que Admin\EventoController::assertCanViewEvento.
     */
    private function assertCanViewEvento(int $evento): void
    {
        $admin = session('admin_user');

        if (($admin['rol'] ?? null) !== 'super_admin' && (int) ($admin['evento_id'] ?? 0) !== $evento) {
            abort(403, 'No tiene acceso a este evento.');
        }
    }

    private function extractErrors(array $payload): array
    {
        $errors = $payload['errors'] ?? null;
        if (is_array($errors)) {
            return array_map(fn ($messages) => is_array($messages) ? implode(' ', $messages) : $messages, $errors);
        }

        return ['general' => $payload['error'] ?? $payload['message'] ?? 'Ocurrió un error.'];
    }
}
