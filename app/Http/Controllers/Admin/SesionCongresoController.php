<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\DelegatesToApiJson;
use App\Http\Controllers\Controller;
use App\Http\Controllers\EventoController as ApiEventoController;
use App\Http\Controllers\SesionCongresoController as ApiSesionCongresoController;
use App\Http\Controllers\SesionCongresoStaffController as ApiSesionCongresoStaffController;
use App\Http\Controllers\TallerCongresoController as ApiTallerCongresoController;
use App\Http\Requests\StoreSesionCongresoRequest;
use App\Http\Requests\UpdateSesionCongresoRequest;
use App\Models\Evento;
use App\Models\Participante;
use App\Models\SesionCongreso;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Consolidación monolito (21/08/2026), Fase 1e-ii — Agenda y sesiones de
 * congreso (config estructural) + vinculación de staff/ponentes. Portado
 * 1:1 de admin-eventos, delegando en SesionCongresoController /
 * SesionCongresoStaffController de la API. Ver
 * ApiRestEvent/brain/api_rest_event/PLAN-CONSOLIDACION-MONOLITO-21082026.md.
 */
class SesionCongresoController extends Controller
{
    use DelegatesToApiJson;

    public function index(
        Evento $event,
        ApiEventoController $apiEvento,
        ApiSesionCongresoController $apiSesion,
        ApiTallerCongresoController $apiTaller,
        ApiSesionCongresoStaffController $apiStaff,
        Request $request
    ): View {
        $this->assertCanViewEvento($event->id);

        $eventoData = $apiEvento->show($event)->getData(true)['eventos'] ?? null;
        abort_if(!$eventoData, 404);

        $sesiones = $this->dataFrom($apiSesion->index($event));
        $talleres = $this->dataFrom($apiTaller->index($event));
        $staffDisponible = $this->dataFrom($apiStaff->disponibles($request, $event));

        // Ponentes disponibles — mismo endpoint que staff, filtrado por
        // rol (ver SesionCongresoStaffController::disponibles() en la API).
        $ponentesRequest = Request::create('', 'GET', ['rol' => 'ponente']);
        $ponentesDisponibles = $this->dataFrom($apiStaff->disponibles($ponentesRequest, $event));

        return view('admin.eventos.sesiones.index', [
            'evento' => $eventoData,
            'sesiones' => $sesiones,
            'talleres' => $talleres,
            'staffDisponible' => $staffDisponible,
            'ponentesDisponibles' => $ponentesDisponibles,
        ]);
    }

    public function store(StoreSesionCongresoRequest $request, Evento $event, ApiSesionCongresoController $api): RedirectResponse
    {
        $this->assertCanViewEvento($event->id);

        return $this->redirectFromApiResponse($api->store($request, $event), 'admin.sesiones.index', [$event->id]);
    }

    public function update(UpdateSesionCongresoRequest $request, Evento $event, SesionCongreso $sesion, ApiSesionCongresoController $api): RedirectResponse
    {
        $this->assertCanViewEvento($event->id);

        return $this->redirectFromApiResponse($api->update($request, $event, $sesion), 'admin.sesiones.index', [$event->id]);
    }

    /**
     * Vinculación de staff/ayudantes o ponentes a sesiones — `rol` viaja en
     * el body (`staff` por default, ver SesionCongresoStaffController::store()).
     */
    public function assignStaff(Request $request, Evento $event, SesionCongreso $sesion, ApiSesionCongresoStaffController $api): RedirectResponse
    {
        $this->assertCanViewEvento($event->id);

        $payload = $api->store($request, $event, $sesion)->getData(true);

        if (!($payload['success'] ?? false)) {
            return back()->withErrors($this->extractErrorsPublic($payload));
        }

        $rol = $request->input('rol', 'staff');
        $mensaje = $rol === 'ponente' ? 'Ponente vinculado correctamente.' : 'Ayudante asignado correctamente.';

        return redirect()->route('admin.sesiones.index', $event->id)->with('status', $mensaje);
    }

    public function unassignStaff(Request $request, Evento $event, SesionCongreso $sesion, Participante $participante, ApiSesionCongresoStaffController $api): RedirectResponse
    {
        $this->assertCanViewEvento($event->id);

        $payload = $api->destroy($request, $event, $sesion, $participante)->getData(true);

        if (!($payload['success'] ?? false)) {
            return back()->withErrors($this->extractErrorsPublic($payload));
        }

        $rol = $request->input('rol', 'staff');
        $mensaje = $rol === 'ponente' ? 'Ponente desvinculado.' : 'Ayudante desasignado.';

        return redirect()->route('admin.sesiones.index', $event->id)->with('status', $mensaje);
    }

    public function destroy(Evento $event, SesionCongreso $sesion, ApiSesionCongresoController $api): RedirectResponse
    {
        $this->assertCanViewEvento($event->id);

        return $this->redirectFromApiResponse($api->destroy($event, $sesion), 'admin.sesiones.index', [$event->id]);
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

    /** Igual que DelegatesToApiJson::extractErrors(), ver EventoController. */
    private function extractErrorsPublic(array $payload): array
    {
        $errors = $payload['errors'] ?? null;
        if (is_array($errors)) {
            return array_map(fn ($messages) => is_array($messages) ? implode(' ', $messages) : $messages, $errors);
        }

        return ['general' => $payload['error'] ?? $payload['message'] ?? 'Ocurrió un error.'];
    }
}
