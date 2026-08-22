<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\EventoController as ApiEventoController;
use App\Http\Controllers\ParticipanteController as ApiParticipanteController;
use App\Models\Evento;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Consolidación monolito (21/08/2026), Fase 1e-i — reporte detallado de
 * inscritos (solo lectura), fila por fila con paginación opt-in. Portado
 * 1:1 de admin-eventos, delegando en el mismo
 * ParticipanteController::porEvento() de la API que ya usan
 * ParticipantesController/NumeracionController. Ver
 * ApiRestEvent/brain/api_rest_event/PLAN-CONSOLIDACION-MONOLITO-21082026.md.
 */
class ParticipantesDetalleController extends Controller
{
    private const PER_PAGE_DEFAULT = 50;
    private const PER_PAGE_MAX = 200;

    public function index(Request $request, Evento $event, ApiEventoController $apiEvento, ApiParticipanteController $apiParticipante): View
    {
        $this->assertCanViewEvento($event->id);

        $eventoData = $apiEvento->show($event)->getData(true)['eventos'] ?? null;
        abort_if(!$eventoData, 404);

        [$categoria, $pagoStatus, $perPage, $page] = $this->filtrosDesde($request);

        $request->merge(array_filter([
            'categoria' => $categoria !== '' ? $categoria : null,
            'pago_status' => $pagoStatus !== '' ? $pagoStatus : null,
            'per_page' => $perPage,
            'page' => $page,
        ]));

        $payload = $apiParticipante->porEvento($request, $event)->getData(true);
        abort_if(!($payload['success'] ?? false), 502, 'No se pudo cargar el detalle de inscritos.');

        return view('admin.eventos.participantes-detalle', [
            'evento' => $eventoData,
            'categoriaSeleccionada' => $categoria,
            'pagoStatusSeleccionado' => $pagoStatus,
            'participantes' => $payload['participantes'] ?? [],
            'meta' => $payload['meta'] ?? null,
        ]);
    }

    public function csvDownload(Request $request, Evento $event, ApiEventoController $apiEvento, ApiParticipanteController $apiParticipante): Response
    {
        $this->assertCanViewEvento($event->id);

        [$categoria, $pagoStatus] = $this->filtrosDesde($request);

        // Igual que Admin\NumeracionController::csvDownload — `categoria`
        // viaja como ID, se resuelve el nombre acá solo para que la
        // columna del CSV sea legible.
        $eventoData = $apiEvento->show($event)->getData(true)['eventos'] ?? [];
        $categoriasPorId = collect($eventoData['categories'] ?? [])->keyBy(fn ($c) => (string) $c['id']);

        // Sin per_page a propósito: la descarga CSV es una acción
        // explícita, no la carga de pantalla por defecto.
        $request->merge(array_filter([
            'categoria' => $categoria !== '' ? $categoria : null,
            'pago_status' => $pagoStatus !== '' ? $pagoStatus : null,
        ]));
        $payload = $apiParticipante->porEvento($request, $event)->getData(true);
        abort_if(!($payload['success'] ?? false), 502, 'No se pudo generar el archivo.');

        $participantes = $payload['participantes'] ?? [];

        $handle = fopen('php://temp', 'w+');
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, [
            'numero_corredor', 'estado', 'importe', 'importe_taller', 'importe_total', 'numero_documento', 'nombre', 'apellido',
            'sexo', 'celular', 'fecha_inscripcion', 'referencia', 'nacimiento', 'distancia',
        ]);
        foreach ($participantes as $p) {
            fputcsv($handle, [
                $p['numeroCorredor'], $this->estadoLabel($p['pagoStatus']), $p['importe'],
                $p['importeTaller'] ?? 0, $p['importeTotal'] ?? $p['importe'],
                $p['numeroDocumento'], $p['nombre'], $p['apellido'], $p['genero'], $p['telefono'],
                $p['fechaInscripcion'], $p['referencia'], $p['fechaNacimiento'],
                $categoriasPorId[$p['categoria']]['name'] ?? $p['categoria'],
            ]);
        }
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        $filename = 'detalle-inscritos-evento-'.$event->id.($pagoStatus !== '' ? '-'.$pagoStatus : '').'.csv';

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    private function filtrosDesde(Request $request): array
    {
        $categoria = $request->query('categoria', '');
        $pagoStatus = $request->query('pago_status', '');
        $perPage = min((int) $request->query('per_page', self::PER_PAGE_DEFAULT), self::PER_PAGE_MAX);
        $page = max((int) $request->query('page', 1), 1);

        return [$categoria, $pagoStatus, $perPage, $page];
    }

    private function estadoLabel(string $pagoStatus): string
    {
        return match ($pagoStatus) {
            'paid' => 'Pagado',
            'pending' => 'Pendiente',
            'cancelled' => 'Cancelado',
            'failed' => 'Fallido',
            default => $pagoStatus,
        };
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
}
