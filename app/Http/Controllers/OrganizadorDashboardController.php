<?php

namespace App\Http\Controllers;

use App\Models\Evento;
use App\Models\Participante;
use App\Services\RegistrationService;
use App\Support\BalanceEventoData;
use App\Support\DashboardInscripcionesData;
use App\Support\ReporteInscritosData;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\URL;

class OrganizadorDashboardController extends Controller
{
    /**
     * Dashboard de solo lectura para el organizador — accedido vía link
     * firmado (sin login, ver routes/web.php), generado con
     * `php artisan organizador:generar-link {evento}`. El cálculo de
     * conteos vive en DashboardInscripcionesData, reusado también por el
     * endpoint autenticado del panel de administración
     * (EventoController::dashboardInscripciones).
     */
    public function show(Evento $evento)
    {
        return view('organizador.dashboard', array_merge(
            ['evento' => $evento],
            DashboardInscripcionesData::paraEvento($evento),
            [
                'balance' => BalanceEventoData::paraEvento($evento),
                // Inscritos por categoría/distancia con recaudación
                // (03/09/2026, pedido del usuario) — distinto de
                // $porCategoria de arriba (DashboardInscripcionesData: cuenta
                // por estado de pago, sin dinero). Reusa ReporteInscritosData
                // tal cual, mismo cálculo que ya usa el panel autenticado
                // (EventoController::dashboardInscripciones) — solo cuenta
                // `paid`, "Recaudación" es dinero efectivamente cobrado.
                'reporteInscritos' => ReporteInscritosData::paraEvento($evento),
                // Firma cubre solo `evento` (los filtros se ignoran al validar
                // en exportCsv) — la vista arma los links filtrados agregando
                // &categoria=/&form_type_id=/&pago_status= a esta misma URL base.
                'exportBaseUrl' => URL::signedRoute('organizador.dashboard.export', ['evento' => $evento->id]),
            ]
        ));
    }

    /**
     * Descarga CSV del listado de participantes, con filtros opcionales por
     * categoría / tipo de formulario / estado de pago (query string, sin
     * firmar) — la firma solo cubre `evento`, así el mismo link sirve para
     * cualquier combinación de filtros sin generar un link por cada uno.
     */
    public function exportCsv(Evento $evento, Request $request)
    {
        abort_unless(
            $request->hasValidSignatureWhileIgnoring(['categoria', 'form_type_id', 'pago_status']),
            403
        );

        $query = DashboardInscripcionesData::participantesDelEvento($evento);

        if ($request->filled('categoria')) {
            $query->where('categoria', $request->query('categoria'));
        }
        if ($request->filled('form_type_id')) {
            $query->whereHas('registration', fn (Builder $q) => $q->where('form_types_id', $request->query('form_type_id')));
        }
        if ($request->filled('pago_status')) {
            $query->whereHas('registration', fn (Builder $q) => $q->where('pago_status', $request->query('pago_status')));
        }

        $participantes = $query->get();

        // Nombre de categoría en el CSV (02/09/2026) — participantes.categoria
        // guarda el ID de la categoría (ver backfill_participante_categoria_
        // legacy_names_to_id), no el nombre; el CSV lo mostraba crudo ("6",
        // "90028") en vez de "15K"/"35K". Mismo mapeo que ya arma
        // DashboardInscripcionesData::paraEvento() (nombresCategorias) para
        // la tabla "Por categoría" en pantalla. Fallback al valor crudo: un
        // form_type sin categoría real (requiere_categoria=false) guarda su
        // propio nombre en este campo, no un ID — ya es legible tal cual.
        $nombresCategorias = $evento->categories()->pluck('name', 'id');

        return response()->streamDownload(function () use ($participantes, $evento, $nombresCategorias) {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                // 'Monto categoría'/'Monto souvenir' (02/09/2026) — pedido
                // del usuario revisando este mismo CSV: antes solo se veía
                // el nombre de la categoría/souvenirs, sin poder saber
                // cuánto pagó cada participante por cada uno.
                'Nombre', 'Apellido', 'Documento', 'Categoría', 'Monto categoría', 'Tipo de formulario',
                'Talla/Polera', 'Souvenirs', 'Monto souvenir', 'Teléfono', 'Correo', 'Estado de pago', 'Referencia',
                'NumeroCorredor', 'Chip', 'ActualizarNumeracionUrl',
                'MontoPendiente', 'ConfirmarPagoSitioUrl',
            ]);
            foreach ($participantes as $p) {
                // Cobro en sitio (12/08/2026) — ver
                // ApiRestEvent/brain/api_rest_event/PRD-precios-periodos-fechas.md,
                // sección 0: los form_types con requiere_categoria=false
                // pasaron a cobrar precio_base de verdad (antes $0), así que
                // una inscripción pendiente de ese tipo puede llegar al
                // mostrador de retiro en sitio sin haber pagado. Acotado a
                // propósito a ese caso — no es "cobro en efectivo genérico
                // para cualquier pendiente", solo el que este cambio de
                // precio originó. `ConfirmarPagoSitioUrl` viaja vacío para
                // cualquier otro caso (ya pagado, o requiere_categoria=true)
                // — elascenso/delivery decide si mostrar el botón de cobro
                // únicamente en base a si esta columna trae algo.
                $formType = $p->registration->formType;
                $elegibleCobroSitio = $p->registration->pago_status === 'pending'
                    && $formType
                    && ! $formType->requiere_categoria;

                fputcsv($out, [
                    $p->nombre,
                    $p->apellido,
                    trim($p->tipo_documento . ' ' . $p->numero_documento),
                    $nombresCategorias[$p->categoria] ?? $p->categoria,
                    $p->precio_categoria,
                    optional($formType)->name,
                    $p->polera,
                    $p->souvenirParticipante->pluck('nombre')->implode(', '),
                    // number_format explícito: sum() no pasa por el cast
                    // decimal:2 del modelo (a diferencia de precio_categoria
                    // arriba), sale "40" en vez de "40.00" si no se fuerza.
                    number_format((float) $p->souvenirParticipante->sum('precio'), 2, '.', ''),
                    $p->telefono,
                    $p->correo,
                    $p->registration->pago_status,
                    $p->registration->referencia,
                    $p->numero_corredor,
                    $p->chip,
                    // Link firmado por-documento para que elascenso/delivery (POS de
                    // retiro en sitio) pueda cargar numeración de corredor/chip al
                    // momento de la entrega física, cuando el proveedor externo no
                    // llegó a tiempo — ver ParticipanteController::porEvento /
                    // brain/PLAN-RESULTADOS-EQUIPOS-31072026.md §1 para el resto del
                    // flujo de numeración. Usa numero_documento real (no el string
                    // combinado de la columna "Documento") para que delivery no
                    // necesite reconstruir/parsear nada.
                    URL::signedRoute('organizador.dashboard.actualizar-numeracion', [
                        'evento' => $evento->id,
                        'documento' => $p->numero_documento,
                    ]),
                    $elegibleCobroSitio ? optional($p->registration->totals)->grand_total : null,
                    $elegibleCobroSitio ? URL::signedRoute('organizador.dashboard.confirmar-pago-sitio', [
                        'evento' => $evento->id,
                        'documento' => $p->numero_documento,
                    ]) : null,
                ]);
            }
            fclose($out);
        }, 'participantes-evento-' . $evento->id . '.csv', ['Content-Type' => 'text/csv']);
    }

    /**
     * Push-back de numeración de corredor/chip desde el POS de retiro en
     * sitio (elascenso/delivery) — machine-to-machine, la firma cubre
     * evento+documento e ignora numero_corredor/chip (mismo patrón que
     * DeliveryController::updateEstado()). No requiere el id numérico del
     * participante porque delivery, en el flujo de retiro en sitio, solo
     * tiene el numero_documento (ver brain, RetiroSitio no guarda
     * participante_id).
     */
    public function actualizarNumeracionSitio(Request $request, Evento $evento, string $documento): JsonResponse
    {
        abort_unless($request->hasValidSignatureWhileIgnoring(['numero_corredor', 'chip']), 403);

        $participante = Participante::whereHas('registration', fn (Builder $q) => $q->where('evento_id', $evento->id))
            ->where('numero_documento', $documento)
            ->first();
        abort_unless($participante, 404);

        $updates = array_filter([
            'numero_corredor' => $request->query('numero_corredor'),
            'chip'            => $request->query('chip'),
        ], fn ($v) => $v !== null);

        if ($updates) {
            $participante->update($updates);
        }

        return response()->json([
            'success'        => true,
            'numeroCorredor' => $participante->numero_corredor,
            'chip'           => $participante->chip,
        ]);
    }

    /**
     * Cobro en sitio (12/08/2026) — ver
     * PRD-precios-periodos-fechas.md, sección 0. Push-back desde el POS de
     * retiro en sitio (elascenso/delivery) para inscripciones pendientes de
     * un form_type sin categoría (`requiere_categoria=false`), que ahora
     * cobra `precio_base` de verdad en vez de $0 — mismo patrón sin
     * sesión/CSRF que `actualizarNumeracionSitio()`, firma validada a mano.
     *
     * Reusa `RegistrationService::updatePaymentStatus()` a propósito (no un
     * update directo del modelo): es el mismo camino que usa la pasarela
     * real, así que dispara el correo de confirmación
     * (`notificarPagoConfirmado`) igual que cualquier otro pago — el
     * participante no debería notar la diferencia de haber pagado en el
     * mostrador en vez de por QR.
     *
     * Revalida la elegibilidad servidor-side (no confía en que la URL
     * firmada implique que sigue siendo válida — los datos pudieron
     * cambiar desde que se generó el CSV): si ya está `paid` (alguien pagó
     * por QR mientras tanto, o dos clicks en el mostrador), responde éxito
     * igual — es un no-op idempotente, no un error. Si el form_type
     * requiere categoría, rechaza — este camino es solo para el caso que
     * lo originó.
     */
    public function confirmarPagoSitio(Evento $evento, string $documento, RegistrationService $registrationService): JsonResponse
    {
        // Firma validada por el middleware `signed` de la ruta (sin query
        // string que ignorar acá, a diferencia de actualizarNumeracionSitio).
        $participante = Participante::with('registration.formType')
            ->whereHas('registration', fn (Builder $q) => $q->where('evento_id', $evento->id))
            ->where('numero_documento', $documento)
            ->first();
        abort_unless($participante, 404);

        $registration = $participante->registration;

        if ($registration->pago_status === 'paid') {
            return response()->json(['success' => true, 'pagoStatus' => 'paid']);
        }

        if ($registration->pago_status !== 'pending') {
            return response()->json([
                'success' => false,
                'error'   => "Esta inscripción está en estado '{$registration->pago_status}', no se puede confirmar el pago.",
            ], 422);
        }

        if (!$registration->formType || $registration->formType->requiere_categoria) {
            return response()->json([
                'success' => false,
                'error'   => 'Este tipo de inscripción requiere categoría — el cobro en sitio no aplica acá.',
            ], 422);
        }

        $registration = $registrationService->updatePaymentStatus($registration->referencia, 'paid');

        return response()->json(['success' => true, 'pagoStatus' => $registration->pago_status]);
    }
}
