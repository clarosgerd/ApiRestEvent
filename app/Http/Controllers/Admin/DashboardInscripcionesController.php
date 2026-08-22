<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\EventoController as ApiEventoController;
use App\Models\Evento;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Consolidación monolito (21/08/2026), Fase 1e-i — dashboard de
 * inscripciones dentro del panel. Delega en
 * EventoController::dashboardInscripciones() de la API (el mismo dato que
 * ya se le manda por correo al organizador vía link firmado). Ver
 * ApiRestEvent/brain/api_rest_event/PLAN-CONSOLIDACION-MONOLITO-21082026.md.
 */
class DashboardInscripcionesController extends Controller
{
    public function show(Evento $event, ApiEventoController $apiEvento): View
    {
        $this->assertCanViewEvento($event->id);

        $eventoData = $apiEvento->show($event)->getData(true)['eventos'] ?? null;
        abort_if(!$eventoData, 404);

        $payload = $apiEvento->dashboardInscripciones($event)->getData(true);
        abort_if(!($payload['success'] ?? false), 502, 'No se pudo cargar el dashboard de inscripciones.');

        return view('admin.eventos.dashboard-inscripciones', [
            'evento' => $eventoData,
            'totalGeneral' => $payload['totalGeneral'] ?? null,
            'porCategoria' => $payload['porCategoria'] ?? null,
            'nombresCategorias' => $payload['nombresCategorias'] ?? null,
            'porFormulario' => $payload['porFormulario'] ?? null,
            'nombresFormTypes' => $payload['nombresFormTypes'] ?? null,
            'estados' => $payload['estados'] ?? null,
            'balance' => $payload['balance'] ?? null,
            'reporteInscritos' => $payload['reporteInscritos'] ?? null,
        ]);
    }

    /**
     * CSV del "Reporte de talleres" (20/08/2026) — reusa
     * `reporteInscritos.porTaller.detalle`, ya armado y ordenado del lado
     * de la API; acá solo se vuelca a CSV, sin recalcular nada.
     */
    public function csvTalleres(Evento $event, ApiEventoController $apiEvento): Response
    {
        $this->assertCanViewEvento($event->id);

        $payload = $apiEvento->dashboardInscripciones($event)->getData(true);
        abort_if(!($payload['success'] ?? false), 502, 'No se pudo generar el archivo.');

        $filas = $payload['reporteInscritos']['porTaller']['detalle'] ?? [];

        $handle = fopen('php://temp', 'w+');
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, [
            'fecha', 'hora_inicio', 'hora_fin', 'sala', 'taller', 'sesion',
            'nombre', 'apellido', 'numero_documento', 'correo', 'telefono', 'referencia', 'precio',
        ]);
        foreach ($filas as $fila) {
            fputcsv($handle, [
                $fila['fecha'], $fila['horaInicio'], $fila['horaFin'], $fila['sala'],
                $fila['tallerNombre'], $fila['sesionTitulo'],
                $fila['participanteNombre'], $fila['participanteApellido'], $fila['numeroDocumento'],
                $fila['correo'], $fila['telefono'], $fila['referencia'], $fila['precio'],
            ]);
        }
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"reporte-talleres-evento-{$event->id}.csv\"",
        ]);
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
