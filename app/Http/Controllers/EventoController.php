<?php

namespace App\Http\Controllers;

use App\Models\Evento;
use App\Models\Registration;
use App\Services\ReferenceQrService;
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
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;

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
        $eventos = $eventos->with('auspiciadores');
        $eventos = $eventos->with('agendaItems');
        $eventos = $eventos->with('equipos');

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
            'eventos' => new EventoResource($event->loadMissing(['coordinates', 'routes', 'promoCodes','categories','formTypes.souvenirs','formTypes.formularioCampos.options','organizador.formasPagoSeleccionadas','auspiciadores','agendaItems','equipos'])),
        ]);

       // return   new EventoResource($event);
    }

    /**
     * PDF público de la agenda completa del evento — todos los días, ítems
     * generales y por cada tipo de formulario. Sin datos de participantes,
     * pensado para descargar/compartir desde la página del evento antes de
     * inscribirse.
     */
    public function agendaPdf(Evento $event)
    {
        $event->loadMissing(['agendaItems', 'formTypes']);

        $formTypeNames = $event->formTypes->pluck('name', 'id');

        $items = $event->agendaItems->sortBy([
            ['fecha', 'asc'],
            ['hora_inicio', 'asc'],
            ['orden', 'asc'],
        ])->values();

        $dias = $items->groupBy(fn ($item) => $item->fecha ?? '');

        $estructura = $dias->map(function ($itemsDelDia) use ($formTypeNames) {
            return [
                'general' => $itemsDelDia->whereNull('form_type_id')->values(),
                'porTipo' => $itemsDelDia->whereNotNull('form_type_id')
                    ->groupBy('form_type_id')
                    ->mapWithKeys(fn ($grupo, $formTypeId) => [
                        ($formTypeNames[$formTypeId] ?? 'Otro') => $grupo->values(),
                    ]),
            ];
        });

        $pdf = Pdf::loadView('tickets.agenda', [
            'evento'     => $event,
            'estructura' => $estructura,
        ]);

        return $pdf->stream('agenda-' . Str::slug($event->nombre) . '.pdf');
    }

    /**
     * Gafetes/credenciales para imprimir en bulk antes del evento — uno por
     * participante inscrito (excluye cancelados/fallidos), con nombre,
     * categoría/rol y un QR de la referencia para check-in. Pensado para
     * congresos, pero sirve para cualquier evento.
     */
    public function gafetesPdf(Evento $event)
    {
        $registrations = Registration::where('evento_id', $event->id)
            ->whereNotIn('pago_status', ['cancelled', 'failed'])
            ->with('participants')
            ->get();

        $items = [];
        foreach ($registrations as $registration) {
            foreach ($registration->participants as $participante) {
                $items[] = [
                    'nombre'     => trim($participante->nombre . ' ' . $participante->apellido),
                    'categoria'  => $participante->categoria,
                    'referencia' => $registration->referencia,
                    'qr'         => ReferenceQrService::toBase64Png($registration->referencia),
                ];
            }
        }

        $pdf = Pdf::loadView('tickets.gafetes', [
            'evento' => $event,
            'filas'  => array_chunk($items, 3),
        ]);

        return $pdf->stream('gafetes-' . Str::slug($event->nombre) . '.pdf');
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
