<?php

namespace App\Http\Controllers;

use App\Models\Route;
use App\Http\Requests\StoreRouteRequest;
use App\Http\Requests\UpdateRouteRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Filters\RouteFilter;
use App\Http\Resources\RouteCollection;
use App\Http\Resources\RouteResource;
use App\Http\Controllers\Concerns\AuthorizesEventoScope;
use App\Services\AdminAuditLogger;

class RouteController extends Controller
{
    use AuthorizesEventoScope;

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        //
           $filter = new RouteFilter();
        $filterItems = $filter->transform($request); // [['column','operator','value']]
        $route = Route::where($filterItems);
        return new RouteCollection($route->paginate()->appends($request->query()) );
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreRouteRequest $request): JsonResponse
    {
        $data = $request->validated();
        $this->assertCanWriteEvento((int) $data['event_id']);

        $route = Route::create($data);

        AdminAuditLogger::log('create', 'route', $route->id, (int) $route->event_id, null, $route->toArray());

        return response()->json([
            'success' => true,
            'message' => 'Ruta creada correctamente.',
            'route'   => new RouteResource($route),
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Route $route)
    {
        //
         $includeRoute = request()->query('includeRoute');
        if ($includeRoute) {
            return new RouteResource($route->loadMissing('routes'));
        }

        return new RouteResource($route);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Route $route)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateRouteRequest $request, Route $route): JsonResponse
    {
        $this->assertCanWriteEvento((int) $route->event_id);

        $before = $route->toArray();
        $route->update($request->validated());

        AdminAuditLogger::log('update', 'route', $route->id, (int) $route->event_id, $before, $route->toArray());

        return response()->json([
            'success' => true,
            'message' => 'Ruta actualizada correctamente.',
            'route'   => new RouteResource($route),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * Sin guarda de inscripciones: es dato geográfico puro, ningún
     * participante lo referencia.
     */
    public function destroy(Route $route): JsonResponse
    {
        $this->assertCanWriteEvento((int) $route->event_id);

        $before = $route->toArray();
        $route->delete();

        AdminAuditLogger::log('delete', 'route', $route->id, (int) $route->event_id, $before, null);

        return response()->json([
            'success' => true,
            'message' => 'Ruta eliminada correctamente.',
        ]);
    }
}
