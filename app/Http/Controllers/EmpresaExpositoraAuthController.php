<?php

namespace App\Http\Controllers;

use App\Models\EmpresaExpositora;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * SmartStand (25/09/2026) — login de la empresa expositora (guard
 * `expositores`), mismo molde que ClubController. Lo consume la app de
 * escaneo del staff del expositor y, más adelante, expositor.php.
 */
class EmpresaExpositoraAuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        // El mismo correo puede tener cuenta en varios eventos (una por
        // evento). La contraseña es aleatoria por cuenta, así que la cuenta
        // correcta es la primera cuyo hash coincide.
        $cuenta = EmpresaExpositora::where('email', strtolower(trim($request->email)))
            ->get()
            ->first(fn (EmpresaExpositora $c) => Hash::check($request->password, $c->password));

        if (! $cuenta) {
            return response()->json([
                'success' => false,
                'error'   => 'Las credenciales proporcionadas son incorrectas.',
            ], 401);
        }

        if (! $cuenta->activo) {
            return response()->json([
                'success' => false,
                'error'   => 'Esta cuenta está desactivada. Contacta al organizador del evento.',
            ], 403);
        }

        $token = $cuenta->createToken('auth-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login exitoso',
            'data'    => [
                'empresa' => $this->presentar($cuenta),
                'token'   => $token,
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user('expositores')->currentAccessToken()->delete();

        return response()->json(['success' => true, 'message' => 'Sesión cerrada con éxito']);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $this->presentar($request->user('expositores')),
        ]);
    }

    private function presentar(EmpresaExpositora $cuenta): array
    {
        $cuenta->loadMissing(['evento', 'categoria']);

        return [
            'id'         => $cuenta->id,
            'nombre'     => $cuenta->nombre,
            'email'      => $cuenta->email,
            'stand'      => $cuenta->stand,
            'tamanoStand' => $cuenta->categoria?->name,
            'eventoId'   => $cuenta->evento_id,
            'eventoNombre' => $cuenta->evento?->nombre,
        ];
    }
}
