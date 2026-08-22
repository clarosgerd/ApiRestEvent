<?php

namespace App\Http\Controllers\Admin;

use App\Actions\CrearInscripcionAction;
use App\Http\Controllers\Controller;
use App\Http\Controllers\EventoController as ApiEventoController;
use App\Http\Controllers\RegistrationController as ApiRegistrationController;
use App\Models\Evento;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Consolidación monolito (21/08/2026), Fase 1e-v (última de la Fase 1e) —
 * Carga masiva de inscripciones por CSV, solo super_admin. Portado 1:1 de
 * admin-eventos, delegando en RegistrationController::importarBulk() de
 * la API. Incluye el soporte de talleres agregado el mismo día en la
 * rama `questions` — el worktree no lo tenía todavía, se trajo con
 * `git cherry-pick` del commit real antes de portar este controller,
 * para no portar una versión vieja de la feature. Ver
 * ApiRestEvent/brain/api_rest_event/PLAN-CONSOLIDACION-MONOLITO-21082026.md
 * y PLAN-REGISTRO-MANUAL-CSV-05082026.md (feature original).
 */
class RegistroManualController extends Controller
{
    private const COLUMNAS = [
        'numero_documento', 'tipo_documento', 'nombre', 'apellido', 'alias', 'genero',
        'fecha_nacimiento', 'email', 'direccion', 'ciudad', 'telefono',
        'contacto_emergencia_nombre', 'contacto_emergencia_telefono', 'contacto_emergencia_relacion',
        'talleres',
    ];

    public function index(Evento $event, ApiEventoController $apiEvento): View
    {
        $eventoData = $apiEvento->show($event)->getData(true)['eventos'] ?? null;
        abort_if(!$eventoData, 404);

        return view('admin.eventos.registro-manual', ['evento' => $eventoData]);
    }

    /**
     * La plantilla incluye un ejemplo con un taller real del evento (si
     * tiene alguno cargado) para que el nombre a escribir en el CSV quede
     * claro de una.
     */
    public function plantilla(Evento $event, ApiEventoController $apiEvento): Response
    {
        $eventoData = $apiEvento->show($event)->getData(true)['eventos'] ?? [];
        $talleres = $eventoData['talleres'] ?? [];
        $ejemploTaller = $talleres[0]['nombre'] ?? '';

        $handle = fopen('php://temp', 'w+');
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, self::COLUMNAS);
        fputcsv($handle, [
            '1234567', 'DNI', 'Ana', 'Prueba', 'AnaP', 'Femenino',
            '1995-06-15', 'ana@example.com', 'Av. Siempre Viva 123', 'La Paz', '77712345',
            'Juan Prueba', '77798765', 'Padre', $ejemploTaller,
        ]);
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="plantilla-registro-manual.csv"',
        ]);
    }

    public function store(Request $request, Evento $event, ApiRegistrationController $api, CrearInscripcionAction $action): RedirectResponse
    {
        $request->validate([
            'form_types_id' => ['required', 'integer'],
            'categoria' => ['required', 'string'],
            'csv' => ['required', 'file', 'mimes:csv,txt', 'max:4096'],
        ]);

        $handle = fopen($request->file('csv')->getRealPath(), 'r');
        if (!$handle) {
            return back()->withErrors(['general' => 'No se pudo leer el archivo.']);
        }

        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        $header = fgetcsv($handle);
        if (!$header) {
            fclose($handle);
            return back()->withErrors(['general' => 'El archivo está vacío.']);
        }
        $header = array_map(fn ($h) => strtolower(trim($h)), $header);

        $faltantes = array_diff(self::COLUMNAS, $header);
        if (!empty($faltantes)) {
            fclose($handle);
            return back()->withErrors(['general' => 'Al archivo le faltan columnas: ' . implode(', ', $faltantes) . '. Usá la plantilla descargable.']);
        }

        $indices = array_flip($header);
        $participantes = [];
        while (($row = fgetcsv($handle)) !== false) {
            if (count(array_filter($row, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue; // fila completamente vacía, se ignora
            }
            $item = [];
            foreach (self::COLUMNAS as $columna) {
                $item[$columna] = trim((string) ($row[$indices[$columna]] ?? ''));
            }
            $participantes[] = $item;
        }
        fclose($handle);

        if (empty($participantes)) {
            return back()->withErrors(['general' => 'El archivo no tiene filas con datos.']);
        }

        // Llamada in-process — a diferencia del proxy HTTP viejo, acá no
        // hace falta el timeout generoso/sin reintentos que necesitaba
        // ApiRestEventClient para un lote grande (cada fila dispara un
        // correo real y síncrono): no hay red ni reintento automático de
        // por medio, la request original de admin-eventos ya corre hasta
        // que termine.
        $bulkRequest = Request::create('', 'POST', [
            'form_types_id' => (int) $request->input('form_types_id'),
            'categoria' => $request->input('categoria'),
            'participantes' => $participantes,
        ]);
        $payload = $api->importarBulk($bulkRequest, $event, $action)->getData(true);

        if (!($payload['success'] ?? false)) {
            return back()->withErrors($this->extractErrors($payload));
        }

        $creados = $payload['creados'] ?? [];
        $errores = $payload['errores'] ?? [];

        $status = count($creados) . ' inscripción(es) creada(s), pendiente(s) de pago.';
        if (!empty($errores)) {
            $status .= ' ' . count($errores) . ' fila(s) con error — ver detalle abajo.';
        }

        return redirect()->route('admin.registro-manual.index', $event->id)
            ->with('status', $status)
            ->with('registroManualReporte', ['creados' => $creados, 'errores' => $errores]);
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
