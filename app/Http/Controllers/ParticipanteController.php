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
        ]);

        $participantes = Participante::whereHas('registration', function ($q) use ($event) {
                $q->where('evento_id', $event->id);
            })
            ->with('registration:id,referencia')
            ->when($data['categoria'] ?? null, fn ($q, $categoria) => $q->where('categoria', $categoria))
            ->orderBy('categoria')
            ->orderBy('apellido')
            ->get([
                'id', 'registration_id', 'nombre', 'apellido', 'alias', 'numero_documento',
                'categoria', 'numero_corredor', 'chip', 'correo', 'telefono', 'direccion',
                'ciudad', 'genero', 'fecha_nacimiento', 'polera',
            ]);

        return response()->json([
            'success' => true,
            'participantes' => $participantes->map(fn (Participante $p) => [
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
            ]),
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
