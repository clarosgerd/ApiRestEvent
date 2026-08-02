<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Login del panel de operaciones (/ops) — guard `web` nativo de Laravel
 * contra la tabla `users`, hasta ahora sin usar (ver
 * app/Providers/GoogleDriveServiceProvider.php y brain/ para el resto de
 * esta sección). Completamente aparte del login de `admin-eventos`
 * (tabla `admin_users`) y del de la SPA de inscripción (`persona`/`club`).
 */
class OpsAuthController extends Controller
{
    public function showLogin(): View
    {
        return view('ops.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (!Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'Credenciales incorrectas.'])->onlyInput('email');
        }

        $request->session()->regenerate();

        return redirect()->route('ops.backups');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('ops.login.show');
    }
}
