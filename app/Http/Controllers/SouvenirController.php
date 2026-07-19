<?php

namespace App\Http\Controllers;

use App\Models\Souvenir;
use App\Http\Requests\StoreSouvenirRequest;
use App\Http\Requests\UpdateSouvenirRequest;
use App\Http\Resources\SouvenirCollection;
use App\Http\Resources\SouvenirResource;
use App\Filters\SouvenirFilter;
use Illuminate\Http\Request;

class SouvenirController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        //
        $filter = new SouvenirFilter();
        $filterItems = $filter->transform($request); // [['column','operator','value']]
        $souvenir = Souvenir::where($filterItems);
        return new SouvenirCollection($souvenir->paginate()->appends($request->query()) );
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
    public function store(StoreSouvenirRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Souvenir $souvenir)
    {
        //
        $includeSouvenir = request()->query('includeSouvenir');
        if ($includeSouvenir) {
            return new SouvenirResource($souvenir->loadMissing('souvenirs'));
        }

        return new SouvenirResource($souvenir);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Souvenir $souvenir)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateSouvenirRequest $request, Souvenir $souvenir)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Souvenir $souvenir)
    {
        //
    }
}
