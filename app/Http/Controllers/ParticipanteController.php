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
use App\Http\Controllers\Concerns\AuthorizesEventoScope;
use App\Services\AdminAuditLogger;

class ParticipanteController extends Controller
{
    use AuthorizesEventoScope;

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
     * Edición restringida desde el panel de administración (admin-eventos)
     * — solo datos no sensibles de contacto/identidad (ver whitelist real
     * en UpdateParticipanteRequest::rules()) más la talla de camiseta,
     * únicamente si el participante ya tiene una asignada. Nunca toca
     * categoria/precio/souvenirs/donacion/promo/equipo/delivery/subtotal
     * ni numero_corredor/chip (esos tienen su propio flujo dedicado) ni
     * numero_documento (identidad, anti-fraude).
     */
    public function update(UpdateParticipanteRequest $request, Participante $participante): JsonResponse
    {
        $participante->loadMissing('registration');
        $eventoId = (int) $participante->registration->evento_id;
        $this->assertCanWriteEvento($eventoId);

        $data = $request->validated();

        // "con polera" / "sin polera" es una decisión de precio/souvenir, no
        // un dato personal — mismo criterio que ya usa elascenso/event en
        // api/_registro_validacion.php al validar altas/ediciones completas.
        if (array_key_exists('polera', $data)) {
            $teniaPolera  = !empty($participante->polera) && $participante->polera !== 'No shirt';
            $tendraPolera = !empty($data['polera']) && $data['polera'] !== 'No shirt';
            if ($teniaPolera !== $tendraPolera) {
                return response()->json([
                    'success' => false,
                    'error'   => 'No se puede cambiar entre "con polera" y "sin polera" desde acá — es un dato de precio/souvenir.',
                ], 422);
            }
        }

        $before = $participante->toArray();
        $participante->update($data);

        AdminAuditLogger::log('update', 'participante', $participante->id, $eventoId, $before, $participante->toArray());

        return response()->json([
            'success'      => true,
            'message'      => 'Participante actualizado.',
            'participante' => new ParticipanteResource($participante),
        ]);
    }

    /**
     * Marcar a un participante como presente (acreditación / check-in) —
     * panel de administración, disparado al escanear el QR de referencia
     * (ver RegistrationController::checkinLookup para el paso previo de
     * búsqueda). Gate real de pago acá, no solo visual en el frontend —
     * ver brain de la sesión 10/08/2026.
     *
     * Reescanear a alguien ya acreditado NO es un error: se devuelve el
     * timestamp original sin pisarlo, con `alreadyCheckedIn: true`, para
     * que el staff pueda escanear de más sin miedo a romper nada.
     */
    public function checkin(Participante $participante): JsonResponse
    {
        $participante->loadMissing('registration');
        $eventoId = (int) $participante->registration->evento_id;
        $this->assertCanWriteEvento($eventoId);

        if ($participante->registration->pago_status !== 'paid') {
            return response()->json([
                'success' => false,
                'error'   => 'No se puede acreditar: el pago no está confirmado.',
            ], 422);
        }

        if ($participante->checked_in_at) {
            return response()->json([
                'success'         => true,
                'alreadyCheckedIn' => true,
                'participante'    => new ParticipanteResource($participante),
            ]);
        }

        $before = $participante->toArray();
        $participante->update(['checked_in_at' => now()]);

        AdminAuditLogger::log('checkin', 'participante', $participante->id, $eventoId, $before, $participante->toArray());

        return response()->json([
            'success'          => true,
            'alreadyCheckedIn' => false,
            'participante'     => new ParticipanteResource($participante),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Participante $participante)
    {
        //
    }

    /**
     * Listado de participantes de un evento para el panel de
     * administración — usado por la pantalla de numeración de
     * corredor/chip (descarga de CSV, edición manual) y por la pantalla
     * de edición restringida de datos de contacto (ver
     * ParticipantesController en admin-eventos / update() arriba).
     * Filtrable por categoría (string libre, ver §0 de
     * brain/PLAN-RESULTADOS-EQUIPOS-31072026.md, no es FK a
     * `categories`). Solo super_admin o el admin asignado a este evento
     * (`AuthorizesEventoScope`, misma regla que el resto del panel).
     */
    public function porEvento(Request $request, Evento $event): JsonResponse
    {
        $this->assertCanWriteEvento($event->id);

        $data = $request->validate([
            'categoria' => ['nullable', 'string'],
            // Reporte detallado de inscritos (15/08/2026) — filtro opcional
            // por estado, mismo enum que App\Support\DashboardInscripcionesData
            // (que expone la constante como pública justamente para esto).
            'pago_status' => ['nullable', 'string', 'in:' . implode(',', \App\Support\DashboardInscripcionesData::ESTADOS)],
            // Paginación opt-in: si no viene `per_page`, se mantiene el
            // comportamiento de siempre (`->get()`, todo en una sola
            // respuesta) para no romper a NumeracionController/
            // ParticipantesController (admin-eventos), que no lo mandan.
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $query = Participante::whereHas('registration', function ($q) use ($event, $data) {
                $q->where('evento_id', $event->id)
                    ->when($data['pago_status'] ?? null, fn ($q2, $estado) => $q2->where('pago_status', $estado));
            })
            // talleresSesiones: reporte detallado de inscritos (19/08/2026) —
            // `subtotal` nunca incluyó el importe de talleres (ver
            // App\Support\ReporteInscritosData, mismo criterio), así que el
            // organizador no podía conciliar contra el banco con lo que ya
            // mostraba este reporte. Eager-load para no hacer N+1 al sumar
            // `total` por participante en el map() de abajo.
            ->with(['registration:id,referencia,pago_status,fecha', 'talleresSesiones'])
            ->when($data['categoria'] ?? null, fn ($q, $categoria) => $q->where('categoria', $categoria))
            ->orderBy('categoria')
            ->orderBy('apellido');

        $columnas = [
            'id', 'registration_id', 'nombre', 'apellido', 'alias', 'numero_documento',
            'categoria', 'numero_corredor', 'chip', 'correo', 'telefono', 'direccion',
            'ciudad', 'genero', 'fecha_nacimiento', 'polera', 'checked_in_at', 'subtotal',
        ];

        $mapear = fn (Participante $p) => [
            'id'              => $p->id,
            'referencia'      => $p->registration->referencia,
            'nombre'          => $p->nombre,
            'apellido'        => $p->apellido,
            'alias'           => $p->alias,
            'numeroDocumento' => $p->numero_documento,
            'categoria'       => $p->categoria,
            'numeroCorredor'  => $p->numero_corredor,
            'chip'            => $p->chip,
            'correo'          => $p->correo,
            'telefono'        => $p->telefono,
            'direccion'       => $p->direccion,
            'ciudad'          => $p->ciudad,
            'genero'          => $p->genero,
            'fechaNacimiento' => optional($p->fecha_nacimiento)->format('Y-m-d'),
            'polera'          => $p->polera,
            // pagoStatus/checkedInAt: para el contador "X de Y acreditados"
            // de la pantalla de Acreditación (admin-eventos) — "Y" es el
            // total de pagados, "X" cuántos de esos ya tienen checkedInAt.
            'pagoStatus'      => $p->registration->pago_status,
            'checkedInAt'     => optional($p->checked_in_at)->toIso8601String(),
            // importe/fechaInscripcion: reporte detallado de inscritos
            // (15/08/2026), pantalla nueva "Detalle de inscritos" en
            // admin-eventos.
            'importe'         => (float) $p->subtotal,
            // importeTaller/importeTotal (19/08/2026) — `importe` (subtotal)
            // no incluye talleres, así que no alcanzaba para conciliar
            // contra el banco lo que el participante realmente pagó.
            // `importeTotal` es lo comparable contra el depósito real
            // (no incluye el cargo de servicio, que se cobra por
            // inscripción/registro completo, no por participante).
            'importeTaller'   => round((float) $p->talleresSesiones->sum('total'), 2),
            'importeTotal'    => round((float) $p->subtotal + (float) $p->talleresSesiones->sum('total'), 2),
            'fechaInscripcion' => optional($p->registration->fecha)->toIso8601String(),
        ];

        if ($data['per_page'] ?? null) {
            $paginador = $query->paginate($data['per_page'], $columnas, 'page', $data['page'] ?? 1);

            return response()->json([
                'success' => true,
                'participantes' => $paginador->getCollection()->map($mapear),
                'meta' => [
                    'currentPage' => $paginador->currentPage(),
                    'lastPage' => $paginador->lastPage(),
                    'perPage' => $paginador->perPage(),
                    'total' => $paginador->total(),
                ],
            ]);
        }

        $participantes = $query->get($columnas);

        return response()->json([
            'success' => true,
            'participantes' => $participantes->map($mapear),
        ]);
    }

    /**
     * Carga masiva de número de corredor y chip, matcheando por
     * numero_documento dentro del evento indicado (es el único dato
     * garantizado desde la inscripción — ver
     * brain/PLAN-RESULTADOS-EQUIPOS-31072026.md §1). Solo super_admin o
     * el admin asignado a este evento.
     *
     * Body: { "items": [ { "numero_documento": "...", "numero_corredor": "...", "chip": "..." }, ... ] }
     *
     * Corrección/borrado (numeración mal cargada, chip fallado, corredor
     * dado de baja) es un caso de uso normal, no una excepción — así que
     * una celda vacía en el CSV **sí borra** el valor existente (envía
     * `null` explícito). Lo que sí se respeta es la ausencia total de la
     * clave en el item (`array_key_exists`), por si algún caller manda
     * un payload parcial con un solo campo — eso no toca el otro.
     */
    public function numeracionBulk(Request $request, Evento $event): JsonResponse
    {
        $this->assertCanWriteEvento($event->id);

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

            $updates = [];
            if (array_key_exists('numero_corredor', $item)) {
                $updates['numero_corredor'] = $item['numero_corredor'];
            }
            if (array_key_exists('chip', $item)) {
                $updates['chip'] = $item['chip'];
            }
            $participante->update($updates);
            $actualizados++;
        }

        return response()->json([
            'success'        => true,
            'actualizados'   => $actualizados,
            'no_encontrados' => $noEncontrados,
        ]);
    }
}
