<?php

namespace App\Http\Controllers\Admin;

use App\Actions\CrearEventoAction;
use App\Http\Controllers\Admin\Concerns\DelegatesToApiJson;
use App\Http\Controllers\Controller;
use App\Http\Controllers\EventoController as ApiEventoController;
use App\Http\Requests\StoreEventosRequest;
use App\Http\Requests\UpdateEventosRequest;
use App\Models\Evento;
use App\Models\Organizador;
use App\Models\TipoEvento;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Consolidación monolito (21/08/2026), Fase 1c — mismo patrón de
 * delegación que Fases 1a/1b. Portado 1:1 de admin-eventos, incluidos los
 * armados de payload anidado (categories/formTypes/coordinates/route/
 * promoCodes/auspiciadores/agenda) para `store()`, que siguen siendo
 * responsabilidad del panel (la API no reimplementa "descartar filas
 * vacías del formulario"). Ver
 * ApiRestEvent/brain/api_rest_event/PLAN-CONSOLIDACION-MONOLITO-21082026.md.
 */
class EventoController extends Controller
{
    use DelegatesToApiJson;

    public function create(): View
    {
        return view('admin.eventos.create', [
            'tiposEvento'   => $this->tiposEvento(),
            'organizadores' => $this->organizadores(),
        ]);
    }

    private function tiposEvento(): array
    {
        return TipoEvento::where('activo', true)
            ->with(['subtipos' => fn ($q) => $q->where('activo', true)->orderBy('nombre')])
            ->orderBy('nombre')
            ->get()
            ->map(fn (TipoEvento $tipo) => [
                'id' => $tipo->id,
                'nombre' => $tipo->nombre,
                'icono' => $tipo->icono,
                'subtipos' => $tipo->subtipos->map(fn ($sub) => ['id' => $sub->id, 'nombre' => $sub->nombre]),
            ])
            ->toArray();
    }

    /**
     * Solo super_admin llega hasta acá (eventos.create está detrás de
     * admin.superadmin), así que no hace falta replicar el scoping que sí
     * le importaba a admin-eventos (ver OrganizadorController de la API).
     * `nombre` no es una columna real — es el mismo campo calculado que
     * arma `OrganizadorResource` (nombre_comercial si existe, si no
     * razon_social), del cual dependen estas 2 vistas para el <select>.
     */
    private function organizadores(): array
    {
        return Organizador::orderBy('razon_social')->get()
            ->map(fn (Organizador $o) => [
                'id' => $o->id,
                'nombre' => $o->nombre_comercial ?: $o->razon_social,
            ])
            ->toArray();
    }

    /**
     * `StoreEventosRequest` NO se type-hintea directo en la firma —
     * `categories.*.name` es `required` (no `required_with`), así que una
     * fila en blanco del formulario ("+ Agregar categoría" tocado pero sin
     * completar) rompería la validación en vez de descartarse en
     * silencio, que es el comportamiento real de admin-eventos. Este
     * filtrado tiene que pasar ANTES de validar — por eso la validación se
     * dispara a mano acá (`mergeAndValidate`), no automáticamente por DI.
     */
    public function store(Request $request, CrearEventoAction $action, ApiEventoController $api): RedirectResponse
    {
        $categories = collect($request->input('categories', []))
            ->filter(fn ($c) => filled($c['name'] ?? null))
            ->values()->all();

        $formTypes = collect($request->input('formTypes', []))
            ->filter(fn ($ft) => filled($ft['name'] ?? null))
            ->map(function ($ft) {
                $ft['requiere_categoria'] = isset($ft['requiere_categoria']);
                $ft['hasTeam'] = isset($ft['hasTeam']);
                $ft['hasDelivery'] = isset($ft['hasDelivery']);
                $ft['hasDonation'] = isset($ft['hasDonation']);
                $ft['hasPromoCode'] = isset($ft['hasPromoCode']);
                $ft['esStaff'] = isset($ft['esStaff']);
                $ft['esPonente'] = isset($ft['esPonente']);
                $ft['souvenirs'] = collect($ft['souvenirs'] ?? [])
                    ->filter(fn ($s) => filled($s['name'] ?? null))
                    ->values()->all();

                return $ft;
            })
            ->values()->all();

        $coordinates = collect($request->input('coordinates', []))
            ->filter(fn ($c) => filled($c['lat'] ?? null) && filled($c['lng'] ?? null))
            ->values()->all();

        $route = collect($request->input('route', []))
            ->filter(fn ($r) => filled($r['lat'] ?? null) && filled($r['lng'] ?? null))
            ->values()->all();

        $promoCodes = collect($request->input('promoCodes', []))
            ->filter(fn ($p) => filled($p['promo_code'] ?? null))
            ->values()->all();

        $auspiciadores = collect($request->input('auspiciadores', []))
            ->filter(fn ($a) => filled($a['nombre'] ?? null))
            ->values()->all();

        $agenda = collect($request->input('agenda', []))
            ->filter(fn ($a) => filled($a['title'] ?? null))
            ->values()->all();

        $merge = [
            'categories' => $categories, 'formTypes' => $formTypes, 'coordinates' => $coordinates,
            'route' => $route, 'promoCodes' => $promoCodes, 'auspiciadores' => $auspiciadores, 'agenda' => $agenda,
            'aceptaUsd' => $request->boolean('aceptaUsd'),
            'talleresConCosto' => $request->boolean('talleresConCosto'),
            'usdPrecioFijo' => $request->boolean('usdPrecioFijo'),
        ];

        // Mismo motivo que en los catálogos: el <select> de organizador
        // manda "" para "Sin organizador asignado", que no pasa la regla
        // `integer` de StoreEventosRequest si se deja tal cual.
        if ($request->input('organizador_id') === '') {
            $merge['organizador_id'] = null;
        }

        $validated = $this->mergeAndValidate(StoreEventosRequest::class, $request, $merge);

        $payload = $api->store($validated, $action)->getData(true);

        if (!($payload['success'] ?? false)) {
            return back()->withInput()->withErrors($this->extractErrorsPublic($payload));
        }

        return $this->redirectToDashboard('Evento creado como borrador — no es visible para participantes hasta que lo publiques.');
    }

    public function publicar(Evento $event, ApiEventoController $api): RedirectResponse
    {
        $payload = $api->publicar($event)->getData(true);

        if (!($payload['success'] ?? false)) {
            return back()->withErrors(['general' => $payload['error'] ?? $payload['message'] ?? 'No se pudo publicar el evento.']);
        }

        return back()->with('status', 'Evento publicado — se envió el correo al organizador.');
    }

    public function despublicar(Evento $event, ApiEventoController $api): RedirectResponse
    {
        $payload = $api->despublicar($event)->getData(true);

        if (!($payload['success'] ?? false)) {
            return back()->withErrors(['general' => $payload['error'] ?? $payload['message'] ?? 'No se pudo despublicar el evento.']);
        }

        return back()->with('status', 'Evento despublicado correctamente.');
    }

    public function edit(Evento $event, ApiEventoController $api): View
    {
        $this->assertCanViewEvento($event->id);

        $eventoData = $api->show($event)->getData(true)['eventos'] ?? null;

        abort_if(!$eventoData, 404);

        return view('admin.eventos.edit', [
            'evento'        => $eventoData,
            'tiposEvento'   => $this->tiposEvento(),
            'organizadores' => $this->organizadores(),
        ]);
    }

    public function gafetesPdf(Evento $event, ApiEventoController $api): Response
    {
        $this->assertCanViewEvento($event->id);

        return $api->gafetesPdf($event);
    }

    public function certificadosPdf(Evento $event, ApiEventoController $api): Response
    {
        $this->assertCanViewEvento($event->id);

        return $api->certificadosPdf($event);
    }

    private function assertCanViewEvento(int $eventoId): void
    {
        $admin = session('admin_user');

        if (($admin['rol'] ?? null) !== 'super_admin' && (int) ($admin['evento_id'] ?? 0) !== $eventoId) {
            abort(403, 'No tiene acceso a este evento.');
        }
    }

    /**
     * `UpdateEventosRequest` tampoco se type-hintea directo — igual que en
     * store(), `feePctPorcentaje` (humano, "5") tiene que convertirse a
     * `feePct` (fracción, "0.05") ANTES de validar, porque la regla es
     * `numeric|min:0|max:0.20` — un "5" crudo la rechazaría. El parámetro
     * de ruta se llama `$event` (no `$evento`) a propósito: coincide con
     * `{event}` en la API real, y `UpdateEventosRequest::rules()` lee
     * `$this->route('event')` para el `Rule::unique(...)->ignore(...)` de
     * `url_slug` — con otro nombre, `ignore()` no encontraría el evento
     * actual y el evento se rechazaría a sí mismo por "slug duplicado".
     */
    public function update(Request $request, Evento $event, ApiEventoController $api): RedirectResponse
    {
        $merge = [
            'aceptaUsd' => $request->boolean('aceptaUsd'),
            'usdPrecioFijo' => $request->boolean('usdPrecioFijo'),
            'talleresConCosto' => $request->boolean('talleresConCosto'),
        ];

        if ($request->input('organizador_id') === '') {
            $merge['organizador_id'] = null;
        }

        if ($request->filled('feePctPorcentaje')) {
            $merge['feePct'] = round(((float) $request->input('feePctPorcentaje')) / 100, 4);
            $merge['feeIncluyeTalleres'] = $request->boolean('feeIncluyeTalleres');
        }

        $validated = $this->mergeAndValidate(UpdateEventosRequest::class, $request, $merge);

        $payload = $api->update($validated, $event)->getData(true);
        $url = route('admin.eventos.edit', $event).'#datos';

        if (!($payload['success'] ?? false)) {
            return redirect($url)->withInput()->withErrors($this->extractErrorsPublic($payload));
        }

        return redirect($url)->with('status', 'Evento actualizado correctamente.');
    }

    public function destroy(Evento $event, ApiEventoController $api): RedirectResponse
    {
        $payload = $api->destroy($event)->getData(true);

        if (!($payload['success'] ?? false)) {
            return back()->withErrors(['general' => $payload['error'] ?? $payload['message'] ?? 'No se pudo eliminar el evento.']);
        }

        return $this->redirectToDashboard('Evento eliminado correctamente.');
    }

    /**
     * `admin.dashboard` (listado de eventos) todavía no está migrado —
     * Fase 1e. Mismo fallback que `Admin\AuthController::login()`: cae a
     * catálogos en vez de un `RouteNotFoundException`.
     */
    private function redirectToDashboard(string $status): RedirectResponse
    {
        $route = \Illuminate\Support\Facades\Route::has('admin.dashboard') ? 'admin.dashboard' : 'admin.catalogos.index';

        return redirect()->route($route)->with('status', $status);
    }

    /** Igual que DelegatesToApiJson::extractErrors(), pero público-friendly
     * para los 2 casos de arriba que no usan redirectFromApiResponse(). */
    private function extractErrorsPublic(array $payload): array
    {
        $errors = $payload['errors'] ?? null;
        if (is_array($errors)) {
            return array_map(fn ($messages) => is_array($messages) ? implode(' ', $messages) : $messages, $errors);
        }

        return ['general' => $payload['error'] ?? $payload['message'] ?? 'Ocurrió un error.'];
    }
}
