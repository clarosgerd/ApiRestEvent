<?php

namespace App\Http\Controllers;

use App\Models\Route;
use App\Http\Requests\StoreRouteRequest;
use App\Http\Requests\UpdateRouteRequest;
use Illuminate\Http\Request;
use App\Filters\RouteFilter;
use App\Http\Resources\RouteCollection;
use App\Http\Resources\RouteResource;

class RouteController extends Controller
{
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
    public function store(StoreRouteRequest $request)
    {
        //
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
    public function update(UpdateRouteRequest $request, Route $route)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Route $route)
    {
        //
    }
}
