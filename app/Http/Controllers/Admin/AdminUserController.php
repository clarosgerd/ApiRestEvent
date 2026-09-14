<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\DelegatesToApiJson;
use App\Http\Controllers\AdminUserController as ApiAdminUserController;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAdminUserRequest;
use App\Http\Requests\UpdateAdminUserRequest;
use App\Models\AdminUser;
use App\Models\Evento;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Consolidación monolito (21/08/2026), Fase 1b — mismo patrón de
 * delegación que los catálogos de Fase 1a. Ver
 * ApiRestEvent/brain/api_rest_event/PLAN-CONSOLIDACION-MONOLITO-21082026.md.
 */
class AdminUserController extends Controller
{
    use DelegatesToApiJson;

    public function index(ApiAdminUserController $api): View
    {
        $paginado = $this->dataFrom($api->index());
        $usuarios = $paginado['data'] ?? [];

        return view('admin.usuarios.index', compact('usuarios'));
    }

    public function create(): View
    {
        return view('admin.usuarios.form', [
            'usuario' => null,
            'eventos' => $this->listaEventos(),
            'action'  => route('admin.usuarios.store'),
        ]);
    }

    public function store(StoreAdminUserRequest $request, ApiAdminUserController $api): RedirectResponse
    {
        return $this->redirectFromApiResponse($api->store($request), 'admin.usuarios.index');
    }

    public function edit(ApiAdminUserController $api, AdminUser $user): View
    {
        $usuario = $this->dataFrom($api->show($user));

        return view('admin.usuarios.form', [
            'usuario' => $usuario,
            'eventos' => $this->listaEventos(),
            'action'  => route('admin.usuarios.update', $user),
        ]);
    }

    /**
     * Admin de evento asignado a varios eventos (28/08/2026, sincronizado
     * 14/09/2026) — un `<select multiple>` vacío (nadie tildado) no manda
     * la clave `evento_ids_adicionales` en el POST, y
     * `AdminUserController::update()` (API) solo resincroniza la pivote si
     * la clave está presente en `$request->validated()` (`array_key_exists`,
     * a propósito: un array vacío SÍ debe limpiar la pivote). No se puede
     * type-hintear `UpdateAdminUserRequest` directo en la firma (Laravel lo
     * valida antes de que este método pueda inyectar el default) — se usa
     * `mergeAndValidate()` para fusionar el default ANTES de validar,
     * mismo patrón ya establecido en Fase 1c.
     */
    public function update(Request $request, ApiAdminUserController $api, AdminUser $user): RedirectResponse
    {
        $merge = [];
        if ($request->input('rol') === 'admin' && !$request->has('evento_ids_adicionales')) {
            $merge['evento_ids_adicionales'] = [];
        }

        $validated = $this->mergeAndValidate(UpdateAdminUserRequest::class, $request, $merge);

        return $this->redirectFromApiResponse($api->update($validated, $user), 'admin.usuarios.index');
    }

    public function destroy(ApiAdminUserController $api, AdminUser $user): RedirectResponse
    {
        return $this->redirectFromApiResponse($api->destroy($user), 'admin.usuarios.index');
    }

    /**
     * Lista de eventos para el select de evento_id (principal) y el
     * multi-select de evento_ids_adicionales — lectura simple, sin lógica
     * de autorización propia (ya está detrás de `admin.superadmin`), así
     * que no hace falta delegar en un controller de la API para esto. La
     * vista espera la clave `name` (así la expone `EventoResource` del
     * lado de la API) — acá se lee directo el modelo, cuya columna real es
     * `nombre`, de ahí el alias explícito.
     *
     * Bug real de admin-eventos (28/08/2026, "solo muestra 48 registros de
     * eventos"): ahí este selector reusaba `GET /event`, que tiene un tope
     * DURO server-side de 48 por página — con más de 48 eventos reales,
     * los últimos quedaban inalcanzables. Acá NO aplica (consulta directa
     * al modelo, sin ese tope HTTP de por medio) — se saca el `limit(48)`
     * que había quedado de la Fase 1b original (arbitrario, mismo síntoma
     * si el catálogo real supera 48 eventos) para no heredar el mismo bug
     * por otro camino.
     */
    private function listaEventos(): array
    {
        return Evento::orderByDesc('id')->get(['id', 'nombre'])
            ->map(fn (Evento $e) => ['id' => $e->id, 'name' => $e->nombre])
            ->toArray();
    }
}
