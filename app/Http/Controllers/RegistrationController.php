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
use App\Http\Resources\ParticipanteResource;
use App\Models\Registration;
use App\Models\Participante;
use App\Models\Evento;
use App\Models\Category;
use App\Models\FormType;
use App\Services\RegistrationService;
use App\Services\QrService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Http\Controllers\Concerns\AuthorizesEventoScope;

class RegistrationController extends Controller
{
    use AuthorizesEventoScope;

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
     * Inscripciones de la persona autenticada (identificada por su
     * numero_documento o correo, igual criterio que lookupRegistration).
     * Usado por el header de login para ofrecer acceso directo a
     * inscripciones pendientes de pago tras loguearse.
     */
    public function mine(Request $request): JsonResponse
    {
        $persona = $request->user();

        $registrations = Registration::with([
            'totals',
            'participants.contactoEmergenciaParticipante',
            'participants.souvenirParticipante',
            'participants.answers',
        ])
            ->whereHas('participants', function ($q) use ($persona) {
                $q->where('numero_documento', $persona->numero_documento)
                  ->orWhere('correo', $persona->email);
            })
            ->when(
                $request->filled('pago_status'),
                fn($query) => $query->where('pago_status', $request->pago_status)
            )
            ->orderByDesc('fecha')
            ->get();

        return response()->json([
            'success' => true,
            'data' => RegistrationCollectionResource::collection($registrations),
        ]);
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
        try {
            $registration = $this->service->create($dto);
        } catch (\DomainException $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 422);
        }

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
     * Buscar la referencia de una inscripción a partir del pay_order_number
     * que le asignó una pasarela externa (Multipago). Llamada
     * server-to-server desde el callback de la pasarela, que solo recibe
     * ese identificador — no la referencia interna.
     */
    public function findByPayOrder(string $payOrderNumber): JsonResponse
    {
        $registration = Registration::where('pay_order_number', $payOrderNumber)->firstOrFail();

        return response()->json([
            'success' => true,
            'referencia' => $registration->referencia,
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
        try {
            $registration = $this->service->update(
                $reference,
                $request->validated()
            );
        } catch (\DomainException $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 422);
        }

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

        try {
            $result = $this->service->updatePaidRegistration($reference, $validated);
        } catch (\DomainException $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 422);
        }

        return response()->json([
            'success'       => true,
            'message'       => 'Inscripción pagada actualizada correctamente.',
            'costo_adicion' => $result['costo_adicion'],
            'data'          => new RegistrationCollectionResource($result['registration']),
        ]);
    }

    /**
     * Asignar/actualizar número de corredor y chip de un participante —
     * manual, uno por uno (staff en la entrega de kits). Para carga masiva
     * ver ParticipanteController::numeracionBulk.
     */
    public function updateNumeracion(Request $request, string $reference, int $participante): JsonResponse
    {
        $data = $request->validate([
            'numero_corredor' => ['nullable', 'string', 'max:50'],
            'chip'            => ['nullable', 'string', 'max:50'],
        ]);

        $registration = Registration::where('referencia', $reference)->firstOrFail();
        $this->assertCanWriteEvento($registration->evento_id);

        $participanteModel = Participante::where('registration_id', $registration->id)
            ->where('id', $participante)
            ->firstOrFail();

        $participanteModel->update($data);

        return response()->json([
            'success'      => true,
            'participante' => new ParticipanteResource($participanteModel),
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

    /**
     * Carga masiva de inscripciones desde el panel de administración —
     * ver brain/PLAN-REGISTRO-MANUAL-CSV-05082026.md. Alcance acordado:
     * solo super_admin, un evento + form_type + categoría fijos para
     * todo el archivo (elegidos una sola vez, no por fila), cada fila
     * del CSV se crea como **su propia inscripción independiente**
     * (referencia propia, `pago_status = pending`) — no una sola
     * inscripción grupal. Precio = solo precio de categoría + cargo de
     * servicio (5%), sin souvenirs/promo/donación/equipo/delivery (por
     * eso se rechaza de entrada un form_type con `has_team`, que exigiría
     * un equipo por participante). Reutiliza
     * `RegistrationService::create()` tal cual (mismas validaciones que
     * el registro online: documento duplicado, cupo, etc.) — por eso
     * cada participante importado también queda con su cuenta `Persona`
     * sincronizada automáticamente (`RegistrationService::syncPersonas()`),
     * sin trabajo extra acá.
     */
    public function importarBulk(Request $request, Evento $event): JsonResponse
    {
        $this->assertIsSuperAdmin();

        $data = $request->validate([
            'form_types_id' => ['required', 'integer'],
            'categoria' => ['required', 'string'],
            'participantes' => ['required', 'array', 'min:1'],
            'participantes.*.numero_documento' => ['required', 'string'],
            'participantes.*.tipo_documento' => ['required', 'string'],
            'participantes.*.nombre' => ['required', 'string'],
            'participantes.*.apellido' => ['required', 'string'],
            'participantes.*.alias' => ['nullable', 'string'],
            'participantes.*.genero' => ['required', 'string'],
            'participantes.*.fecha_nacimiento' => ['required', 'date'],
            'participantes.*.email' => ['required', 'email'],
            'participantes.*.direccion' => ['required', 'string'],
            'participantes.*.ciudad' => ['required', 'string'],
            'participantes.*.telefono' => ['required', 'string'],
            'participantes.*.contacto_emergencia_nombre' => ['required', 'string'],
            'participantes.*.contacto_emergencia_telefono' => ['required', 'string'],
            'participantes.*.contacto_emergencia_relacion' => ['required', 'string'],
        ]);

        $formType = FormType::where('id', $data['form_types_id'])
            ->where('event_id', $event->id)
            ->first();
        if (!$formType) {
            return response()->json(['success' => false, 'error' => 'El tipo de formulario no pertenece a este evento.'], 422);
        }
        if ($formType->has_team) {
            return response()->json(['success' => false, 'error' => 'Este tipo de formulario requiere equipo — la carga masiva simple no lo soporta.'], 422);
        }

        $category = Category::where('event_id', $event->id)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($data['categoria']))])
            ->first();
        if (!$category) {
            return response()->json(['success' => false, 'error' => "La categoría \"{$data['categoria']}\" no existe en este evento."], 422);
        }

        $precio = (float) $category->price;
        $fee = round($precio * 0.05, 2);
        $grandTotal = round($precio + $fee, 2);

        $creados = [];
        $errores = [];

        foreach ($data['participantes'] as $i => $row) {
            $fila = $i + 2; // +1 base 0→1, +1 fila de encabezado del CSV

            try {
                $nacimiento = Carbon::parse($row['fecha_nacimiento']);
                $referencia = 'LA-' . strtoupper(substr(md5(uniqid((string) mt_rand(), true)), 0, 8));

                $dto = \App\DTOs\RegistrationDTO::fromArray([
                    'referencia' => $referencia,
                    'fecha' => now()->toDateTimeString(),
                    'evento_id' => $event->id,
                    'evento_nombre' => $event->nombre,
                    'form_types_id' => $formType->id,
                    'tipo_pago' => 'pendiente',
                    'pago_status' => 'pending',
                    'pay_order_number' => null,
                    'totales' => [
                        'inscripcion' => $precio,
                        'donacion' => 0,
                        'souvenirs' => 0,
                        'fee' => $fee,
                        'descuento' => 0,
                        'descuento_registrante' => 0,
                        'grand_total' => $grandTotal,
                    ],
                    'participantes' => [[
                        'nombre' => $row['nombre'],
                        'apellido' => $row['apellido'],
                        'alias' => $row['alias'] ?? '',
                        'genero' => $row['genero'],
                        'tipoDocumento' => $row['tipo_documento'],
                        'numeroDocumento' => $row['numero_documento'],
                        'polera' => '',
                        'precioPolera' => 0,
                        'nacimiento' => ['dia' => $nacimiento->day, 'mes' => $nacimiento->month, 'anio' => $nacimiento->year],
                        'edad' => $nacimiento->age,
                        'correo' => $row['email'],
                        'direccion' => $row['direccion'],
                        'ciudad' => $row['ciudad'],
                        'telefono' => $row['telefono'],
                        'contacto_emergencia' => [
                            'nombre' => $row['contacto_emergencia_nombre'],
                            'celular' => $row['contacto_emergencia_telefono'],
                            'relacion' => $row['contacto_emergencia_relacion'],
                        ],
                        'souvenirs' => [],
                        'answers' => [],
                        'categoria' => $category->name,
                        'precioCategoria' => $precio,
                        'donacion' => 0,
                        'promoDescuento' => 0,
                        'promoCodigo' => '',
                        'subtotal' => $precio,
                    ]],
                ]);

                $this->service->create($dto);
                $creados[] = ['fila' => $fila, 'numero_documento' => $row['numero_documento'], 'referencia' => $referencia];
            } catch (\DomainException $e) {
                $errores[] = ['fila' => $fila, 'numero_documento' => $row['numero_documento'] ?? null, 'error' => $e->getMessage()];
            } catch (\Throwable $e) {
                $errores[] = ['fila' => $fila, 'numero_documento' => $row['numero_documento'] ?? null, 'error' => 'Error inesperado: ' . $e->getMessage()];
            }
        }

        return response()->json([
            'success' => true,
            'creados' => $creados,
            'errores' => $errores,
        ]);
    }
}