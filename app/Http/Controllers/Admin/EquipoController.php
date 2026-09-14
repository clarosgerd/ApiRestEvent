<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\EquipoController as ApiEquipoController;
use App\Models\Equipo;
use App\Models\Evento;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Catálogo de equipos por evento (01/09/2026, sincronizado al monolito
 * 14/09/2026) — mismo patrón de delegación que Admin\AuspiciadorController.
 * store() acepta un textarea de un nombre por línea, convertido acá al
 * array que espera EquipoController::store() de la API (que ya existía
 * como endpoint bulk, antes solo alcanzable pegándole directo).
 */
class EquipoController extends Controller
{
    public function store(Request $request, Evento $event, ApiEquipoController $api): RedirectResponse
    {
        $nombres = collect(preg_split('/\r\n|\r|\n/', (string) $request->input('nombres')))
            ->map(fn ($n) => trim($n))
            ->filter()
            ->values()
            ->all();

        if (empty($nombres)) {
            return redirect(route('admin.eventos.edit', $event) . '#equipos')
                ->withErrors(['general' => 'Escribí al menos un nombre de equipo, uno por línea.']);
        }

        $bulkRequest = Request::create('', 'POST', ['equipos' => $nombres]);
        $payload = $api->store($bulkRequest, $event)->getData(true);

        if (!($payload['success'] ?? false)) {
            return redirect(route('admin.eventos.edit', $event) . '#equipos')->withErrors($this->extractErrors($payload));
        }

        return redirect(route('admin.eventos.edit', $event) . '#equipos')->with('status', 'Equipos agregados correctamente.');
    }

    public function update(Request $request, Equipo $equipo, ApiEquipoController $api): RedirectResponse
    {
        $eventoId = $request->input('evento_id');
        $payload = $api->update($request, $equipo)->getData(true);

        if (!($payload['success'] ?? false)) {
            return redirect(route('admin.eventos.edit', $eventoId) . '#equipos')->withErrors($this->extractErrors($payload));
        }

        return redirect(route('admin.eventos.edit', $eventoId) . '#equipos')->with('status', 'Equipo actualizado correctamente.');
    }

    public function destroy(Request $request, Equipo $equipo, ApiEquipoController $api): RedirectResponse
    {
        $eventoId = $request->input('evento_id');
        $payload = $api->destroy($equipo)->getData(true);

        if (!($payload['success'] ?? false)) {
            return redirect(route('admin.eventos.edit', $eventoId) . '#equipos')->withErrors($this->extractErrors($payload));
        }

        return redirect(route('admin.eventos.edit', $eventoId) . '#equipos')->with('status', 'Equipo eliminado correctamente.');
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
