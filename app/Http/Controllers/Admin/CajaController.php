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
use Illuminate\Http\Response;
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
    public function cobrarPendiente(Request $request, Evento $event, string $reference, ApiCajaController $api): JsonResponse
    {
        return $api->cobrarPendiente($request, $reference);
    }

    /**
     * Historia de movimientos de caja de una inscripción (02/10/2026) —
     * para elegir cuál anular, ver caja/buscar.blade.php.
     */
    public function movimientos(Request $request, Evento $event, string $reference, ApiCajaController $api): JsonResponse
    {
        return $api->movimientos($request, $reference);
    }

    /**
     * Anular un cobro ya registrado (02/10/2026) — ver AnularCobroAction
     * (ApiRestEvent). Mismo patrón mergeAndValidate() que storeNueva/
     * storeEditar, FormRequest type-hinteado en la firma del método API.
     */
    public function anularCobro(Request $request, Evento $event, string $reference, ApiCajaController $api, \App\Actions\AnularCobroAction $action): JsonResponse
    {
        $validated = $this->mergeAndValidate(\App\Http\Requests\AnularCobroRequest::class, $request, []);

        return $api->anularCobro($validated, $reference, $action);
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

        // Detalle de cierre de caja (27/08/2026) — el filtro de cajero
        // necesita la lista de cajeros que tuvieron turnos en este evento
        // (no existe un endpoint de "admins del evento" reusable acá). Se
        // deriva de una consulta SIN el filtro de cajero (respetando
        // fecha, si hay), no de la lista ya filtrada — si no, elegir un
        // cajero haría desaparecer del <select> a los demás.
        if ($request->filled('cajero')) {
            $sinCajero = new Request(array_filter([
                'desde' => $request->input('desde'),
                'hasta' => $request->input('hasta'),
            ]));
            $todosLosTurnos = $apiTurno->index($sinCajero, $event)->getData(true)['turnos'] ?? [];
        } else {
            $todosLosTurnos = $payload['turnos'] ?? [];
        }
        $cajeros = collect($todosLosTurnos)
            ->unique('cajeroId')
            ->map(fn ($t) => ['id' => $t['cajeroId'], 'nombre' => $t['cajeroNombre'] ?? ('#'.$t['cajeroId'])])
            ->sortBy('nombre')
            ->values();

        return view('admin.caja.cierres', [
            'evento' => $this->eventoData($event, $apiEvento),
            'turnos' => $payload['turnos'] ?? [],
            'cajeros' => $cajeros,
            'error'  => (!($payload['success'] ?? false)) ? ($payload['error'] ?? 'No se pudo conectar con el servidor.') : null,
        ]);
    }

    /**
     * Detalle de un turno (27/08/2026) — drill-down pedido por el usuario
     * desde el reporte de cierres, mismo patrón que editar()/eticket().
     */
    public function cierreDetalle(Evento $event, CajaTurno $turno, ApiEventoController $apiEvento, ApiCajaTurnoController $apiTurno): View
    {
        $payload = $apiTurno->show($event, $turno)->getData(true);

        abort_if(!($payload['success'] ?? false), 404, $payload['error'] ?? 'No se pudo cargar el detalle del turno.');

        return view('admin.caja.cierre-detalle', [
            'evento' => $this->eventoData($event, $apiEvento),
            'turno'  => $payload['turno'],
        ]);
    }

    /**
     * CSV del detalle de un turno (08/10/2026) — pedido real del usuario,
     * junto con "Imprimir": una fila por movimiento, mismos datos que la
     * tabla de admin/caja/cierre-detalle.blade.php (incluye motivo/
     * observaciones). Reusa el mismo endpoint que cierreDetalle(), sin
     * volver a calcular nada.
     */
    public function cierreCsv(Evento $event, CajaTurno $turno, ApiEventoController $apiEvento, ApiCajaTurnoController $apiTurno): Response
    {
        $payload = $apiTurno->show($event, $turno)->getData(true);

        abort_if(!($payload['success'] ?? false), 404, $payload['error'] ?? 'No se pudo generar el archivo.');

        $eventoData = $this->eventoData($event, $apiEvento);
        $t = $payload['turno'];

        $tipoLabels = [
            'inscripcion_nueva' => 'Inscripción nueva',
            'cobro_pendiente'   => 'Cobro pendiente',
            'edicion_pagada'    => 'Edición pagada',
            'anulacion'         => 'Anulación',
        ];

        $handle = fopen('php://temp', 'w+');
        fwrite($handle, "\xEF\xBB\xBF");

        fputcsv($handle, ['Evento', $eventoData['name'] ?? '']);
        fputcsv($handle, ['Cajero', $t['cajeroNombre'] ?? ('#'.$t['cajeroId'])]);
        fputcsv($handle, ['Abierto', $t['abiertoAt'] ?? '']);
        fputcsv($handle, ['Cerrado', $t['cerradoAt'] ?? '']);
        fputcsv($handle, ['Fondo inicial', $t['fondoInicial'] ?? 0]);
        fputcsv($handle, ['Esperado', $t['montoEsperado'] ?? '']);
        fputcsv($handle, ['Contado', $t['montoContado'] ?? '']);
        fputcsv($handle, ['Diferencia', $t['diferencia'] ?? '']);
        fputcsv($handle, ['Efectivo', $t['totalEfectivo'] ?? 0]);
        fputcsv($handle, ['QR', $t['totalQr'] ?? 0]);
        fputcsv($handle, ['Depósito', $t['totalDeposito'] ?? 0]);
        fputcsv($handle, ['Organizador', $t['totalOrganizador'] ?? 0]);
        fputcsv($handle, ['Cortesía', $t['totalCortesia'] ?? 0]);
        fputcsv($handle, []);

        fputcsv($handle, ['fecha_hora', 'tipo', 'referencia', 'metodo_pago', 'monto', 'motivo', 'observaciones']);
        foreach ($t['movimientos'] ?? [] as $m) {
            fputcsv($handle, [
                $m['createdAt'] ?? '',
                $tipoLabels[$m['tipo']] ?? $m['tipo'],
                $m['registrationReferencia'] ?? '',
                $m['metodoPago'] ?? '',
                $m['monto'] ?? 0,
                $m['motivo'] ?? '',
                $m['observaciones'] ?? '',
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"cierre-caja-turno-{$turno->id}.csv\"",
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
