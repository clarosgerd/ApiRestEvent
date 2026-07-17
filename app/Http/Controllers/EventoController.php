<?php

namespace App\Http\Controllers;

use App\Models\Evento;
use App\DTOs\EventoDTO;
use App\Http\Requests\StoreEventosRequest;
use App\Http\Requests\UpdateEventosRequest;
use App\Http\Resources\EventoCollection;
use App\Http\Resources\EventoResource;
use App\Services\EventoService;
use App\Filters\EventoFilter;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Jobs\SendWhatsappMessageJob;

class EventoController extends Controller
{
    public function __construct(
        private readonly EventoService $service
    ) {}

    /**
     * Display a listing of the resource.
     */
        public static $wrap = null; // Remove the 'data' wrapper

    public function index(Request $request):JsonResponse
    {
        //
//SendWhatsappMessageJob::dispatch('+59175925001@c.us', 'Hola, tu pedido está listo 📦');


    // dd($request);
        $filter = new EventoFilter();
        $filterItems = $filter->transform($request); // [['column','operator','value']]
        $eventos = Evento::where($filterItems);
        $eventos = $eventos->with('coordinates');
        $eventos = $eventos->with('routes');
        $eventos = $eventos->with('promoCodes');
        $eventos = $eventos->with('categories');
        $eventos = $eventos->with('formTypes.souvenirs');
        $eventos = $eventos->paginate()->appends($request->query());
        //dd($eventos);
       // $eventos = Evento::paginate(15);
        $collection = EventoResource::collection($eventos);
            return response()->json([
                'success' => true,
                'eventos' => $collection,
                'pagination' => [
                    'total' => $eventos->total(),
                    'per_page' => $eventos->perPage(),
                    'current_page' => $eventos->currentPage(),
                    'last_page' => $eventos->lastPage(),
                    'from' => $eventos->firstItem(),
                    'to' => $eventos->lastItem(),
                    'path'  => $eventos->path(),
                  //  'page'  => $eventos->lastPage(),

                ],
            ]);
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
    public function store(StoreEventosRequest $request): JsonResponse
    {
        $dto = EventoDTO::fromArray($request->validated());
        $evento = $this->service->create($dto);

        return response()->json([
            'success' => true,
            'message' => 'Evento registrado correctamente.',
            'eventos' => new EventoResource($evento),
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Evento $event): JsonResponse
    {
        //

        //dd($event);
       // $evento = Evento::findOrFail($eventos->id);
// Envío inmediato (a la cola)
//SendWhatsappMessageJob::dispatch('59175925001', 'Hola! Tu pedido está listo 📦');

// Envío con retraso (ej: 5 minutos después)
//SendWhatsappMessageJob::dispatch('59175925001', 'Recordatorio de tu cita')
//    ->delay(now()->addMinutes(5));

// Enviar a una cola específica (ej: "whatsapp")
//SendWhatsappMessageJob::dispatch('59175925001', 'Mensaje urgente')
//    ->onQueue('whatsapp');


        return response()->json([
            'success' => true,
            'eventos' => new EventoResource($event->loadMissing(['coordinates', 'routes', 'promoCodes','categories','formTypes.souvenirs'])),
        ]);

       // return   new EventoResource($event);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Eventos $eventos)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateEventosRequest $request, Eventos $eventos)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Eventos $eventos)
    {
        //
    }


   
}
