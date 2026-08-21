<?php

namespace App\Http\Controllers;

use App\Actions\ActualizarInscripcionAction;
use App\Actions\ActualizarInscripcionPagadaAction;
use App\Actions\CrearInscripcionAction;
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
    public function store(StoreRegistrationRequest $request, CrearInscripcionAction $action): JsonResponse
    {
        $dto = RegistrationDTO::fromArray(
            $request->validated()[0]
        );
       // dd( $request->validated()[0]);
        try {
            $registration = $action->handle($dto);
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
           // Caja para eventos tipo congreso (20/08/2026) — faltaba, así
           // que editar una inscripción con talleres desde Caja no los
           // prellenaba (mismo gap ya documentado para souvenirs, ver
           // caja/editar.blade.php).
           'participants.talleresSesiones',
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
    public function update(UpdateRegistrationRequest $request, string $reference, ActualizarInscripcionAction $action): JsonResponse
    {
        try {
            $registration = $action->handle(
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
    public function updatePaid(UpdatePaidRegistrationRequest $request, string $reference, ActualizarInscripcionPagadaAction $action): JsonResponse
    {
        $validated = $request->validated();
        $validated['_usuario'] = $request->user()?->email ?? $request->ip();

        try {
            $result = $action->handle($reference, $validated);
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
     * Buscar una inscripción por referencia para acreditación (check-in) —
     * panel de administración, escaneando el QR que ya se manda en el
     * e-ticket/email (solo codifica la referencia, ver ReferenceQrService).
     *
     * A propósito NO reusa show(): ese endpoint es público y no confirma
     * que la referencia sea de ESTE evento — acá si la referencia existe
     * pero es de otro evento, se trata igual que "no existe" (404), para
     * no filtrar datos de un evento ajeno a través de un QR equivocado.
     */
    public function checkinLookup(Request $request, Evento $event, string $reference): JsonResponse
    {
        $this->assertCanWriteEvento($event->id);

        $registration = Registration::with('participants')
            ->where('referencia', $reference)
            ->where('evento_id', $event->id)
            ->firstOrFail();

        $categoriasPorId = Category::where('event_id', $event->id)->get()->keyBy(fn ($c) => (string) $c->id);

        return response()->json([
            'success'     => true,
            'referencia'  => $registration->referencia,
            'pagoStatus'  => $registration->pago_status,
            'participantes' => $registration->participants->map(fn (Participante $p) => [
                'id'           => $p->id,
                'nombre'       => $p->nombre,
                'apellido'     => $p->apellido,
                'categoria'    => $categoriasPorId->get($p->categoria)->name ?? $p->categoria,
                'numeroCorredor' => $p->numero_corredor,
                'chip'         => $p->chip,
                'checkedInAt'  => optional($p->checked_in_at)->toIso8601String(),
            ]),
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
     * `App\Actions\CrearInscripcionAction` tal cual (mismas validaciones
     * que el registro online: documento duplicado, cupo, etc.) — por eso
     * cada participante importado también queda con su cuenta `Persona`
     * sincronizada automáticamente (`RegistrationService::syncPersonas()`),
     * sin trabajo extra acá.
     */
    public function importarBulk(Request $request, Evento $event, CrearInscripcionAction $action): JsonResponse
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
            // Talleres (21/08/2026) — opcional, string cruda del CSV (uno o
            // más nombres de taller separados por ';'); se resuelve fila
            // por fila en resolverTalleresFila() para que un nombre
            // inválido/ambiguo rechace SOLO esa fila, no todo el archivo.
            'participantes.*.talleres' => ['nullable', 'string'],
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
        // Precios por período (12/08/2026) — ver PRD-precios-periodos-fechas.md,
        // sección 0. Esta carga masiva elige la categoría por nombre — no
        // tiene forma de representar "sin categoría" en el CSV — así que
        // no aplica a un form_type con `requiere_categoria=false` (ese
        // precio sale de `precio_base`, sin categoría que buscar).
        if (!$formType->requiere_categoria) {
            return response()->json(['success' => false, 'error' => 'Este tipo de formulario no requiere categoría — la carga masiva por categoría no aplica.'], 422);
        }

        $category = Category::where('event_id', $event->id)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($data['categoria']))])
            ->first();
        if (!$category) {
            return response()->json(['success' => false, 'error' => "La categoría \"{$data['categoria']}\" no existe en este evento."], 422);
        }

        // Precio vigente (períodos), no el `price` crudo — mismo criterio
        // que el registro online, así CrearInscripcionAction::validatePrecioCategoria()
        // no rechaza esta importación cuando la categoría tiene un
        // período de precio activo.
        $precio = \App\Support\PrecioVigenteData::paraCategoria($category)['precio'];

        $creados = [];
        $errores = [];

        foreach ($data['participantes'] as $i => $row) {
            $fila = $i + 2; // +1 base 0→1, +1 fila de encabezado del CSV

            try {
                // Talleres (21/08/2026) — ver
                // brain/api_rest_event/PLAN-CARGA-MASIVA-TALLERES-21082026.md.
                // Se resuelve ANTES de armar el DTO, dentro del mismo
                // try/catch de la fila: un nombre de taller inválido o
                // ambiguo (más de un horario) rechaza solo esta fila, igual
                // que cualquier otro error de datos del participante — no
                // tira abajo el resto del archivo.
                [$talleresFila, $totalTalleres] = $this->resolverTalleresFila($event, $row['talleres'] ?? '');

                // El total de talleres varía por fila (cada participante
                // puede elegir talleres distintos) — a diferencia de
                // `$precio` (mismo para todo el archivo), fee/grand_total
                // se recalculan acá adentro del loop. Antes esto usaba un
                // 5% hardcodeado en vez de `$event->fee_pct` — bug latente
                // preexistente para cualquier evento con un cargo de
                // servicio distinto del default, arreglado de paso porque
                // fee_incluye_talleres ya obliga a tocar este cálculo.
                $feeBase = $precio + ($event->fee_incluye_talleres ? $totalTalleres : 0);
                $fee = round($feeBase * (float) $event->fee_pct, 2);
                $grandTotal = round($precio + $totalTalleres + $fee, 2);

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
                        'talleres' => $totalTalleres,
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
                        'talleres' => $talleresFila,
                        // El registro online (elascenso/event) guarda el ID de
                        // categoría en `participantes.categoria`, no el nombre
                        // — acá se elige la categoría por nombre (más legible
                        // en el <select> del panel) pero hay que persistir el
                        // ID para que el filtro de Numeración/Participantes
                        // (que compara por ID) encuentre también a estos
                        // participantes. Bug real: antes se guardaba
                        // `$category->name`, por eso el filtro solo
                        // "funcionaba" con las filas cargadas por este mismo
                        // camino y aparecía roto para el resto (ver
                        // elascenso/event/brain/BUG-FILTRO-CATEGORIA-NUMERACION-10082026.md,
                        // en el repo hermano — ApiRestEvent no tiene su
                        // propia carpeta brain/ con documentación).
                        'categoria' => (string) $category->id,
                        'precioCategoria' => $precio,
                        'donacion' => 0,
                        'promoDescuento' => 0,
                        'promoCodigo' => '',
                        'subtotal' => $precio,
                    ]],
                ]);

                $action->handle($dto);
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

    /**
     * Resuelve la celda "talleres" del CSV (nombres de taller separados
     * por ';', case-insensitive, opcional — vacío = sin talleres) a la
     * forma que espera `ParticipantDTO`/`TallerSesionDTO`
     * (`taller_id`/`sesion_congreso_id`), más el total en Bs a sumar al
     * `grand_total` de esta fila. El precio real que se persiste lo
     * vuelve a calcular `ValidarSeleccionesTallerAction`/
     * `ResolverPrecioTallerData` dentro de `CrearInscripcionAction`
     * (fuente de verdad, igual que `precioCategoria`) — este total es
     * solo para que `validateFeePct()` no rechace la fila por un
     * `grand_total`/`fee` que no cuadra.
     *
     * Cada nombre tiene que resolver a un taller con EXACTAMENTE una
     * sesión activa: un CSV solo puede nombrar el taller, no elegir entre
     * varios horarios — si el taller tiene 0 sesiones activas (mal
     * configurado) o más de una (varios horarios posibles), no hay forma
     * de saber cuál sin ambigüedad, y se rechaza la fila pidiendo cargarla
     * por el formulario público o el panel en su lugar. Ver
     * brain/api_rest_event/PLAN-CARGA-MASIVA-TALLERES-21082026.md.
     *
     * @return array{0: array<int, array{taller_id:int, sesion_congreso_id:int}>, 1: float}
     */
    private function resolverTalleresFila(Evento $event, string $celda): array
    {
        $nombres = array_values(array_filter(
            array_map('trim', explode(';', $celda)),
            fn ($n) => $n !== ''
        ));

        if (empty($nombres)) {
            return [[], 0.0];
        }

        $talleres = [];
        $total = 0.0;

        foreach ($nombres as $nombre) {
            $taller = \App\Models\Taller::where('evento_id', $event->id)
                ->whereRaw('LOWER(nombre) = ?', [mb_strtolower($nombre)])
                ->first();

            if (!$taller) {
                throw new \DomainException("El taller \"{$nombre}\" no existe en este evento.");
            }
            if (!$taller->activo) {
                throw new \DomainException("El taller \"{$nombre}\" no está activo.");
            }

            $sesionesActivas = $taller->sesiones()->where('activa', true)->get();

            if ($sesionesActivas->isEmpty()) {
                throw new \DomainException("El taller \"{$nombre}\" no tiene ninguna sesión activa configurada.");
            }
            if ($sesionesActivas->count() > 1) {
                throw new \DomainException("El taller \"{$nombre}\" tiene más de un horario — no se puede identificar por nombre en el CSV, cargá esta fila desde el formulario público o el panel.");
            }

            $sesion = $sesionesActivas->first();

            $talleres[] = ['taller_id' => $taller->id, 'sesion_congreso_id' => $sesion->id];
            $total += \App\Support\Taller\ResolverPrecioTallerData::total($taller, $sesion, $event);
        }

        return [$talleres, round($total, 2)];
    }
}