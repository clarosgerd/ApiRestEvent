<?php

namespace App\Http\Controllers;

use App\Models\Persona;
use Illuminate\Http\Request;

class MarketingOptOutController extends Controller
{
    /**
     * Enlace de opt-out de los correos de marketing (§2.6) — la URL viene
     * firmada (Laravel signed routes), así que no requiere login: solo
     * quien recibió el correo tiene el enlace válido.
     */
    public function optOut(Request $request, Persona $persona)
    {
        $persona->update(['acepta_marketing' => false]);

        return response('You have been unsubscribed from marketing emails.', 200)
            ->header('Content-Type', 'text/plain');
    }
}
