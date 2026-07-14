<?php

namespace App\Http\Controllers;

use App\DTOs\RegistrationDTO;
use App\DTP\ParticipantDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRegistrationRequest;
use App\Http\Resources\RegistrationResource;
use App\Http\Resources\PaginatedRegistrationCollectionResource;
use App\Http\Resources\RegistrationCollectionResource;

use App\Models\Registration;
use App\Models\Participante;

use App\Services\RegistrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RegistrationController extends Controller
{
    public function __construct(
        private readonly RegistrationService $service
    ) {
    }

    /**
     * Listado paginado.
     */
    public function index(Request $request): PaginatedRegistrationCollectionResource
    {
        $registrations = Registration::with([
            'totals',
            'participants.contactoEmergenciaParticipante',
            'participants.souvenirParticipante',
        ])
            ->when(
                $request->filled('evento_id'),
                fn($query) => $query->where('evento_id', $request->evento_id)
            )
            ->when(
                $request->filled('pago_status'),
                fn($query) => $query->where('pago_status', $request->pago_status)
            )
            ->when(
                $request->filled('tipo_pago'),
                fn($query) => $query->where('tipo_pago', $request->tipo_pago)
            )
            ->orderByDesc('fecha')
            ->paginate(
                $request->integer('per_page', 15)
            );

        return new PaginatedRegistrationCollectionResource($registrations);
    }

    /**
     * Registrar una inscripción.
     */
    public function store(StoreRegistrationRequest $request): JsonResponse
    {
//dd($request);

        $dto = RegistrationDTO::fromArray(
            $request->validated()[0]
        );
       // dd( $request->validated()[0]);
        $registration = $this->service->create($dto);

        return response()->json([
            'success' => true,
            'message' => 'Inscripción registrada correctamente.',
            'data' => new RegistrationCollectionResource($registration)
        ], 201);
    }

    /**
     * Obtener una inscripción por referencia.
     */
    public function show(string $reference): JsonResponse
    {

    
      //dd($reference);
        $registration = Registration::with([
          'totals',
           'participants.contactoEmergenciaParticipante',
           'participants.souvenirParticipante'
        ])
        ->where('referencia', $reference)
        ->firstOrFail();
 //dd($registration);
        return response()->json([
            'success' => true,
            'data' => new RegistrationCollectionResource($registration)
        ]);
    }

    /**
     * Actualizar estado del pago.
     */
    public function updatePayment(
        Request $request,
        string $reference
    ): JsonResponse {



   // dd($request);
        $request->validate([
            'pago_status' => [
                'required',
                'string',
                'in:pending,paid,failed,cancelled'
            ]
        ]);

        $registration = $this->service->updatePaymentStatus(
            $reference,
            $request->pago_status
        );

        return response()->json([
            'success' => true,
            'message' => 'Estado de pago actualizado.',
            'data' => new RegistrationCollectionResource($registration)
        ]);
    }

    /**
     * Eliminar una inscripción.
     */
    public function destroy(string $reference): JsonResponse
    {
        $registration = Registration::where(
            'referencia',
            $reference
        )->firstOrFail();

        $registration->delete();

        return response()->json([
            'success' => true,
            'message' => 'Inscripción eliminada correctamente.'
        ]);
    }
}