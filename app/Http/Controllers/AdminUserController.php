<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesEventoScope;
use App\Http\Requests\StoreAdminUserRequest;
use App\Http\Requests\UpdateAdminUserRequest;
use App\Models\AdminUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

/**
 * Gestión de usuarios admin — solo accesible para super_admin (ver
 * brain/PLAN-PANEL-ADMIN-EVENTOS-02082026.md §1.2).
 */
class AdminUserController extends Controller
{
    use AuthorizesEventoScope;

    public function index(): JsonResponse
    {
        $this->assertIsSuperAdmin();

        return response()->json([
            'success' => true,
            // Admin de evento asignado a varios eventos (28/08/2026) —
            // eager-cargado para que admin-eventos pueda mostrar los
            // eventos adicionales sin N+1.
            'data'    => AdminUser::with('eventosAdicionales:id,nombre')->orderBy('nombre')->paginate(20),
        ]);
    }

    public function store(StoreAdminUserRequest $request): JsonResponse
    {
        $this->assertIsSuperAdmin();

        $data = $request->validated();
        $eventoIdsAdicionales = $data['evento_ids_adicionales'] ?? [];
        unset($data['evento_ids_adicionales']);
        $data['password'] = Hash::make($data['password']);
        $data['activo'] = $data['activo'] ?? true;

        $admin = AdminUser::create($data);
        $admin->eventosAdicionales()->sync($eventoIdsAdicionales);

        return response()->json([
            'success' => true,
            'message' => 'Usuario admin creado correctamente.',
            'data'    => $admin->load('eventosAdicionales:id,nombre'),
        ], 201);
    }

    public function show(AdminUser $user): JsonResponse
    {
        $this->assertIsSuperAdmin();

        return response()->json([
            'success' => true,
            'data'    => $user->load('eventosAdicionales:id,nombre'),
        ]);
    }

    public function update(UpdateAdminUserRequest $request, AdminUser $user): JsonResponse
    {
        $this->assertIsSuperAdmin();

        $data = $request->validated();
        // Admin de evento asignado a varios eventos (28/08/2026) — solo
        // se resincroniza la pivote si el campo llegó en el request
        // (array_key_exists, no isset — un array vacío es una petición
        // legítima de "sacar todos los eventos adicionales", no "no
        // tocar nada").
        if (array_key_exists('evento_ids_adicionales', $data)) {
            $user->eventosAdicionales()->sync($data['evento_ids_adicionales'] ?? []);
        }
        unset($data['evento_ids_adicionales']);

        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        $user->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Usuario admin actualizado correctamente.',
            'data'    => $user->load('eventosAdicionales:id,nombre'),
        ]);
    }

    public function destroy(AdminUser $user): JsonResponse
    {
        $this->assertIsSuperAdmin();

        // No permitir que un super_admin se borre a sí mismo por accidente
        // vía API y quede el sistema sin ningún usuario con acceso.
        if (auth('admins')->id() === $user->id) {
            return response()->json([
                'success' => false,
                'error'   => 'No puede eliminar su propio usuario.',
            ], 409);
        }

        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'Usuario admin eliminado correctamente.',
        ]);
    }
}
