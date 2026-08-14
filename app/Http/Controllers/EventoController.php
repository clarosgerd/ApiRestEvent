<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Evento;
use App\Models\Participante;
use App\Models\Registration;
use App\Models\TipoEvento;
use App\Http\Resources\CoordinateResource;
use App\Http\Resources\RouteResource;
use App\Http\Resources\CategoryResource;
use App\Services\ReferenceQrService;
use App\Actions\CrearEventoAction;
use App\Actions\PublicarEventoAction;
use App\Actions\DespublicarEventoAction;
use App\DTOs\EventoDTO;
use App\Http\Requests\StoreEventosRequest;
use App\Http\Requests\UpdateEventosRequest;
use App\Http\Resources\EventoCollection;
use App\Http\Resources\EventoResource;
use App\Services\EventoService;
use App\Services\AdminAuditLogger;
use App\Support\BalanceEventoData;
use App\Support\DashboardInscripcionesData;
use App\Filters\EventoFilter;
use App\Http\Controllers\Concerns\AuthorizesEventoScope;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Jobs\SendWhatsappMessageJob;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
use Spatie\IcalendarGenerator\Components\Calendar;
use Spatie\IcalendarGenerator\Components\Event as CalendarEvent;

class EventoController extends Controller
{
    use AuthorizesEventoScope;

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
        // .pricePeriods eager-cargado para que CategoryResource pueda
        // calcular precio_vigente (PrecioVigenteData::paraCategoria())
        // sin N+1 — ver PRD-precios-periodos-fechas.md.
        $eventos = $eventos->with('categories.pricePeriods');
        $eventos = $eventos->with('formTypes.souvenirs');
        $eventos = $eventos->with('formTypes.formularioCampos.options');
        $eventos = $eventos->with('organizador.formasPagoSeleccionadas');
        $eventos = $eventos->with('auspiciadores');
        $eventos = $eventos->with('agendaItems');
        $eventos = $eventos->with('equipos');
        $eventos = $eventos->with('tipoEvento');
        $eventos = $eventos->with('subtipoEvento');

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
    public function store(StoreEventosRequest $request, CrearEventoAction $action): JsonResponse
    {
        // Crear un evento nuevo (no editar uno existente) es una acción de
        // super_admin — un admin scoped a un solo evento no tiene ningún
        // evento_id nuevo al que quedar asociado automáticamente.
        $this->assertIsSuperAdmin();

        $dto = EventoDTO::fromArray($request->validated());
        $evento = $action->handle($dto);

        AdminAuditLogger::log('create', 'evento', $evento->id, $evento->id, null, $evento->toArray());

        return response()->json([
            'success' => true,
            'message' => 'Evento registrado correctamente.',
            'eventos' => new EventoResource($evento),
        ], 201);
    }

    /**
     * Publica un evento — pasa de borrador (el contrato con el organizador
     * todavía no está firmado) a elegible para mostrarse/inscribirse. Este
     * es el único momento en que se envía el correo con el link del
     * dashboard de organizador (y de delivery, si aplica): un evento recién
     * creado (borrador) todavía puede cambiar de forma, así que no tiene
     * sentido avisarle al organizador hasta que esté publicado.
     */
    public function publicar(Evento $event): JsonResponse
    {
        $this->assertCanWriteEvento($event->id);

        try {
            app(PublicarEventoAction::class)->handle($event);
        } catch (\DomainException $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Evento publicado correctamente.',
            'eventos' => new EventoResource($event->fresh()->loadMissing(['coordinates', 'routes', 'promoCodes', 'categories.pricePeriods', 'formTypes.souvenirs', 'formTypes.formularioCampos.options', 'organizador.formasPagoSeleccionadas', 'auspiciadores', 'agendaItems', 'equipos'])),
        ]);
    }

    /**
     * Revierte un evento publicado a borrador — a diferencia de publicar(),
     * no dispara ningún correo (es solo un cambio de estado interno).
     * Bloqueado si el evento ya tiene participantes inscritos, para no
     * ocultar un evento del que ya hay gente inscrita/pagando.
     */
    public function despublicar(Evento $event): JsonResponse
    {
        $this->assertCanWriteEvento($event->id);

        if ($event->registrations()->exists()) {
            return response()->json([
                'success' => false,
                'error'   => 'No se puede despublicar un evento que ya tiene participantes inscritos.',
            ], 409);
        }

        try {
            app(DespublicarEventoAction::class)->handle($event);
        } catch (\DomainException $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Evento despublicado correctamente.',
            'eventos' => new EventoResource($event->fresh()->loadMissing(['coordinates', 'routes', 'promoCodes', 'categories.pricePeriods', 'formTypes.souvenirs', 'formTypes.formularioCampos.options', 'organizador.formasPagoSeleccionadas', 'auspiciadores', 'agendaItems', 'equipos'])),
        ]);
    }

    /**
     * Dashboard de inscripciones (total/pagados/pendientes/cancelados/
     * fallidos, por categoría y por tipo de formulario) para el panel de
     * administración — mismo cálculo que ya se manda por correo al
     * organizador vía link firmado (ver OrganizadorDashboardController /
     * App\Support\DashboardInscripcionesData), ahora también disponible
     * autenticado (super_admin o el admin asignado a este evento).
     */
    public function dashboardInscripciones(Evento $event): JsonResponse
    {
        $this->assertCanWriteEvento($event->id);

        return response()->json([
            'success' => true,
            'balance' => BalanceEventoData::paraEvento($event),
        ] + DashboardInscripcionesData::paraEvento($event));
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
            'eventos' => new EventoResource($event->loadMissing(['coordinates', 'routes', 'promoCodes','categories.pricePeriods','formTypes.souvenirs','formTypes.formularioCampos.options','organizador.formasPagoSeleccionadas','auspiciadores','agendaItems','equipos','tipoEvento','subtipoEvento'])),
        ]);

       // return   new EventoResource($event);
    }

    /**
     * Endpoint de consumo, público, para un sistema externo (archivo
     * histórico de resultados, cronometraje, etc.) — ver
     * brain/PLAN-ENDPOINT-CONSUMO-05082026.md. Solo eventos **cerrados** y
     * de disciplina deportiva real (excluye "Congreso / No aplica", ver
     * migración 2026_08_05_120000_add_congreso_tipo_evento), con su
     * ruta/coordenadas/categorías, y solo los participantes **pagados** que
     * ya tienen número de corredor **y** chip asignados (si falta
     * cualquiera de los dos, no sale — no tendría sentido en un archivo de
     * resultados). Campos de participante acotados a propósito a lo pedido
     * (sexo, categoría, número de corredor, chip) — sin nombre, documento,
     * correo ni teléfono: es un endpoint público, sin login.
     *
     * `?evento_id=` opcional para pedir un solo evento en vez de todos los
     * cerrados.
     */
    public function consumo(Request $request): JsonResponse
    {
        $tipoCongresoIds = TipoEvento::where('nombre', 'Congreso / No aplica')->pluck('id');

        $query = Evento::where('estado_evento_id', 'closed')
            ->whereNotIn('tipo_evento_id', $tipoCongresoIds)
            ->with(['coordinates', 'routes', 'categories.pricePeriods', 'tipoEvento', 'subtipoEvento'])
            ->orderByDesc('fecha_inicio');

        if ($request->filled('evento_id')) {
            $query->where('id', $request->integer('evento_id'));
        }

        $eventos = $query->get();

        return response()->json([
            'success' => true,
            'eventos' => $eventos->map(function (Evento $evento) {
                $participantes = Participante::whereHas('registration', function ($q) use ($evento) {
                        $q->where('evento_id', $evento->id)->where('pago_status', 'paid');
                    })
                    ->whereNotNull('numero_corredor')
                    ->whereNotNull('chip')
                    ->get(['nombre', 'apellido', 'alias', 'genero', 'categoria', 'numero_corredor', 'chip']);

                return [
                    'id' => $evento->id,
                    'name' => $evento->nombre,
                    'date' => $evento->fecha_inicio,
                    'location' => $evento->direccion,
                    'tipoEvento' => $evento->tipoEvento?->nombre,
                    'subtipoEvento' => $evento->subtipoEvento?->nombre,
                    'coordinates' => CoordinateResource::collection($evento->coordinates),
                    'route' => RouteResource::collection($evento->routes),
                    'categories' => CategoryResource::collection($evento->categories),
                    'participantes' => $participantes->map(fn (Participante $p) => [
                        'nombre' => $p->nombre.' '.$p->apellido,
                        'alias' => $p->alias,
                        'genero' => $p->genero,
                        'categoria' => $p->categoria,
                        'numeroCorredor' => $p->numero_corredor,
                        'chip' => $p->chip,
                    ]),
                ];
            }),
        ]);
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
     * `.ics` (RFC 5545) público de la agenda del evento — descargable e
     * importable a Outlook/Apple Calendar/Google Calendar. Ver
     * elascenso/event/brain/PLAN-CALENDARIO-ICS-EVENTO-13082026.md.
     *
     * Un `VEVENT` por `agenda_item` si el evento tiene agenda de congreso
     * cargada; si no, cae a un solo `VEVENT` con los datos del evento
     * (fecha_inicio+localTime, lugar/dirección) — mismo criterio que la
     * tarjeta de agenda del frontend, que se esconde si no hay ítems.
     *
     * Zona horaria: no existe hoy ningún campo de TZ por evento ni por
     * país (ver el plan) — se usa un valor fijo de plataforma
     * (`config('app.event_ics_timezone')`), documentado como deuda si el
     * ecosistema llega a tener eventos en husos horarios distintos a la
     * vez.
     */
    public function agendaIcs(Evento $event)
    {
        $event->loadMissing('agendaItems');
        $tz = config('app.event_ics_timezone');

        // No se llama a ->timezone(): la librería genera el VTIMEZONE
        // automáticamente a partir de la zona horaria que ya traen los
        // Carbon de cada VEVENT (ver $tz más abajo, pasado a Carbon::parse).
        $calendar = Calendar::create($event->nombre)
            ->productIdentifier('-//Pass2Go//Agenda de eventos//ES');

        $items = $event->agendaItems->sortBy([
            ['fecha', 'asc'],
            ['hora_inicio', 'asc'],
            ['orden', 'asc'],
        ])->values();

        if ($items->isEmpty()) {
            // Evento sin agenda de congreso — un solo VEVENT con los datos
            // del evento. Sin hora_fin propia del evento: se asume una
            // duración por defecto (4hs), no hay dato real de cuánto dura.
            // `fecha_inicio` no tiene cast a Carbon en el modelo Evento — es
            // un string plano, distinto de AgendaItem::fecha que sí castea.
            $inicio = Carbon::parse(Carbon::parse($event->fecha_inicio)->toDateString() . ' ' . ($event->localTime ?: '00:00:00'), $tz);

            $calendarEvent = CalendarEvent::create($event->nombre)
                ->uniqueIdentifier('evento-' . $event->id . '@inscrito.net')
                ->startsAt($inicio)
                ->endsAt($inicio->clone()->addHours(4));

            if ($event->descripcion) {
                $calendarEvent->description(strip_tags($event->descripcion));
            }
            if ($event->lugar || $event->direccion) {
                $calendarEvent->address(trim(($event->direccion ?? '') . ' ' . ($event->lugar ?? '')), $event->lugar);
            }

            $calendar->event($calendarEvent);
        } else {
            foreach ($items as $item) {
                if (!$item->fecha || !$item->hora_inicio) {
                    continue;
                }

                $inicio = Carbon::parse($item->fecha . ' ' . $item->hora_inicio, $tz);
                $fin = $item->hora_fin
                    ? Carbon::parse($item->fecha . ' ' . $item->hora_fin, $tz)
                    : $inicio->clone()->addHour();

                $calendarEvent = CalendarEvent::create($item->titulo)
                    ->uniqueIdentifier('agenda-item-' . $item->id . '@inscrito.net')
                    ->startsAt($inicio)
                    ->endsAt($fin);

                $descripcion = trim(($item->descripcion ?? '') . ($item->ponente ? "\nPonente: {$item->ponente}" . ($item->ponente_cargo ? " ({$item->ponente_cargo})" : '') : ''));
                if ($descripcion) {
                    $calendarEvent->description($descripcion);
                }
                if ($item->sala) {
                    $calendarEvent->address($item->sala);
                }

                $calendar->event($calendarEvent);
            }
        }

        return response($calendar->get(), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="agenda-' . Str::slug($event->nombre) . '.ics"',
        ]);
    }

    /**
     * Gafetes/credenciales para imprimir en bulk antes del evento — uno por
     * participante inscrito (excluye cancelados/fallidos), con nombre,
     * categoría/rol y un QR de la referencia para check-in. Pensado para
     * congresos, pero sirve para cualquier evento.
     *
     * Color por gafete: **no** usa la marca del evento (`color_hex`) — cada
     * gafete toma el color de `form_types.color`, así que un evento con
     * varios tipos de formulario (Individual, Grupal, Voluntario...) puede
     * distinguirlos a simple vista en la mesa de acreditación. Sin color
     * propio en el form_type, cae al navy por defecto.
     */
    public function gafetesPdf(Evento $event)
    {
        $registrations = Registration::where('evento_id', $event->id)
            ->whereNotIn('pago_status', ['cancelled', 'failed'])
            ->with(['participants', 'formType'])
            ->get();

        $items = [];
        foreach ($registrations as $registration) {
            $color = $this->safeHex($registration->formType->color ?? null);

            foreach ($registration->participants as $participante) {
                $items[] = [
                    'nombre'     => trim($participante->nombre . ' ' . $participante->apellido),
                    'categoria'  => $registration->formType->name ?? $participante->categoria,
                    'referencia' => $registration->referencia,
                    'qr'         => ReferenceQrService::toBase64Png($registration->referencia),
                    'color'      => $color,
                ];
            }
        }

        // Gafete físico de 7x5cm (ver figura de referencia del organizador) —
        // sin logo/nombre de evento/foto/referencia, solo nombre + QR + rol.
        // A4 horizontal (mismo patrón que certificadosPdf) para que entren 3
        // gafetes por fila con margen de sobra; en A4 vertical con los
        // márgenes default de dompdf, 3×7cm queda muy justo/desborda.

        $pdf = Pdf::loadView('tickets.gafetes', [
            'evento' => $event,
            'filas'  => array_chunk($items, 3),
        ])->setPaper('a4', 'landscape');

        return $pdf->stream('gafetes-' . Str::slug($event->nombre) . '.pdf');
    }

    /**
     * Descarga la imagen de portada del evento y la devuelve como data URI
     * — dompdf tiene `enable_remote` en `false` por defecto (protección
     * anti-SSRF razonable, no se toca a nivel global) así que una URL
     * externa cruda en el `<img>` del PDF sale rota. Mismo criterio que
     * `ReferenceQrService::toBase64Png()`: el PDF solo recibe bytes ya
     * resueltos, nunca una URL que él mismo tenga que ir a buscar.
     * Best-effort: si falla (timeout, no es imagen, 404), el PDF se genera
     * igual, simplemente sin logo.
     */
    private function logoDataUri(?string $url): ?string
    {
        if (empty($url)) {
            return null;
        }

        try {
            $response = Http::timeout(5)->get($url);
            $mime = $response->header('Content-Type') ?: '';

            if (! $response->successful() || ! str_starts_with($mime, 'image/')) {
                return null;
            }

            return 'data:' . $mime . ';base64,' . base64_encode($response->body());
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Valida que un color venga en formato hex antes de dejarlo llegar a un
     * bloque <style> del PDF — es un campo que carga el organizador
     * (`form_types.color` / `eventos.color_hex`), así que un valor no-hex
     * colado hasta ahí podría romper el CSS del documento entero.
     */
    private function safeHex(?string $hex, string $fallback = '#022858'): string
    {
        return preg_match('/^#[0-9a-fA-F]{6}$/', (string) $hex) ? $hex : $fallback;
    }

    /**
     * Certificados en bulk para imprimir/entregar en el evento — uno por
     * participante elegible, un PDF por página (orientación horizontal).
     * Dos variantes según el form_type de la inscripción:
     * - `requiere_categoria = true` (carreras): "certificado de
     *   participación", solo para quienes tienen un Resultado cargado con
     *   estado `finisher` (dns/dnf/dsq quedan afuera, igual que el ranking
     *   de equipo — ver brain/PLAN-RESULTADOS-EQUIPOS-31072026.md), incluye
     *   tiempo oficial y posición.
     * - `requiere_categoria = false` (congresos, staff, voluntariado):
     *   "certificado de asistencia" simple, para cualquier inscripción
     *   pagada — no depende de resultados.
     */
    public function certificadosPdf(Evento $event)
    {
        $event->loadMissing('categories');
        $categoryNames = $event->categories->pluck('name', 'id');

        $registrations = Registration::where('evento_id', $event->id)
            ->whereNotIn('pago_status', ['cancelled', 'failed'])
            ->with(['formType', 'participants.resultado'])
            ->get();

        $items = [];
        foreach ($registrations as $registration) {
            $formType = $registration->formType;
            $requiereCategoria = $formType ? (bool) $formType->requiere_categoria : true;

            foreach ($registration->participants as $participante) {
                if ($requiereCategoria) {
                    $resultado = $participante->resultado;
                    if (! $resultado || $resultado->estado !== 'finisher') {
                        continue;
                    }

                    $items[] = [
                        'tipo'              => 'participacion',
                        'nombre'            => trim($participante->nombre . ' ' . $participante->apellido),
                        'categoria'         => $categoryNames[$participante->categoria] ?? $participante->categoria,
                        'tiempoOficial'     => $resultado->tiempo_oficial,
                        'posicionGeneral'   => $resultado->posicion_general,
                        'posicionCategoria' => $resultado->posicion_categoria,
                        'referencia'        => $registration->referencia,
                    ];
                } else {
                    $items[] = [
                        'tipo'       => 'asistencia',
                        'nombre'     => trim($participante->nombre . ' ' . $participante->apellido),
                        'rol'        => $participante->categoria,
                        'referencia' => $registration->referencia,
                    ];
                }
            }
        }

        $pdf = Pdf::loadView('tickets.certificados', [
            'evento' => $event,
            'items'  => $items,
            'logo'   => $this->logoDataUri($event->imagen_portada_url),
            'brand'  => $this->safeHex($event->color_hex),
        ])->setPaper('a4', 'landscape');

        return $pdf->stream('certificados-' . Str::slug($event->nombre) . '.pdf');
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
    public function update(UpdateEventosRequest $request, Evento $event): JsonResponse
    {
        $this->assertCanWriteEvento($event->id);

        // Cargo de servicio (11/08/2026) — a diferencia del resto de los
        // campos escalares de este Request, no lo puede tocar un admin
        // scoped a su propio evento: es la plataforma decidiendo cuánto
        // cobra, no un dato interno del organizador (mismo criterio que
        // Socios/Liquidación). El resto del update sigue funcionando
        // igual si no viene `feePct` en el body.
        if ($request->has('feePct')) {
            $this->assertIsSuperAdmin();
        }

        // CRUD de organizadores (11/08/2026) — mismo criterio de rol que
        // feePct arriba (super_admin, no el admin scoped al evento), más
        // una regla propia: una vez publicado, el organizador queda fijo.
        // El contrato/convenio se firma con un organizador puntual — si se
        // pudiera reasignar después de publicar, el organizador original ya
        // habría recibido el correo de dashboard (ver
        // EventoController::publicar()) para un evento que en teoría ya no
        // es el suyo.
        if ($request->has('organizador_id')) {
            $this->assertIsSuperAdmin();

            if ($event->publicado) {
                return response()->json([
                    'success' => false,
                    'error' => 'No se puede cambiar el organizador de un evento ya publicado.',
                ], 422);
            }
        }

        $before = $event->toArray();
        $event = $this->service->update($event, $request->validated());

        AdminAuditLogger::log('update', 'evento', $event->id, $event->id, $before, $event->toArray());

        return response()->json([
            'success' => true,
            'message' => 'Evento actualizado correctamente.',
            'eventos' => new EventoResource($event),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * Se rechaza (409) solo cuando el evento tiene participantes Y está
     * publicado a la vez — regla confirmada por el usuario, ver
     * brain/PLAN-PANEL-ADMIN-EVENTOS-02082026.md §1.1. Deja pasar a
     * propósito un evento publicado sin participantes, o uno con
     * participantes de prueba pero aún en borrador.
     */
    public function destroy(Evento $event): JsonResponse
    {
        $this->assertCanWriteEvento($event->id);

        if ($event->publicado && $event->registrations()->exists()) {
            return response()->json([
                'success' => false,
                'error'   => 'No se puede eliminar un evento publicado que ya tiene participantes.',
            ], 409);
        }

        $before = $event->toArray();
        $event->delete();

        AdminAuditLogger::log('delete', 'evento', $event->id, $event->id, $before, null);

        return response()->json([
            'success' => true,
            'message' => 'Evento eliminado correctamente.',
        ]);
    }


   
}
