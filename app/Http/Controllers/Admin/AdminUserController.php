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

    public function update(UpdateAdminUserRequest $request, ApiAdminUserController $api, AdminUser $user): RedirectResponse
    {
        return $this->redirectFromApiResponse($api->update($request, $user), 'admin.usuarios.index');
    }

    public function destroy(ApiAdminUserController $api, AdminUser $user): RedirectResponse
    {
        return $this->redirectFromApiResponse($api->destroy($user), 'admin.usuarios.index');
    }

    /**
     * Lista de eventos para el select de evento_id — lectura simple, sin
     * lógica de autorización propia (ya está detrás de `admin.superadmin`),
     * así que no hace falta delegar en un controller de la API para esto.
     * La vista espera la clave `name` (así la expone `EventoResource` del
     * lado de la API) — acá se lee directo el modelo, cuya columna real es
     * `nombre`, de ahí el alias explícito.
     */
    private function listaEventos(): array
    {
        return Evento::orderByDesc('id')->limit(48)->get(['id', 'nombre'])
            ->map(fn (Evento $e) => ['id' => $e->id, 'name' => $e->nombre])
            ->toArray();
    }
}
