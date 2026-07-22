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

        // Filtro por nombre de categoría: ?category[eq]=3k
        $categoryFilter = $request->query('category');
        if (isset($categoryFilter['eq'])) {
            $eventos->whereHas('categories', function ($q) use ($categoryFilter) {
                $q->where('name', '=', $categoryFilter['eq']);
            });
        } elseif (isset($categoryFilter['li'])) {
            $eventos->whereHas('categories', function ($q) use ($categoryFilter) {
                $q->where('name', 'like', '%' . $categoryFilter['li'] . '%');
            });
        }

        // Filtro por tipo de formulario: ?tipo[eq]=deportivo | ?tipo[li]=depor
        $tipoFilter = $request->query('tipo');
        if (isset($tipoFilter['eq'])) {
            $eventos->whereHas('formTypes', function ($q) use ($tipoFilter) {
                $q->where('tipo', '=', $tipoFilter['eq']);
            });
        } elseif (isset($tipoFilter['li'])) {
            $eventos->whereHas('formTypes', function ($q) use ($tipoFilter) {
                $q->where('tipo', 'like', '%' . $tipoFilter['li'] . '%');
            });
        }

        // Filtro por rango de precio: ?price[gte]=100&price[lte]=500
        $priceFilter = $request->query('price');
        if (isset($priceFilter['gte']) || isset($priceFilter['lte'])) {
            $eventos->whereHas('categories', function ($q) use ($priceFilter) {
                if (isset($priceFilter['gte'])) $q->where('price', '>=', $priceFilter['gte']);
                if (isset($priceFilter['lte'])) $q->where('price', '<=', $priceFilter['lte']);
            });
        }

        // Búsqueda de texto libre: coincide por nombre del evento o nombre de categoría.
        // ?search=texto
        $search = $request->query('search');
        if (!empty($search)) {
            $eventos->where(function ($q) use ($search) {
                $q->where('nombre', 'like', '%' . $search . '%')
                  ->orWhereHas('categories', function ($qq) use ($search) {
                      $qq->where('name', 'like', '%' . $search . '%');
                  });
            });
        }

        $eventos = $eventos->with('coordinates');
        $eventos = $eventos->with('routes');
        $eventos = $eventos->with('promoCodes');
        $eventos = $eventos->with('categories');
        $eventos = $eventos->with('formTypes.souvenirs');
        $eventos = $eventos->with('formTypes.formularioCampos.options');
        $eventos = $eventos->with('organizador.formasPagoSeleccionadas');

        // Tamaño de página configurable, acotado para evitar pedir el catálogo completo.
        $perPage = (int) $request->query('per_page', 12);
        $perPage = max(6, min(48, $perPage));

        $eventos = $eventos->paginate($perPage)->appends($request->query());
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
            'eventos' => new EventoResource($event->loadMissing(['coordinates', 'routes', 'promoCodes','categories','formTypes.souvenirs','formTypes.formularioCampos.options','organizador.formasPagoSeleccionadas'])),
        ]);

       // return   new EventoResource($event);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Evento $evento)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateEventosRequest $request, Evento $evento)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Evento $evento)
    {
        //
    }


   
}
