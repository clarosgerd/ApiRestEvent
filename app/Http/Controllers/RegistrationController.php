<?php

namespace App\Http\Controllers;

use App\DTOs\RegistrationDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRegistrationRequest;
use App\Http\Requests\UpdateRegistrationRequest;
use App\Http\Requests\UpdatePaidRegistrationRequest;
use App\Http\Requests\LookupRegistrationRequest;
use App\Http\Resources\RegistrationResource;
use App\Http\Resources\PaginatedRegistrationCollectionResource;
use App\Http\Resources\RegistrationCollectionResource;
use App\Http\Resources\PersonaResource;
use App\Models\Registration;
use App\Models\Participante;
use App\Services\RegistrationService;
use App\Services\QrService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class RegistrationController extends Controller
{
    public function __construct(
        private readonly RegistrationService $service,
        private readonly QrService $qrService
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
            'participants.answers',
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
           'participants.souvenirParticipante',
           'participants.answers',
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

public function estadoTransaccion(
        string $reference
    ): JsonResponse {

        $registration = Registration::where('referencia', $reference)->firstOrFail();

        $tokenData = $this->qrService->generarToken();
        $token = $tokenData['token'] ?? $tokenData['data']['token'] ?? $tokenData['access_token'] ?? '';

        $estado = $this->qrService->estadoTransaccion($reference, $token);

        return response()->json([
            'success' => true,
            'data'    => $estado,
        ]);
    }

    /**
     * Generar token de autenticación para pagos.
     */
    public function generarToken(): JsonResponse
    {
        $token = $this->qrService->generarToken();

        return response()->json([
            'success' => true,
            'message' => 'Token generado correctamente.',
            'data'    => $token,
        ]);
    }

    /**
     * Generar código QR para pago de una inscripción.
     */
    public function generaQr(string $reference): JsonResponse
    {
        $registration = Registration::with('totals')
            ->where('referencia', $reference)
            ->firstOrFail();

        $tokenData = $this->qrService->generarToken();
        $token = $tokenData['token'] ?? $tokenData['data']['token'] ?? $tokenData['access_token'] ?? '';

        $qr = $this->qrService->generaQr($registration, $token);

        return response()->json([
            'success' => true,
            'message' => 'Código QR generado correctamente.',
            'data'    => $qr,
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

    /**
     * Actualizar una inscripción (solo si no está pagada).
     */
    public function update(UpdateRegistrationRequest $request, string $reference): JsonResponse
    {
        $registration = $this->service->update(
            $reference,
            $request->validated()
        );

        return response()->json([
            'success' => true,
            'message' => 'Inscripción actualizada correctamente.',
            'data' => new RegistrationCollectionResource($registration),
        ]);
    }

    /**
     * Actualizar inscripción pagada (con costo adicional).
     */
    public function updatePaid(UpdatePaidRegistrationRequest $request, string $reference): JsonResponse
    {
        $validated = $request->validated();
        $validated['_usuario'] = $request->user()?->email ?? $request->ip();

        $result = $this->service->updatePaidRegistration($reference, $validated);

        return response()->json([
            'success'       => true,
            'message'       => 'Inscripción pagada actualizada correctamente.',
            'costo_adicion' => $result['costo_adicion'],
            'data'          => new RegistrationCollectionResource($result['registration']),
        ]);
    }

    /**
     * Buscar inscripción por credenciales, evento y form_type.
     */
    public function lookup(LookupRegistrationRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $result = $this->service->lookupRegistration(
                $validated['email'],
                $validated['password'],
                $validated['evento_id'],
                $validated['form_type_id']
            );
        } catch (\DomainException $e) {
            return response()->json([
                'success' => false,
                'error'   => $e->getMessage(),
            ], 401);
        }

        $resource = $result['type'] === 'registration'
            ? new RegistrationCollectionResource($result['data'])
            : new PersonaResource($result['data']);

        $response = [
            'success' => true,
            'type'    => $result['type'],
            'data'    => $resource,
        ];

        if (isset($result['token'])) {
            $response['token'] = $result['token'];
        }

        return response()->json($response);
    }
}