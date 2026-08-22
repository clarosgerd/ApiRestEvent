<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\EventoController as ApiEventoController;
use App\Http\Controllers\ParticipanteController as ApiParticipanteController;
use App\Http\Controllers\RegistrationController as ApiRegistrationController;
use App\Models\Evento;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Consolidación monolito (21/08/2026), Fase 1e-ii — Numeración de
 * corredor/chip por evento, edición manual + carga masiva por CSV.
 * Portado 1:1 de admin-eventos, delegando en
 * RegistrationController::updateNumeracion() / ParticipanteController::
 * numeracionBulk()/porEvento() de la API. Ver
 * ApiRestEvent/brain/api_rest_event/PLAN-CONSOLIDACION-MONOLITO-21082026.md.
 */
class NumeracionController extends Controller
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

        return view('admin.eventos.numeracion', [
            'evento' => $eventoData,
            'categoriaSeleccionada' => $categoria,
            'participantes' => $payload['participantes'] ?? [],
        ]);
    }

    public function update(Request $request, string $reference, int $participante, ApiRegistrationController $api): RedirectResponse
    {
        $evento = (int) $request->input('evento_id');
        $this->assertCanViewEvento($evento);

        $payload = $api->updateNumeracion($request, $reference, $participante)->getData(true);

        if (!($payload['success'] ?? false)) {
            return back()->withErrors($this->extractErrors($payload));
        }

        return redirect()->route('admin.numeracion.index', ['event' => $evento, 'categoria' => $request->input('categoria') ?: null])
            ->with('status', 'Numeración actualizada correctamente.');
    }

    public function csvDownload(Request $request, Evento $event, ApiEventoController $apiEvento, ApiParticipanteController $apiParticipante): Response
    {
        $this->assertCanViewEvento($event->id);

        $categoria = $request->query('categoria', '');

        $eventoData = $apiEvento->show($event)->getData(true)['eventos'] ?? [];
        $categoriasPorId = collect($eventoData['categories'] ?? [])->keyBy(fn ($c) => (string) $c['id']);

        $request->merge(array_filter(['categoria' => $categoria !== '' ? $categoria : null]));
        $payload = $apiParticipante->porEvento($request, $event)->getData(true);
        abort_if(!($payload['success'] ?? false), 502, 'No se pudo generar el archivo.');

        $participantes = $payload['participantes'] ?? [];

        $handle = fopen('php://temp', 'w+');
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, ['numero_documento', 'nombre', 'apellido', 'categoria', 'numero_corredor', 'chip']);
        foreach ($participantes as $p) {
            fputcsv($handle, [
                $p['numeroDocumento'], $p['nombre'], $p['apellido'],
                $categoriasPorId[$p['categoria']]['name'] ?? $p['categoria'],
                $p['numeroCorredor'], $p['chip'],
            ]);
        }
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        $categoriaLabel = $categoriasPorId[$categoria]['name'] ?? $categoria;
        $filename = 'numeracion-evento-'.$event->id.($categoria !== '' ? '-'.Str::slug($categoriaLabel) : '').'.csv';

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    public function csvUpload(Request $request, Evento $event, ApiParticipanteController $api): RedirectResponse
    {
        $this->assertCanViewEvento($event->id);

        $request->validate([
            'csv' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ]);

        $categoria = $request->input('categoria', '');
        $redirectBack = fn () => redirect()->route('admin.numeracion.index', ['event' => $event->id, 'categoria' => $categoria ?: null]);

        $handle = fopen($request->file('csv')->getRealPath(), 'r');
        if (!$handle) {
            return $redirectBack()->withErrors(['general' => 'No se pudo leer el archivo.']);
        }

        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        $header = fgetcsv($handle);
        if (!$header) {
            fclose($handle);
            return $redirectBack()->withErrors(['general' => 'El archivo está vacío.']);
        }
        $header = array_map(fn ($h) => strtolower(trim($h)), $header);
        $idxDoc = array_search('numero_documento', $header, true);
        $idxCorredor = array_search('numero_corredor', $header, true);
        $idxChip = array_search('chip', $header, true);

        if ($idxDoc === false) {
            fclose($handle);
            return $redirectBack()->withErrors(['general' => 'El archivo no tiene la columna "numero_documento" — usá el CSV descargado desde acá, no lo reordenes.']);
        }

        $items = [];
        while (($row = fgetcsv($handle)) !== false) {
            $numeroDocumento = trim($row[$idxDoc] ?? '');
            if ($numeroDocumento === '') {
                continue;
            }
            $items[] = [
                'numero_documento' => $numeroDocumento,
                'numero_corredor' => $idxCorredor !== false ? trim((string) ($row[$idxCorredor] ?? '')) ?: null : null,
                'chip' => $idxChip !== false ? trim((string) ($row[$idxChip] ?? '')) ?: null : null,
            ];
        }
        fclose($handle);

        if (empty($items)) {
            return $redirectBack()->withErrors(['general' => 'El archivo no tiene filas con numero_documento.']);
        }

        $bulkRequest = Request::create('', 'POST', ['items' => $items]);
        $payload = $api->numeracionBulk($bulkRequest, $event)->getData(true);

        if (!($payload['success'] ?? false)) {
            return $redirectBack()->withErrors($this->extractErrors($payload));
        }

        $actualizados = $payload['actualizados'] ?? 0;
        $noEncontrados = $payload['no_encontrados'] ?? [];

        $status = "Numeración actualizada: {$actualizados} participante(s).";
        if (!empty($noEncontrados)) {
            $status .= ' No se encontraron '.count($noEncontrados).' número(s) de documento: '.implode(', ', $noEncontrados).'.';
        }

        return $redirectBack()->with('status', $status);
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
