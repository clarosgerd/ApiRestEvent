<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\AdminAuthController;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Consolidación monolito (21/08/2026), Fase 1a/1b (recorte mínimo de auth
 * necesario para poder probar Fase 1a en el navegador) — ver
 * ApiRestEvent/brain/api_rest_event/PLAN-CONSOLIDACION-MONOLITO-21082026.md.
 *
 * Portado 1:1 de admin-eventos/AuthController, pero en vez de
 * `ApiRestEventClient::forward('POST', '/admin/login', …)` (HTTP real) se
 * llama directo a `AdminAuthController::login()` — el mismo controller que
 * usa `/api/v1/admin/login` — y se guarda el token de Sanctum resultante
 * en sesión, exactamente igual que antes. Ver
 * `App\Http\Middleware\Admin\InjectAdminSessionToken` para cómo ese token
 * de sesión vuelve a alimentar el guard `admins` en cada request siguiente.
 */
class AuthController extends Controller
{
    public function showLoginForm(): View
    {
        return view('admin.auth.login');
    }

    public function login(Request $request, AdminAuthController $api): RedirectResponse
    {
        $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $response = $api->login($request);
        $payload = $response->getData(true);

        if (!($payload['success'] ?? false)) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => $payload['error'] ?? 'No se pudo iniciar sesión.']);
        }

        $data = $payload['data'];
        session([
            'admin_token' => $data['token'],
            'admin_user'  => $data['admin'],
        ]);

        // Caja de cobro presencial — un cajero no tiene dashboard, va
        // directo a su módulo. Todavía no migrado en esta sub-fase
        // (queda para 1d) — mientras tanto, si loguea un cajero acá, no
        // hay a dónde mandarlo: se le avisa en vez de romper con una ruta
        // inexistente.
        if (($data['admin']['rol'] ?? null) === 'cajero') {
            return redirect()->route('admin.login')->withErrors([
                'email' => 'El módulo de Caja todavía no está disponible en este panel (Fase 1d pendiente) — usá el panel de admin-eventos por ahora.',
            ]);
        }

        return \Illuminate\Support\Facades\Route::has('admin.dashboard')
            ? redirect()->route('admin.dashboard')
            : redirect()->route('admin.catalogos.index');
    }

    public function logout(AdminAuthController $api): RedirectResponse
    {
        if (session('admin_token')) {
            $api->logout(request());
        }

        session()->forget(['admin_token', 'admin_user']);

        return redirect()->route('admin.login');
    }
}
