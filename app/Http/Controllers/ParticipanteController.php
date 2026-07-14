<?php

namespace App\Http\Controllers;

use App\Models\Participante;
use App\Http\Requests\StoreParticipanteRequest;
use App\Http\Requests\UpdateParticipanteRequest;
use App\Filters\ParticipanteFilter;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Resources\ParticipanteResource;
use App\Http\Resources\ParticipanteCollection;

class ParticipanteController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        //
      //  dd($request);
        /*$filter = new ParticipanteFilter();
        $filterItems = $filter->transform($request); // [['column','operator','value']]
        $participante = Participante::where($filterItems);
        return new ParticipanteCollection($participante->paginate()->appends($request->query()) );*/

        $participantes = Participante::with('contactoEmergencia')->get();

        return ParticipanteResource::collection($participantes);



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
    public function store(StoreParticipanteRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Participante $participante)
    {
        //
          $participantes = Participante::with('contactoEmergencia')->get();
        return ParticipanteResource::collection($participantes);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Participante $participante)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateParticipanteRequest $request, Participante $participante)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Participante $participante)
    {
        //
    }
}
