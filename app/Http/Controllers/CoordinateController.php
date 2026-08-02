<?php

namespace App\Http\Controllers;

use App\Models\Coordinate;
use App\Http\Requests\StoreCoordinateRequest;
use App\Http\Requests\UpdateCoordinateRequest;
use App\Http\Resources\CoordinateResource;
use App\Http\Resources\CoordinateCollection;
use App\Filters\CoordinateFilter;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Concerns\AuthorizesEventoScope;
use App\Services\AdminAuditLogger;


class CoordinateController extends Controller
{
    use AuthorizesEventoScope;

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        //
         $filter = new CoordinateFilter();
        $filterItems = $filter->transform($request); // [['column','operator','value']]
        $coordinate = Coordinate::where($filterItems);
        return new CoordinateCollection($coordinate->paginate()->appends($request->query()) );
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
    public function store(StoreCoordinateRequest $request): JsonResponse
    {
        $data = $request->validated();
        $this->assertCanWriteEvento((int) $data['event_id']);

        $coordinate = Coordinate::create($data);

        AdminAuditLogger::log('create', 'coordinate', $coordinate->id, (int) $coordinate->event_id, null, $coordinate->toArray());

        return response()->json([
            'success'    => true,
            'message'    => 'Coordenada creada correctamente.',
            'coordinate' => new CoordinateResource($coordinate),
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Coordinate $coordinate)
    {
        //

        $includeCoordinate = request()->query('includeCoordinate');
        if ($includeCoordinate) {
            return new CoordinateResource($coordinate->loadMissing('coordinates'));
        }

        return new CoordinateResource($coordinate);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Coordinate $coordinate)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateCoordinateRequest $request, Coordinate $coordinate): JsonResponse
    {
        $this->assertCanWriteEvento((int) $coordinate->event_id);

        $before = $coordinate->toArray();
        $coordinate->update($request->validated());

        AdminAuditLogger::log('update', 'coordinate', $coordinate->id, (int) $coordinate->event_id, $before, $coordinate->toArray());

        return response()->json([
            'success'    => true,
            'message'    => 'Coordenada actualizada correctamente.',
            'coordinate' => new CoordinateResource($coordinate),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * Sin guarda de inscripciones: es dato geográfico puro, ningún
     * participante lo referencia.
     */
    public function destroy(Coordinate $coordinate): JsonResponse
    {
        $this->assertCanWriteEvento((int) $coordinate->event_id);

        $before = $coordinate->toArray();
        $coordinate->delete();

        AdminAuditLogger::log('delete', 'coordinate', $coordinate->id, (int) $coordinate->event_id, $before, null);

        return response()->json([
            'success' => true,
            'message' => 'Coordenada eliminada correctamente.',
        ]);
    }
}
