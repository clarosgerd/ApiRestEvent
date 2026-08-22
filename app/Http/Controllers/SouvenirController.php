<?php

namespace App\Http\Controllers;

use App\Models\FormType;
use App\Models\Souvenir;
use App\Models\SouvenirParticipante;
use App\Http\Requests\StoreSouvenirRequest;
use App\Http\Requests\UpdateSouvenirRequest;
use App\Http\Resources\SouvenirCollection;
use App\Http\Resources\SouvenirResource;
use App\Filters\SouvenirFilter;
use App\Http\Controllers\Concerns\AuthorizesEventoScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SouvenirController extends Controller
{
    use AuthorizesEventoScope;

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
    public function store(StoreSouvenirRequest $request): JsonResponse
    {
        $data = $request->validated();
        $formType = FormType::findOrFail($data['form_types_id']);
        $this->assertCanWriteEvento((int) $formType->event_id);

        // `nullable|boolean` (no `sometimes`) hace que $request->validated()
        // incluya `visible_participante: null` cuando el cliente no lo
        // manda — Eloquent no relee el default de la BD después del
        // INSERT, así que el modelo en memoria (y la respuesta JSON)
        // quedarían con `null` en vez del default real (`true`). Se
        // resuelve acá explícito en vez de tocar el patrón de validación
        // que ya comparten incluido/requiere_talla/requiere_sexo.
        $data['visible_participante'] ??= true;

        $souvenir = Souvenir::create($data);

        return response()->json([
            'success'  => true,
            'message'  => 'Souvenir creado correctamente.',
            'souvenir' => new SouvenirResource($souvenir),
        ], 201);
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
    public function update(UpdateSouvenirRequest $request, Souvenir $souvenir): JsonResponse
    {
        $this->assertCanWriteEvento((int) $souvenir->formType->event_id);

        $souvenir->update($request->validated());

        return response()->json([
            'success'  => true,
            'message'  => 'Souvenir actualizado correctamente.',
            'souvenir' => new SouvenirResource($souvenir),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * Bloquea (409) si algún participante ya seleccionó este souvenir.
     */
    public function destroy(Souvenir $souvenir): JsonResponse
    {
        $this->assertCanWriteEvento((int) $souvenir->formType->event_id);

        $enUso = SouvenirParticipante::where('souvenir_id', $souvenir->id)->exists();

        if ($enUso) {
            return response()->json([
                'success' => false,
                'error'   => 'No se puede eliminar este souvenir: ya fue seleccionado por un participante.',
            ], 409);
        }

        $souvenir->delete();

        return response()->json([
            'success' => true,
            'message' => 'Souvenir eliminado correctamente.',
        ]);
    }
}
