<?php

namespace App\Http\Controllers;

use App\Actions\ActualizarInscripcionAction;
use App\Actions\ActualizarInscripcionPagadaAction;
use App\Actions\AnularCobroAction;
use App\Actions\CrearInscripcionAction;
use App\DTOs\RegistrationDTO;
use App\Http\Controllers\Concerns\AuthorizesEventoScope;
use App\Http\Requests\AnularCobroRequest;
use App\Http\Requests\StoreInscripcionCajaRequest;
use App\Http\Requests\UpdatePaidRegistrationRequest;
use App\Http\Requests\UpdateRegistrationRequest;
use App\Http\Resources\CajaMovimientoResource;
use App\Http\Resources\RegistrationCollectionResource;
use App\Models\AdminUser;
use App\Models\CajaMovimiento;
use App\Models\CajaTurno;
use App\Models\Evento;
use App\Models\Persona;
use App\Models\Registration;
use App\Services\RegistrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Caja de cobro presencial — ver PLAN-CAJA-COBRO-PRESENCIAL-14082026.md.
 * Ningún endpoint reinventa cálculo de precios/stock/promo: todos
 * delegan a las Actions que ya usa el registro público
 * (CrearInscripcionAction/ActualizarInscripcionAction/
 * ActualizarInscripcionPagadaAction/RegistrationService::updatePaymentStatus).
 * Lo único nuevo acá es el rol, el turno, y el registro del movimiento de
 * caja.
 */
class CajaController extends Controller
{
    use AuthorizesEventoScope;

    public function __construct(
        private readonly RegistrationService $registrationService,
    ) {
    }

    /**
     * Búsqueda en vivo para el mostrador — por referencia, documento,
     * nombre o apellido, scoped al evento del cajero. Mismo patrón que
     * PosController::buscar() de elascenso/delivery, adaptado a
     * Registration.
     */
    public function buscar(Request $request, Evento $event): JsonResponse
    {
        $this->assertCanOperarCaja((int) $event->id);

        $data = $request->validate(['q' => ['required', 'string', 'min:2']]);
        $q = $data['q'];

        $registrations = Registration::where('evento_id', $event->id)
            ->whereNotIn('pago_status', ['cancelled'])
            ->where(function ($query) use ($q) {
                $query->where('referencia', 'like', "%{$q}%")
                    ->orWhereHas('participants', function ($p) use ($q) {
                        $p->where('numero_documento', 'like', "%{$q}%")
                            ->orWhere('nombre', 'like', "%{$q}%")
                            ->orWhere('apellido', 'like', "%{$q}%");
                    });
            })
            ->with([
                'participants.contactoEmergenciaParticipante',
                'participants.souvenirParticipante',
                'participants.answers',
                'totals',
                'formType',
            ])
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        return response()->json([
            'success' => true,
            'data'    => RegistrationCollectionResource::collection($registrations),
        ]);
    }

    /**
     * Prellenado desde `personas` (20/08/2026) — `personas` es la tabla
     * global (no por evento) que RegistrationService::syncPersonas()
     * mantiene al día con cada inscripción confirmada de CUALQUIER
     * evento. Sirve para no hacer retipear todo a alguien que ya se
     * inscribió antes a otro evento. Deliberadamente NO se usa para el
     * chequeo de "ya está inscrito en este evento" — ese sigue siendo
     * buscar() (scoped a `evento_id`); acá es solo prellenado de datos
     * personales, nunca bloquea nada.
     */
    public function buscarPersona(Request $request, Evento $event): JsonResponse
    {
        $this->assertCanOperarCaja((int) $event->id);

        $data = $request->validate(['numero_documento' => ['required', 'string', 'min:3']]);

        $persona = Persona::with('contactoEmergencia')
            ->where('numero_documento', $data['numero_documento'])
            ->first();

        if (!$persona) {
            return response()->json(['success' => true, 'data' => null]);
        }

        $ce = $persona->contactoEmergencia;

        return response()->json([
            'success' => true,
            'data'    => [
                'nombre'          => $persona->nombre,
                'apellido'        => $persona->apellido,
                'alias'           => $persona->alias,
                'genero'          => $persona->sexo,
                'tipoDocumento'   => $persona->tipo_documento,
                'numeroDocumento' => $persona->numero_documento,
                'correo'          => $persona->correo,
                'direccion'       => $persona->direccion,
                'ciudad'          => $persona->ciudad,
                'telefono'        => $persona->telefono,
                // fecha_nacimiento no tiene cast a fecha en el modelo
                // Persona (columna `dateTime` sin $casts) — se parsea acá
                // en vez de tocar el modelo, que puede tener otros
                // consumidores que ya esperan el string crudo.
                'nacimiento'      => $persona->fecha_nacimiento ? (function () use ($persona) {
                    $fecha = \Carbon\Carbon::parse($persona->fecha_nacimiento);
                    return ['dia' => (int) $fecha->format('d'), 'mes' => (int) $fecha->format('m'), 'anio' => (int) $fecha->format('Y')];
                })() : null,
                'contacto_emergencia' => $ce ? [
                    'nombre'   => $ce->nombre,
                    'celular'  => $ce->celular,
                    'relacion' => $ce->relacion,
                ] : null,
            ],
        ]);
    }

    /**
     * Alta de un participante nuevo desde el mostrador + cobro inmediato
     * en efectivo. Se crea `pending` vía CrearInscripcionAction (mismas
     * validaciones que el registro público) y de inmediato se confirma el
     * pago vía updatePaymentStatus() — así el e-ticket/email real sale
     * igual que siempre (CrearInscripcionAction solo notifica el caso
     * "pending", updatePaymentStatus() es quien dispara
     * notificarPagoConfirmado()).
     */
    public function inscripcion(StoreInscripcionCajaRequest $request, Evento $event, CrearInscripcionAction $action): JsonResponse
    {
        $admin = $this->assertCanOperarCaja((int) $event->id);
        $turno = $this->turnoAbierto($event, $admin);
        if (!$turno) {
            return $this->errorSinTurno();
        }

        $data = $request->validated();
        $referencia = 'CAJA-' . strtoupper(substr(md5(uniqid((string) mt_rand(), true)), 0, 8));

        $dto = RegistrationDTO::fromArray([
            'referencia'       => $referencia,
            'fecha'            => now()->toDateTimeString(),
            'evento_id'        => $event->id,
            'evento_nombre'    => $event->nombre,
            'form_types_id'    => $data['form_types_id'],
            // Método elegido en Caja (EFECTIVO, QR, DEPOSITO, ORGANIZADOR, CORTESIA):
            // se guarda también en la inscripción, no solo en el movimiento de caja.
            'tipo_pago'        => $data['metodo_pago'] ?? 'EFECTIVO',
            'pago_status'      => 'pending',
            'pay_order_number' => null,
            'totales'          => $data['totales'],
            'participantes'    => [$data['participante']],
            // Precio USD fijo en Caja (12/09/2026) — ver
            // CurrencyResolverData::resolverPrecioFijo(), que
            // CrearInscripcionAction::validateMonedaPago() ya invoca para
            // CUALQUIER caller (Caja incluida) cuando el evento es
            // usdPrecioFijo. `grand_total` en $data['totales'] sigue
            // siendo el bookkeeping en BOB de siempre — esto es el monto
            // real cobrado en efectivo cuando corresponde.
            'moneda_pago'          => $data['totales']['moneda_pago'] ?? null,
            'tipo_cambio_aplicado' => $data['totales']['tipo_cambio_aplicado'] ?? null,
            'total_pagado'         => $data['totales']['total_pagado'] ?? null,
        ]);

        try {
            $registration = $action->handle($dto);
        } catch (\DomainException $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 422);
        }

        // Cortesía (30/09/2026) — se aplica ANTES de crear el CajaMovimiento
        // (necesita el monto final ya en 0) pero DESPUÉS de que
        // CrearInscripcionAction ya consumió cupo/stock normalmente contra
        // los precios reales — ver aplicarCortesia() más abajo.
        $esCortesia = ($data['metodo_pago'] ?? null) === 'CORTESIA';
        if ($esCortesia) {
            $this->aplicarCortesia($registration);
        }

        CajaMovimiento::create([
            'caja_turno_id'    => $turno->id,
            'evento_id'        => $event->id,
            'registration_id'  => $registration->id,
            'admin_user_id'    => $admin->id,
            'tipo'             => 'inscripcion_nueva',
            'monto'            => $esCortesia ? 0 : (float) $data['totales']['grand_total'],
            // Método de pago en Caja (18/09/2026, ampliado 30/09/2026) — ver
            // StoreInscripcionCajaRequest para el detalle de cada uno.
            'metodo_pago'      => $data['metodo_pago'] ?? 'EFECTIVO',
        ]);

        $registration = $this->registrationService->updatePaymentStatus($registration->referencia, 'paid');

        return response()->json([
            'success' => true,
            'message' => 'Inscripción registrada y cobrada correctamente.',
            'data'    => new RegistrationCollectionResource($registration),
        ], 201);
    }

    /**
     * Cobra (efectivo, QR, depósito, organizador o cortesía — 18/09/2026,
     * ampliado 30/09/2026) una inscripción `pending` existente (creada
     * online o por caja antes).
     */
    public function cobrarPendiente(Request $request, string $reference): JsonResponse
    {
        $data = $request->validate([
            'metodo_pago' => ['nullable', 'string', Rule::in(['EFECTIVO', 'QR', 'DEPOSITO', 'ORGANIZADOR', 'CORTESIA'])],
        ]);

        $registration = Registration::with('totals')->where('referencia', $reference)->firstOrFail();
        $event = Evento::findOrFail($registration->evento_id);

        $admin = $this->assertCanOperarCaja((int) $event->id);
        $turno = $this->turnoAbierto($event, $admin);
        if (!$turno) {
            return $this->errorSinTurno();
        }

        if ($registration->pago_status !== 'pending') {
            return response()->json([
                'success' => false,
                'error'   => 'Esta inscripción no está pendiente de pago.',
            ], 422);
        }

        // Cortesía (30/09/2026) — el monto ya persistido (RegistrationTotal,
        // categoría/talleres reales elegidos previamente) se pone en 0
        // ANTES de leer $registration->totals->grand_total para el
        // movimiento, mismo criterio que en inscripcion().
        $esCortesia = ($data['metodo_pago'] ?? null) === 'CORTESIA';
        if ($esCortesia) {
            $this->aplicarCortesia($registration);
            $registration->load('totals');
        }

        CajaMovimiento::create([
            'caja_turno_id'    => $turno->id,
            'evento_id'        => $event->id,
            'registration_id'  => $registration->id,
            'admin_user_id'    => $admin->id,
            'tipo'             => 'cobro_pendiente',
            'monto'            => (float) ($registration->totals?->grand_total ?? 0),
            'metodo_pago'      => $data['metodo_pago'] ?? 'EFECTIVO',
        ]);

        // El cobro en Caja define cómo se pagó: el tipo de pago de la inscripción
        // queda con el método elegido, no con el que tenía al crearse (pendiente).
        $registration->update(['tipo_pago' => $data['metodo_pago'] ?? 'EFECTIVO']);

        $registration = $this->registrationService->updatePaymentStatus($reference, 'paid');

        return response()->json([
            'success' => true,
            'message' => 'Cobro registrado correctamente.',
            'data'    => new RegistrationCollectionResource($registration),
        ]);
    }

    /**
     * Cortesía (30/09/2026) — el organizador regala la inscripción: el
     * total pasa a ser $0 sin importar qué categoría/talleres se hayan
     * elegido. Se aplica DESPUÉS de crear/cobrar la inscripción con los
     * precios reales (para que cupo/stock/numeración se consuman
     * normalmente, igual que cualquier inscripción real) — este paso solo
     * pone en 0 los campos de dinero; qué se eligió (categoría, talleres,
     * souvenirs) queda intacto para kit/certificado/reportes.
     */
    private function aplicarCortesia(Registration $registration): void
    {
        $registration->totals()->update([
            'inscripcion' => 0, 'donacion' => 0, 'souvenirs' => 0, 'talleres' => 0,
            'fee' => 0, 'descuento' => 0, 'descuento_registrante' => 0, 'grand_total' => 0,
        ]);
        $registration->update(['total_pagado' => null]);

        $participanteIds = $registration->participants()->pluck('id');
        if ($participanteIds->isEmpty()) {
            return;
        }

        \App\Models\Participante::whereIn('id', $participanteIds)->update([
            'precio_categoria' => 0, 'subtotal' => 0, 'donacion' => 0, 'promo_descuento' => 0,
        ]);
        \App\Models\ParticipanteTallerSesion::whereIn('participante_id', $participanteIds)
            ->update(['unit_price' => 0, 'discount' => 0, 'total' => 0]);
        \App\Models\SouvenirParticipante::whereIn('participante_id', $participanteIds)
            ->update(['precio' => 0]);
    }

    /**
     * Edita cualquier campo de una inscripción `pending` — reusa
     * ActualizarInscripcionAction tal cual (no cobra nada por sí sola: si
     * el cajero también quiere cobrarla, llama cobrarPendiente() aparte,
     * así que no exige turno abierto acá).
     */
    public function editarPendiente(UpdateRegistrationRequest $request, string $reference, ActualizarInscripcionAction $action): JsonResponse
    {
        $registration = Registration::where('referencia', $reference)->firstOrFail();
        $this->assertCanOperarCaja((int) $registration->evento_id);

        try {
            $registration = $action->handle($reference, $request->validated());
        } catch (\DomainException $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Inscripción actualizada correctamente.',
            'data'    => new RegistrationCollectionResource($registration),
        ]);
    }

    /**
     * Edita una inscripción `paid` y cobra/desembolsa el adicional real —
     * reusa ActualizarInscripcionPagadaAction tal cual, que ya calcula ese
     * monto (costo_edicion fijo + diferencia real de talleres/categoría/
     * souvenirs). A diferencia del autoservicio
     * (RegistrationController::updatePaid(), 'solo_subida' — ver
     * EdicionPagadaCategoriaData), la caja SÍ puede cambiar de categoría en
     * cualquier dirección (modoCategoria='libre') porque puede desembolsar
     * la diferencia en efectivo ahí mismo — 25/08/2026, ver
     * PLAN-EDICION-PAGADA-TALLERES-CATEGORIA-25082026.md.
     */
    public function editarPagada(UpdatePaidRegistrationRequest $request, string $reference, ActualizarInscripcionPagadaAction $action): JsonResponse
    {
        $registration = Registration::where('referencia', $reference)->firstOrFail();
        $event = Evento::findOrFail($registration->evento_id);

        $admin = $this->assertCanOperarCaja((int) $event->id);
        $turno = $this->turnoAbierto($event, $admin);
        if (!$turno) {
            return $this->errorSinTurno();
        }

        try {
            // requierePagoEnSitio se deja en su default (false): el
            // cajero cobra/desembolsa en efectivo en el momento, así que
            // cualquier taller/souvenir nuevo agregado acá ya está cobrado
            // (ver ActualizarInscripcionPagadaAction::handle()).
            // permiteQuitarTalleres: true (29/09/2026) — Caja es el único
            // flujo que puede quitar un taller ya pagado (con o sin
            // reemplazo); la Action exige $data['motivo'] cuando eso pasa
            // de verdad.
            $result = $action->handle(
                $reference,
                $request->validated() + ['_usuario' => $admin->email],
                modoCategoria: 'libre',
                permiteQuitarTalleres: true,
            );
        } catch (\DomainException $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 422);
        }

        // != 0 en vez de > 0 (25/08/2026) — un cambio a categoría más
        // barata da un costo_adicion negativo (desembolso real al
        // participante) que también debe quedar registrado. `monto` ya es
        // decimal con signo y CajaTurno::sum('monto') ya resta un negativo
        // correctamente al calcular monto_esperado, sin cambios ahí.
        if (abs((float) $result['costo_adicion']) > 0.001) {
            CajaMovimiento::create([
                'caja_turno_id'    => $turno->id,
                'evento_id'        => $event->id,
                'registration_id'  => $result['registration']->id,
                'admin_user_id'    => $admin->id,
                'tipo'             => 'edicion_pagada',
                'monto'            => (float) $result['costo_adicion'],
                'metodo_pago'      => $request->validated()['metodo_pago'] ?? 'EFECTIVO',
                'motivo'           => $request->validated()['motivo'] ?? null,
            ]);
        }

        return response()->json([
            'success'       => true,
            'message'       => 'Inscripción actualizada y adicional cobrado correctamente.',
            'costo_adicion' => (float) $result['costo_adicion'],
            'data'          => new RegistrationCollectionResource($result['registration']),
        ]);
    }

    /**
     * Historia de movimientos de caja de una inscripción (02/10/2026) — para
     * que el cajero elija cuál anular. `anulacion` eager-cargada para que
     * `CajaMovimientoResource::anulable` no dispare una query por fila.
     */
    public function movimientos(Request $request, string $reference): JsonResponse
    {
        $registration = Registration::where('referencia', $reference)->firstOrFail();
        $this->assertCanOperarCaja((int) $registration->evento_id);

        $movimientos = CajaMovimiento::where('registration_id', $registration->id)
            ->with('anulacion')
            ->orderBy('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => CajaMovimientoResource::collection($movimientos),
        ]);
    }

    /**
     * Anular un cobro ya registrado (02/10/2026) — ver AnularCobroAction.
     * Mismo guard que editarPagada()/cobrarPendiente(): turno abierto
     * obligatorio, es un movimiento de dinero real.
     */
    public function anularCobro(AnularCobroRequest $request, string $reference, AnularCobroAction $action): JsonResponse
    {
        $registration = Registration::where('referencia', $reference)->firstOrFail();
        $event = Evento::findOrFail($registration->evento_id);

        $admin = $this->assertCanOperarCaja((int) $event->id);
        $turno = $this->turnoAbierto($event, $admin);
        if (!$turno) {
            return $this->errorSinTurno();
        }

        try {
            $data = $request->validated();
            $action->handle($registration, (int) $data['movimiento_id'], $data['motivo'], $admin, $turno);
        } catch (\DomainException $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Cobro anulado — la inscripción quedó cancelada y el cupo fue liberado.',
        ]);
    }

    private function turnoAbierto(Evento $event, AdminUser $admin): ?CajaTurno
    {
        return CajaTurno::where('evento_id', $event->id)
            ->where('admin_user_id', $admin->id)
            ->where('estado', 'abierto')
            ->first();
    }

    private function errorSinTurno(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error'   => 'Abrí un turno de caja antes de cobrar.',
        ], 422);
    }
}
