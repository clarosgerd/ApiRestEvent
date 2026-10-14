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
            // 'categoria' (07/10/2026) — deja de ser obligatoria: un tipo de
            // formulario sin categoría (Staff, Ponente, o cualquier otro
            // armado así desde "Tipo de formulario" → requiere categoría,
            // ej. "GAFETES STANDS") no tiene nada que elegir acá; la vista
            // oculta el <select> en ese caso (ver registro-manual.blade.php).
            // ApiRestEvent valida que si el tipo SÍ requiere categoría, esta
            // llegue igual (ver RegistrationController::importarBulk()).
            'categoria' => ['nullable', 'string'],
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
        // Errores de lectura (06/10/2026) — antes, una sola celda con
        // caracteres que no son UTF-8 (típico al exportar un CSV desde
        // Excel en español como "CSV" en vez de "CSV UTF-8") rompía el
        // armado del payload, tumbando el archivo COMPLETO con un error
        // genérico antes de que se creara una sola inscripción. Ahora se
        // intenta recuperar el valor (Windows-1252 es la codificación casi
        // segura en ese caso); si no se puede, esa fila queda en
        // $erroresLectura y el resto del archivo se sigue procesando —
        // mismo criterio que ApiRestEvent ya aplica fila por fila (ver
        // reglasFilaCarga()).
        $erroresLectura = [];
        $filasReales = [];
        $fila = 1; // la fila 1 es el encabezado, ya consumido arriba
        while (($row = fgetcsv($handle)) !== false) {
            $fila++;
            if (count(array_filter($row, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue; // fila completamente vacía, se ignora
            }
            $item = [];
            $columnaInvalida = null;
            foreach (self::COLUMNAS as $columna) {
                $valor = trim((string) ($row[$indices[$columna]] ?? ''));
                if ($valor !== '' && !mb_check_encoding($valor, 'UTF-8')) {
                    $convertido = mb_convert_encoding($valor, 'UTF-8', 'Windows-1252');
                    if (mb_check_encoding($convertido, 'UTF-8')) {
                        $valor = $convertido;
                    } else {
                        $columnaInvalida = $columna;
                        break;
                    }
                }
                $item[$columna] = $valor;
            }
            if ($columnaInvalida !== null) {
                $erroresLectura[] = [
                    'fila' => $fila,
                    'numero_documento' => $item['numero_documento'] ?? null,
                    'error' => "La columna \"{$columnaInvalida}\" tiene caracteres con una codificación que no se pudo leer. Volvé a guardar el archivo como CSV UTF-8.",
                ];
                continue;
            }
            // La fila real del CSV, guardada por posición en $participantes
            // (no el índice del array: cuando alguna fila se omite más
            // arriba por error de lectura, la posición y la fila real del
            // archivo dejan de coincidir) — se usa abajo para corregir los
            // números de fila que devuelve ApiRestEvent, que los calcula
            // sobre el array que le llega, ya sin las filas omitidas acá.
            $filasReales[] = $fila;
            $participantes[] = $item;
        }
        fclose($handle);

        if (empty($participantes)) {
            if (!empty($erroresLectura)) {
                return redirect()->route('admin.registro-manual.index', $event->id)
                    ->with('status', '0 inscripción(es) creada(s). ' . count($erroresLectura) . ' fila(s) con error — ver detalle abajo.')
                    ->with('registroManualReporte', ['creados' => [], 'errores' => $erroresLectura]);
            }

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

        // ApiRestEvent numera 'fila' por la posición dentro del array que le
        // llegó (índice + 2) — se traduce de vuelta a la fila real del CSV
        // con $filasReales, guardado arriba en el mismo orden en que se
        // armó $participantes.
        $remapFila = function (array $entry) use ($filasReales): array {
            $posicion = ($entry['fila'] ?? 2) - 2;
            $entry['fila'] = $filasReales[$posicion] ?? $entry['fila'];

            return $entry;
        };

        $creados = array_map($remapFila, $payload['creados'] ?? []);
        $errores = array_merge($erroresLectura, array_map($remapFila, $payload['errores'] ?? []));
        usort($errores, fn ($a, $b) => $a['fila'] <=> $b['fila']);

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
