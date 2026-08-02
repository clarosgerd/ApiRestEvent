<?php

namespace App\Http\Controllers;

use App\Models\AdminUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AdminAuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $admin = AdminUser::where('email', $request->email)->first();

        if (!$admin || !$admin->activo || !Hash::check($request->password, $admin->password)) {
            return response()->json([
                'success' => false,
                'error'   => 'Las credenciales proporcionadas son incorrectas.',
            ], 401);
        }

        $token = $admin->createToken('auth-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login exitoso',
            'data'    => [
                'admin' => [
                    'id'        => $admin->id,
                    'nombre'    => $admin->nombre,
                    'email'     => $admin->email,
                    'rol'       => $admin->rol,
                    'evento_id' => $admin->evento_id,
                ],
                'token' => $token,
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $admin = auth('admins')->user();

        if ($admin) {
            $admin->currentAccessToken()->delete();

            return response()->json(['success' => true, 'message' => 'Sesión cerrada con éxito']);
        }

        return response()->json(['success' => false, 'error' => 'No autorizado'], 401);
    }

    public function me(Request $request): JsonResponse
    {
        $admin = $request->user();

        return response()->json([
            'success' => true,
            'data'    => [
                'id'        => $admin->id,
                'nombre'    => $admin->nombre,
                'email'     => $admin->email,
                'rol'       => $admin->rol,
                'evento_id' => $admin->evento_id,
            ],
        ]);
    }
}
