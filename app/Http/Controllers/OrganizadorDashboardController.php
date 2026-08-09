<?php

namespace App\Http\Controllers;

use App\Models\Evento;
use App\Models\Participante;
use App\Support\DashboardInscripcionesData;
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

        return response()->streamDownload(function () use ($participantes, $evento) {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'Nombre', 'Apellido', 'Documento', 'Categoría', 'Tipo de formulario',
                'Talla/Polera', 'Souvenirs', 'Teléfono', 'Correo', 'Estado de pago', 'Referencia',
                'NumeroCorredor', 'Chip', 'ActualizarNumeracionUrl',
            ]);
            foreach ($participantes as $p) {
                fputcsv($out, [
                    $p->nombre,
                    $p->apellido,
                    trim($p->tipo_documento . ' ' . $p->numero_documento),
                    $p->categoria,
                    optional($p->registration->formType)->name,
                    $p->polera,
                    $p->souvenirParticipante->pluck('nombre')->implode(', '),
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
}
