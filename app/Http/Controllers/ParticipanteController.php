<?php

namespace App\Http\Controllers;

use App\Models\Evento;
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
        $filter = new ParticipanteFilter();
        $filterItems = $filter->transform($request); // [['column','operator','value']]
        $participante = Participante::where($filterItems)->with('contactoEmergencia');
        return new ParticipanteCollection($participante->paginate()->appends($request->query()) );

        /*$participantes = Participante::with('contactoEmergencia')->get();

        return ParticipanteResource::collection($participantes);*/



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

        return response()->json([
            'success' => true,
            'participante' => new ParticipanteResource($participante->loadMissing('contactoEmergencia')),
        ]);
        
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

    /**
     * Carga masiva de número de corredor y chip, matcheando por
     * numero_documento dentro del evento indicado (es el único dato
     * garantizado desde la inscripción — ver
     * brain/PLAN-RESULTADOS-EQUIPOS-31072026.md §1).
     *
     * Body: { "items": [ { "numero_documento": "...", "numero_corredor": "...", "chip": "..." }, ... ] }
     */
    public function numeracionBulk(Request $request, Evento $event): JsonResponse
    {
        $data = $request->validate([
            'items'                       => ['required', 'array', 'min:1'],
            'items.*.numero_documento'    => ['required', 'string'],
            'items.*.numero_corredor'     => ['nullable', 'string', 'max:50'],
            'items.*.chip'                => ['nullable', 'string', 'max:50'],
        ]);

        $actualizados  = 0;
        $noEncontrados = [];

        foreach ($data['items'] as $item) {
            $participante = Participante::whereHas('registration', function ($q) use ($event) {
                    $q->where('evento_id', $event->id);
                })
                ->where('numero_documento', $item['numero_documento'])
                ->first();

            if (!$participante) {
                $noEncontrados[] = $item['numero_documento'];
                continue;
            }

            $participante->update([
                'numero_corredor' => $item['numero_corredor'] ?? $participante->numero_corredor,
                'chip'            => $item['chip'] ?? $participante->chip,
            ]);
            $actualizados++;
        }

        return response()->json([
            'success'        => true,
            'actualizados'   => $actualizados,
            'no_encontrados' => $noEncontrados,
        ]);
    }
}
