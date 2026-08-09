<?php

namespace App\Http\Controllers;

use App\Models\Evento;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

/**
 * Reemplaza en el navegador lo que hoy solo se puede hacer con
 * `organizador:generar-link {evento}` / `delivery:generar-link {evento}`
 * por consola (app/Console/Commands/GenerarLinkDashboardOrganizador.php,
 * GenerarLinkDelivery.php) — en UAT no hay SSH para correrlos. Misma
 * lógica exacta (URL::signedRoute contra las mismas rutas), no se
 * duplica nada.
 */
class OpsLinkController extends Controller
{
    public function index(): View
    {
        $eventos = Evento::orderByDesc('id')->limit(100)->get(['id', 'nombre']);

        return view('ops.links', compact('eventos'));
    }

    public function show(Evento $evento): View
    {
        $links = [
            'Dashboard organizador' => URL::signedRoute('organizador.dashboard', ['evento' => $evento->id]),
            // Consumido por elascenso/delivery en /retiro (EventoRetiroConfig.csv_url)
            // para el POS de retiro en sitio — trae numeración de corredor/chip y el
            // link de push-back por participante, no confundir con "Delivery CSV" de
            // abajo (esa es para envíos a domicilio, datos distintos).
            'Participantes CSV (retiro en sitio)' => URL::signedRoute('organizador.dashboard.export', ['evento' => $evento->id]),
            'Dashboard delivery (HTML)' => URL::signedRoute('delivery.dashboard', ['evento' => $evento->id]),
            'Delivery CSV' => URL::signedRoute('delivery.dashboard.export', ['evento' => $evento->id]),
            'Delivery JSON' => URL::signedRoute('delivery.dashboard.json', ['evento' => $evento->id]),
        ];

        $eventos = Evento::orderByDesc('id')->limit(100)->get(['id', 'nombre']);

        return view('ops.links', [
            'eventos' => $eventos,
            'selected' => $evento,
            'links' => $links,
        ]);
    }
}
