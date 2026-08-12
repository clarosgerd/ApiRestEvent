<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesEventoScope;
use App\Http\Requests\StoreListaEsperaRequest;
use App\Http\Resources\ListaEsperaResource;
use App\Models\Evento;
use App\Models\FormType;
use App\Models\ListaEspera;
use App\Models\Souvenir;
use App\Support\DisponibilidadItemData;
use Illuminate\Http\JsonResponse;

/**
 * Lista de espera de un form_type con cupo lleno, o de un ítem/talla
 * puntual agotado — ver PRD-kit-tallas-stock-lista-espera.md. `store()`
 * es público (sin auth, mismo criterio que /registrations/lookup);
 * `index()` es del panel de administración.
 */
class ListaEsperaController extends Controller
{
    use AuthorizesEventoScope;

    /**
     * Anotarse. Rechaza si en realidad hay cupo/stock disponible ahora
     * (no tiene sentido anotarse, se le dice que se inscriba
     * directamente) o si el form_type no admite lista de espera.
     */
    public function store(StoreListaEsperaRequest $request, Evento $event): JsonResponse
    {
        $data = $request->validated();

        $formType = FormType::where('id', $data['form_types_id'])
            ->where('event_id', $event->id)
            ->first();

        if (!$formType) {
            return response()->json([
                'success' => false,
                'error'   => 'Ese tipo de inscripción no pertenece a este evento.',
            ], 422);
        }

        if (!$formType->permite_lista_espera) {
            return response()->json([
                'success' => false,
                'error'   => 'Este tipo de inscripción no admite lista de espera.',
            ], 422);
        }

        $souvenir = null;
        if (!empty($data['souvenir_id'])) {
            $souvenir = Souvenir::where('id', $data['souvenir_id'])
                ->where('form_types_id', $formType->id)
                ->first();

            if (!$souvenir) {
                return response()->json([
                    'success' => false,
                    'error'   => 'Ese ítem no pertenece a este tipo de inscripción.',
                ], 422);
            }

            $disponible = DisponibilidadItemData::disponibleParaCombinacion($souvenir, $data['talla'] ?? null, $data['sexo'] ?? null);
            if ($disponible === null || $disponible > 0) {
                return response()->json([
                    'success' => false,
                    'error'   => 'Todavía hay disponibilidad de esa talla — podés inscribirte directamente.',
                ], 422);
            }
        } elseif ($formType->activo) {
            return response()->json([
                'success' => false,
                'error'   => 'Todavía hay cupo disponible — podés inscribirte directamente.',
            ], 422);
        }

        $entry = ListaEspera::create([
            'evento_id'     => $event->id,
            'form_types_id' => $formType->id,
            'souvenir_id'   => $souvenir?->id,
            'talla'         => $data['talla'] ?? null,
            'sexo'          => $data['sexo'] ?? null,
            'nombre'        => $data['nombre'],
            'correo'        => $data['correo'],
            'telefono'      => $data['telefono'] ?? null,
            'estado'        => 'pendiente',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Te anotamos en la lista de espera — te avisamos por correo si se libera un lugar.',
            'entry'   => new ListaEsperaResource($entry),
        ], 201);
    }

    /**
     * Listado del panel de administración — de solo lectura esta ronda
     * (la promoción es automática, ver
     * App\Actions\PromoverListaEsperaAction).
     */
    public function index(Evento $event): JsonResponse
    {
        $this->assertCanWriteEvento($event->id);

        $lista = ListaEspera::where('evento_id', $event->id)
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'success'      => true,
            'lista_espera' => ListaEsperaResource::collection($lista),
        ]);
    }
}
