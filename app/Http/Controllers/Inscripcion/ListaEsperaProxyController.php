<?php

namespace App\Http\Controllers\Inscripcion;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ListaEsperaController;
use App\Http\Requests\StoreListaEsperaRequest;
use App\Models\Evento;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Consolidación monolito (22/08/2026), Fase 2 — reemplaza
 * `elascenso-blade\Api\EventoProxyController::listaEspera()`. Único de los
 * proxies "simples" que necesita un adaptador propio en vez de una ruta
 * directa al controller de la API: `elascenso-blade` expone `evento_id` en
 * el BODY (`POST /eventos/lista-espera`, sin login), pero
 * `ListaEsperaController::store()` de la API lo espera en la URL
 * (`POST /event/{event}/lista-espera`, route model binding) — mismo motivo
 * por el que `Admin\Concerns\DelegatesToApiJson::mergeAndValidate()` existe
 * para Fase 1: el FormRequest (`StoreListaEsperaRequest`) no se puede
 * type-hintear directo en la firma porque Laravel lo resolvería/validaría
 * ANTES de que el evento esté resuelto.
 */
class ListaEsperaProxyController extends Controller
{
    public function store(Request $request, ListaEsperaController $api): JsonResponse
    {
        $eventoId = $request->input('evento_id');
        if (! $eventoId) {
            return response()->json(['success' => false, 'error' => 'Falta el ID del evento.'], 400);
        }

        $evento = Evento::find($eventoId);
        if (! $evento) {
            return response()->json(['success' => false, 'error' => 'Evento no encontrado.'], 404);
        }

        $formRequest = StoreListaEsperaRequest::createFrom($request);
        $formRequest->setContainer(app());
        $formRequest->setRedirector(app('redirect'));
        $formRequest->validateResolved();

        return $api->store($formRequest, $evento);
    }
}
