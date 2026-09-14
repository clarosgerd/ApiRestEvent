<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\AuthorizesEventoScope;
use App\Http\Controllers\Controller;
use App\Http\Controllers\EventoController as ApiEventoController;
use App\Http\Controllers\ListaEsperaController as ApiListaEsperaController;
use App\Models\Evento;
use Illuminate\View\View;

/**
 * Consolidación monolito (21/08/2026), Fase 1e-iii — lista de espera de un
 * evento (solo lectura, la promoción es automática vía
 * PromoverListaEsperaAction del lado API). Portado 1:1 de admin-eventos.
 * Ver ApiRestEvent/brain/api_rest_event/PLAN-CONSOLIDACION-MONOLITO-21082026.md.
 */
class ListaEsperaController extends Controller
{
    use AuthorizesEventoScope;

    public function index(Evento $event, ApiEventoController $apiEvento, ApiListaEsperaController $apiListaEspera): View
    {
        $this->assertCanViewEvento($event->id);

        $eventoData = $apiEvento->show($event)->getData(true)['eventos'] ?? null;
        abort_if(!$eventoData, 404);

        $lista = $apiListaEspera->index($event)->getData(true)['lista_espera'] ?? [];

        $nombresFormTypes = collect($eventoData['formTypes'] ?? [])->pluck('name', 'id');

        return view('admin.eventos.lista-espera', [
            'evento' => $eventoData,
            'lista' => $lista,
            'nombresFormTypes' => $nombresFormTypes,
        ]);
    }
}
