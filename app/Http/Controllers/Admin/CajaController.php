<?php

namespace App\Http\Controllers\Admin;

use App\Actions\ActualizarInscripcionAction;
use App\Actions\ActualizarInscripcionPagadaAction;
use App\Actions\CrearInscripcionAction;
use App\Http\Controllers\Admin\Concerns\DelegatesToApiJson;
use App\Http\Controllers\CajaController as ApiCajaController;
use App\Http\Controllers\CajaTurnoController as ApiCajaTurnoController;
use App\Http\Controllers\Controller;
use App\Http\Controllers\EventoController as ApiEventoController;
use App\Http\Controllers\RegistrationController as ApiRegistrationController;
use App\Http\Requests\AbrirTurnoCajaRequest;
use App\Http\Requests\CerrarTurnoCajaRequest;
use App\Http\Requests\StoreInscripcionCajaRequest;
use App\Http\Requests\UpdatePaidRegistrationRequest;
use App\Http\Requests\UpdateRegistrationRequest;
use App\Models\CajaTurno;
use App\Models\Evento;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Consolidación monolito (21/08/2026), Fase 1d — Caja de cobro presencial.
 * Mismo patrón de delegación que 1a/1b/1c, portado 1:1 de admin-eventos
 * (ninguna validación/cálculo/autorización se reimplementa acá — vive en
 * `App\Http\Controllers\CajaController`/`CajaTurnoController` de la API,
 * que ya usan las mismas Actions que el registro público). Ver
 * ApiRestEvent/brain/api_rest_event/PLAN-CONSOLIDACION-MONOLITO-21082026.md
 * y PLAN-CAJA-COBRO-PRESENCIAL-14082026.md (feature original, 14/08).
 *
 * A diferencia de los controllers de catálogos/evento (que solo traducen
 * `{success, message, data}` a status/redirect), 3 endpoints de Caja son
 * consumidos por `fetch()` desde el navegador (buscar, buscarPersona,
 * cobrarPendiente) — esos devuelven la JsonResponse de la API tal cual,
 * sin traducir a vista/redirect.
 */
class CajaController extends Controller
{
    use DelegatesToApiJson;

    public function index(Evento $event, ApiEventoController $apiEvento, ApiCajaTurnoController $apiTurno): View
    {
        $turno = $apiTurno->actual($event)->getData(true)['turno'] ?? null;

        return view('admin.caja.index', ['evento' => $this->eventoData($event, $apiEvento), 'turno' => $turno]);
    }

    public function abrirTurno(AbrirTurnoCajaRequest $request, Evento $event, ApiCajaTurnoController $api): RedirectResponse
    {
        return $this->redirectFromApiResponse($api->abrir($request, $event), 'admin.caja.index', [$event->id]);
    }

    public function cerrarTurno(CerrarTurnoCajaRequest $request, Evento $event, CajaTurno $turno, ApiCajaTurnoController $api): RedirectResponse
    {
        $payload = $api->cerrar($request, $turno)->getData(true);

        if (!($payload['success'] ?? false)) {
            return back()->withErrors($this->extractErrorsPublic($payload));
        }

        $t = $payload['turno'];
        $status = sprintf(
            'Turno cerrado. Esperado: %s — Contado: %s — Diferencia: %s',
            number_format((float) $t['montoEsperado'], 2),
            number_format((float) $t['montoContado'], 2),
            number_format((float) $t['diferencia'], 2)
        );

        return redirect()->route('admin.caja.index', $event->id)->with('status', $status);
    }

    public function nueva(Evento $event, ApiEventoController $apiEvento): View
    {
        return view('admin.caja.nueva', ['evento' => $this->eventoData($event, $apiEvento)]);
    }

    public function storeNueva(Request $request, Evento $event, ApiCajaController $api, CrearInscripcionAction $action): RedirectResponse
    {
        $validated = $this->mergeAndValidate(StoreInscripcionCajaRequest::class, $request, [
            'form_types_id' => $request->input('form_types_id'),
            'participante'  => json_decode((string) $request->input('participante_json'), true) ?? [],
            'totales'       => json_decode((string) $request->input('totales_json'), true) ?? [],
        ]);

        $payload = $api->inscripcion($validated, $event, $action)->getData(true);

        if (!($payload['success'] ?? false)) {
            return back()->withInput()->withErrors($this->extractErrorsPublic($payload));
        }

        $referencia = $payload['data']['referencia'] ?? null;

        // Comprobante imprimible (20/08/2026) — el cajero necesita algo
        // físico para entregar; mandamos directo al comprobante en vez de
        // a Caja > índice, que era un punto muerto sin acción posible.
        return redirect()->route('admin.caja.eticket', [$event->id, $referencia])
            ->with('status', "Inscripción {$referencia} registrada y cobrada correctamente.");
    }

    public function buscarPage(Evento $event, ApiEventoController $apiEvento): View
    {
        return view('admin.caja.buscar', ['evento' => $this->eventoData($event, $apiEvento)]);
    }

    /**
     * Búsqueda en vivo — llamada por fetch() desde caja/buscar.blade.php.
     */
    public function buscar(Request $request, Evento $event, ApiCajaController $api): JsonResponse
    {
        return $api->buscar($request, $event);
    }

    /**
     * Prellenado desde `personas` — búsqueda en vivo llamada desde
     * caja/_formulario.blade.php al tipear el N° documento en alta nueva.
     */
    public function buscarPersona(Request $request, Evento $event, ApiCajaController $api): JsonResponse
    {
        return $api->buscarPersona($request, $event);
    }

    // Nota: el parámetro se llama $reference (no $referencia) A PROPÓSITO
    // en los 4 métodos de acá abajo — tiene que coincidir con el nombre
    // del route param `{reference}` (ver routes/admin.php) para que
    // Laravel lo bindee, Y con lo que leen
    // UpdateRegistrationRequest/UpdatePaidRegistrationRequest vía
    // `$this->route('reference')` en storeEditar().
    public function cobrarPendiente(Evento $event, string $reference, ApiCajaController $api): JsonResponse
    {
        return $api->cobrarPendiente($reference);
    }

    public function editar(Evento $event, string $reference, ApiEventoController $apiEvento, ApiRegistrationController $apiReg): View
    {
        $registro = $apiReg->show($reference)->getData(true)['data'] ?? null;

        return view('admin.caja.editar', ['evento' => $this->eventoData($event, $apiEvento), 'registro' => $registro, 'referencia' => $reference]);
    }

    /**
     * Comprobante imprimible (20/08/2026) — el cajero lo necesita en mano
     * después de cobrar (o para reimprimir uno viejo desde "Buscar").
     */
    public function eticket(Evento $event, string $reference, ApiEventoController $apiEvento, ApiRegistrationController $apiReg): View
    {
        $registro = $apiReg->show($reference)->getData(true)['data'] ?? null;

        abort_if(!$registro, 404, 'Inscripción no encontrada.');

        return view('admin.caja.eticket', ['evento' => $this->eventoData($event, $apiEvento), 'registro' => $registro, 'referencia' => $reference]);
    }

    public function storeEditar(
        Request $request,
        Evento $event,
        string $reference,
        ApiCajaController $api,
        ActualizarInscripcionAction $pendienteAction,
        ActualizarInscripcionPagadaAction $pagadaAction
    ): RedirectResponse {
        $esPagada = $request->input('pago_status') === 'paid';

        $merge = [
            'participantes' => [json_decode((string) $request->input('participante_json'), true) ?? []],
            'totales'       => json_decode((string) $request->input('totales_json'), true) ?? [],
        ];

        if ($esPagada) {
            $merge['confirmacion'] = true;
            $validated = $this->mergeAndValidate(UpdatePaidRegistrationRequest::class, $request, $merge);
            $payload = $api->editarPagada($validated, $reference, $pagadaAction)->getData(true);
        } else {
            $validated = $this->mergeAndValidate(UpdateRegistrationRequest::class, $request, $merge);
            $payload = $api->editarPendiente($validated, $reference, $pendienteAction)->getData(true);
        }

        if (!($payload['success'] ?? false)) {
            return back()->withInput()->withErrors($this->extractErrorsPublic($payload));
        }

        $status = 'Inscripción actualizada correctamente.';
        if ($esPagada && ($payload['costo_adicion'] ?? 0) > 0) {
            $status .= ' Adicional cobrado: ' . number_format((float) $payload['costo_adicion'], 2) . '.';
        }

        return redirect()->route('admin.caja.index', $event->id)->with('status', $status);
    }

    public function cierres(Request $request, Evento $event, ApiEventoController $apiEvento, ApiCajaTurnoController $apiTurno): View
    {
        // Traduce el nombre del filtro de la pantalla ("cajero") al que
        // espera CajaTurnoController::index() ("admin_user_id") — merge()
        // toca el bag correcto (query, para un GET) sin importar el verbo,
        // a diferencia de replace().
        $request->merge(array_filter([
            'admin_user_id' => $request->input('cajero'),
            'desde'         => $request->input('desde'),
            'hasta'         => $request->input('hasta'),
        ]));

        $payload = $apiTurno->index($request, $event)->getData(true);

        return view('admin.caja.cierres', [
            'evento' => $this->eventoData($event, $apiEvento),
            'turnos' => $payload['turnos'] ?? [],
            'error'  => (!($payload['success'] ?? false)) ? ($payload['error'] ?? 'No se pudo conectar con el servidor.') : null,
        ]);
    }

    private function eventoData(Evento $event, ApiEventoController $apiEvento): array
    {
        return $apiEvento->show($event)->getData(true)['eventos'] ?? [];
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
