<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\AuthorizesEventoScope;
use App\Http\Controllers\Controller;
use App\Http\Controllers\EventoController as ApiEventoController;
use App\Models\Evento;
use App\Support\CalendarioGrilla;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Calendario del evento (05/10/2026): agenda y sesiones de congreso por mes,
 * semana y día. Llama a EventoController::calendario() de la API in-process
 * y arma la grilla con App\Support\CalendarioGrilla (lógica pura, portada
 * 1:1 de admin-eventos).
 */
class CalendarioEventoController extends Controller
{
    use AuthorizesEventoScope;

    public function index(Request $request, Evento $event, ApiEventoController $apiEvento): View
    {
        $this->assertCanViewEvento($event->id);

        $eventoData = $apiEvento->show($event)->getData(true)['eventos'] ?? null;
        abort_if(!$eventoData, 404);

        $calendarioPayload = $apiEvento->calendario($event)->getData(true);
        $calendario = $calendarioPayload['calendario'] ?? [];
        $inicio = $calendario['fecha_inicio'] ?? null;
        $fin = $calendario['fecha_fin'] ?? null;
        $bloques = $calendario['bloques'] ?? [];

        $vista = in_array($request->query('vista'), CalendarioGrilla::VISTAS, true) ? $request->query('vista') : 'mes';
        $ancla = $this->fechaAncla($request, $inicio);

        $grilla = match ($vista) {
            'semana' => CalendarioGrilla::semana($ancla, $bloques, $inicio, $fin),
            'dia' => CalendarioGrilla::dia($ancla, $bloques, $inicio, $fin),
            default => CalendarioGrilla::mes($ancla, $bloques, $inicio, $fin),
        };

        return view('admin.eventos.calendario', [
            'evento' => $eventoData,
            'vista' => $vista,
            'ancla' => $ancla,
            'grilla' => $grilla,
            'navegacion' => CalendarioGrilla::navegacion($vista, $ancla),
            'totalBloques' => count($bloques),
        ]);
    }

    /**
     * Fecha en torno a la que se arma la vista: la pedida, o la de inicio del
     * evento, o hoy si el evento no tiene fecha.
     */
    private function fechaAncla(Request $request, ?string $fechaInicio): Carbon
    {
        $pedida = $request->query('fecha');
        if (is_string($pedida) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $pedida)) {
            return Carbon::parse($pedida)->startOfDay();
        }

        return $fechaInicio ? Carbon::parse($fechaInicio)->startOfDay() : Carbon::today();
    }
}
