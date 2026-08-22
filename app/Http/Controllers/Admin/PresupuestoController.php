<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\DelegatesToApiJson;
use App\Http\Controllers\Controller;
use App\Http\Controllers\EventoController as ApiEventoController;
use App\Http\Controllers\PresupuestoCategoriaController as ApiPresupuestoCategoriaController;
use App\Http\Controllers\PresupuestoEventoController as ApiPresupuestoEventoController;
use App\Http\Requests\StorePresupuestoEventoRequest;
use App\Http\Requests\UpdatePresupuestoEventoRequest;
use App\Models\Evento;
use App\Models\PresupuestoEvento;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Consolidación monolito (21/08/2026), Fase 1e-iv — Presupuesto de un
 * evento (control financiero del organizador). Portado 1:1 de
 * admin-eventos, delegando en PresupuestoEventoController /
 * PresupuestoCategoriaController de la API. Ver
 * ApiRestEvent/brain/api_rest_event/PLAN-CONSOLIDACION-MONOLITO-21082026.md.
 */
class PresupuestoController extends Controller
{
    use DelegatesToApiJson;

    public function index(
        Evento $event,
        ApiEventoController $apiEvento,
        ApiPresupuestoEventoController $apiPresupuesto,
        ApiPresupuestoCategoriaController $apiCategorias
    ): View {
        $this->assertCanViewEvento($event->id);

        $eventoData = $apiEvento->show($event)->getData(true)['eventos'] ?? null;
        abort_if(!$eventoData, 404);

        $movimientos = $this->dataFrom($apiPresupuesto->index($event));

        // PresupuestoCategoriaController::index() de la API exige
        // super_admin (es un catálogo global) — pero esta pantalla de
        // presupuesto SÍ la puede ver un admin scoped a su propio evento.
        // El proxy HTTP viejo (admin-eventos) nunca veía esto como un
        // error: `$response?->json('data') ?? []` de un 403 simplemente
        // daba `[]`, así que un admin scoped se quedaba con el dropdown
        // de categorías vacío pero la página cargaba igual. Llamando el
        // método in-process, esa misma autorización tira una excepción
        // real que rompería TODA la página si no se atajara acá —
        // replicando el mismo "degradar en silencio" de siempre, no
        // "mejorando" el comportamiento sin que lo pidan.
        try {
            $categoriasData = $this->dataFrom($apiCategorias->index());
        } catch (HttpException $e) {
            $categoriasData = [];
        }
        // array_values(): array_filter no reindexa las claves, y la vista
        // asume $categorias[0] para el <option> preseleccionado.
        $categorias = array_values(array_filter($categoriasData, fn ($c) => $c['activo']));

        $balance = $apiEvento->dashboardInscripciones($event)->getData(true)['balance'] ?? null;

        return view('admin.eventos.presupuesto', [
            'evento' => $eventoData,
            'movimientos' => $movimientos,
            'categorias' => $categorias,
            'balance' => $balance,
        ]);
    }

    public function store(StorePresupuestoEventoRequest $request, Evento $event, ApiPresupuestoEventoController $api): RedirectResponse
    {
        $this->assertCanViewEvento($event->id);

        return $this->redirectFromApiResponse($api->store($request, $event), 'admin.presupuesto.index', [$event->id]);
    }

    public function update(UpdatePresupuestoEventoRequest $request, Evento $event, PresupuestoEvento $presupuesto, ApiPresupuestoEventoController $api): RedirectResponse
    {
        $this->assertCanViewEvento($event->id);

        return $this->redirectFromApiResponse($api->update($request, $event, $presupuesto), 'admin.presupuesto.index', [$event->id]);
    }

    public function destroy(Evento $event, PresupuestoEvento $presupuesto, ApiPresupuestoEventoController $api): RedirectResponse
    {
        $this->assertCanViewEvento($event->id);

        return $this->redirectFromApiResponse($api->destroy($event, $presupuesto), 'admin.presupuesto.index', [$event->id]);
    }

    /**
     * Mismo criterio que Admin\NumeracionController::assertCanViewEvento.
     */
    private function assertCanViewEvento(int $evento): void
    {
        $admin = session('admin_user');

        if (($admin['rol'] ?? null) !== 'super_admin' && (int) ($admin['evento_id'] ?? 0) !== $evento) {
            abort(403, 'No tiene acceso a este evento.');
        }
    }
}
