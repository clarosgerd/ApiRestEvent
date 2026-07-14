<?php

namespace App\Http\Controllers;

use App\Models\Coordinate;
use App\Http\Requests\StoreCoordinateRequest;
use App\Http\Requests\UpdateCoordinateRequest;
use App\Http\Resources\CoordinateResource;
use App\Http\Resources\CoordinateCollection;
use App\Filters\CoordinateFilter;
use Illuminate\Http\Request;


class CoordinateController extends Controller
{
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
    public function store(StoreCoordinateRequest $request)
    {
        //
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
    public function update(UpdateCoordinateRequest $request, Coordinate $coordinate)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Coordinate $coordinate)
    {
        //
    }
}
