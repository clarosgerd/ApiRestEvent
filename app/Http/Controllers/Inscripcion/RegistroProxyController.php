<?php

namespace App\Http\Controllers\Inscripcion;

use App\Actions\ActualizarInscripcionAction;
use App\Actions\ActualizarInscripcionPagadaAction;
use App\Actions\CrearInscripcionAction;
use App\DTOs\RegistrationDTO;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Inscripcion\Concerns\ResolvesFormRequests;
use App\Http\Requests\StoreRegistrationRequest;
use App\Http\Requests\UpdatePaidRegistrationRequest;
use App\Http\Requests\UpdateRegistrationRequest;
use App\Services\QrProviderService;
use App\Services\RegistroValidacionService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Consolidación monolito (22/08/2026), Fase 2b — reemplaza
 * `elascenso-blade\Api\RegistroProxyController::store()/update()/
 * marcarPagada()` (lookup/mine/misResultados ya se portaron en Fase 2a como
 * rutas directas, ver routes/inscripcion.php).
 *
 * Enfoque confirmado con el usuario: `RegistroValidacionService` se
 * mantiene como capa de prevalidación/UX temprana (mensajes de error
 * amigables antes de tocar la Action real), pero el `forward('POST',
 * '/registrations', ...)` final de cada método se reemplaza por invocar
 * directo `CrearInscripcionAction`/`ActualizarInscripcionAction`/
 * `ActualizarInscripcionPagadaAction` — las mismas que usa
 * `RegistrationController` en `/api/v1/registrations`, in-process, con el
 * FormRequest correspondiente resuelto a mano (mismo mecanismo que
 * `Admin\Concerns\DelegatesToApiJson::mergeAndValidate()`, ver
 * `Concerns\ResolvesFormRequests`).
 */
class RegistroProxyController extends Controller
{
    use ResolvesFormRequests;

    public function __construct(
        private readonly RegistroValidacionService $validacion,
        private readonly QrProviderService $qr,
    ) {
    }

    public function store(Request $request): JsonResponse
    {
        $eventoId = trim((string) $request->input('evento_id', ''));
        $formTypeId = $request->input('form_type_id');
        $tipoPago = trim((string) $request->input('tipoPago', ''));
        $participantes = $request->input('participantes', []);
        $authToken = trim((string) $request->input('auth_token', ''));
        $monedaPago = strtoupper(trim((string) $request->input('moneda_pago', 'BOB')));

        if ($eventoId === '') {
            return response()->json(['error' => 'Falta el ID del evento.'], 400);
        }
        if ($tipoPago === '') {
            return response()->json(['error' => 'Falta el tipo de pago.'], 400);
        }
        if (empty($participantes) || ! is_array($participantes)) {
            return response()->json(['error' => 'Se requiere al menos un participante.'], 400);
        }
        if ($authToken === '') {
            return response()->json(['error' => 'Debes iniciar sesión para confirmar tu inscripción.'], 400);
        }
        if (! in_array($monedaPago, ['BOB', 'USD'], true)) {
            return response()->json(['error' => "Moneda de cobro no soportada: '{$monedaPago}'."], 400);
        }

        $personaRegistrante = $this->validacion->resolverPersonaRegistrante($authToken);
        if (! $personaRegistrante) {
            return response()->json(['error' => 'Tu sesión expiró o no es válida. Inicia sesión nuevamente.'], 401);
        }

        $evento = $this->validacion->fetchExternalEvento($eventoId);
        if (! $evento) {
            return response()->json(['error' => 'Evento no encontrado.'], 404);
        }

        $formaPagoSeleccionada = null;
        foreach (($evento['formasPago'] ?? []) as $fp) {
            if (($fp['slug'] ?? '') === $tipoPago) {
                $formaPagoSeleccionada = $fp;
                break;
            }
        }
        if (! $formaPagoSeleccionada) {
            return response()->json(['error' => 'Método de pago no válido para este evento.'], 422);
        }

        $pasarelasSoportadas = ['sip', 'multipago'];
        if (($formaPagoSeleccionada['tipo'] ?? null) === 'integrado' && ! in_array($formaPagoSeleccionada['pasarela'] ?? '', $pasarelasSoportadas, true)) {
            return response()->json(['error' => 'Este método de pago no está disponible todavía.'], 503);
        }

        if ($monedaPago === 'USD' && empty($evento['aceptaUsd'])) {
            return response()->json(['error' => 'Este evento solo acepta pago en BOB. Recargá la página e intentá de nuevo.'], 422);
        }
        if ($monedaPago === 'USD' && ! in_array($formaPagoSeleccionada['pasarela'] ?? '', $pasarelasSoportadas, true)) {
            return response()->json(['error' => 'Para pagar en USD elegí QR (SIP) o Multipago. Los métodos manuales solo aceptan BOB.'], 422);
        }

        $ftResult = $this->validacion->resolverFormType($evento, $formTypeId);
        if (isset($ftResult['error'])) {
            return response()->json(['error' => $ftResult['error']], 422);
        }
        $formType = $ftResult['formType'];

        $calc = $this->validacion->validarYCalcularParticipantes($evento, $formType, $participantes);
        if (isset($calc['error'])) {
            return response()->json(['error' => $calc['error']], 422);
        }

        $tipoCambioAplicado = null;
        $totalPagado = null;
        $monedaParaPasarela = 'BOB';
        if ($monedaPago === 'USD') {
            if (! empty($evento['usdPrecioFijo'])) {
                $usdFijo = $this->validacion->calcularTotalUsdFijo($evento, $calc['participantes']);
                if (isset($usdFijo['error'])) {
                    return response()->json(['error' => $usdFijo['error']], 422);
                }
                $feePct = isset($evento['fee_pct']) ? (float) $evento['fee_pct'] : 0.05;
                $feeIncluyeTalleresUsd = $evento['feeIncluyeTalleres'] ?? true;
                $baseFeeUsd = $usdFijo['total'] - ($feeIncluyeTalleresUsd ? 0 : $usdFijo['totalTalleres']);
                $totalPagado = round($usdFijo['total'] + round($baseFeeUsd * $feePct, 2), 2);
                $monedaParaPasarela = 'USD';
            } else {
                $cached = Cache::get(TipoCambioController::CACHE_KEY);
                $tasa = (float) ($cached['rates']['USD'] ?? 0);
                if ($tasa <= 0) {
                    return response()->json(['error' => 'No se pudo obtener el tipo de cambio USD. Intentá de nuevo en unos minutos.'], 503);
                }
                $tipoCambioAplicado = $tasa;
                $totalPagado = round($calc['totales']['grand_total'] * $tasa, 2);
                $monedaParaPasarela = 'USD';
            }
        }
        $montoParaPasarela = $monedaParaPasarela === 'USD' ? $totalPagado : $calc['totales']['grand_total'];

        $ref = 'LA-'.strtoupper(substr(md5(uniqid((string) mt_rand(), true)), 0, 8));
        $fechaRegistro = now()->format('Y-m-d H:i:s');
        $qrProvider = $this->qr->provider();
        $pasarela = $formaPagoSeleccionada['pasarela'] ?? null;

        $sipQrData = null;
        if ($pasarela === 'sip' && $qrProvider === 'sip') {
            $sip = $this->qr->sipClient();
            if ($sip) {
                try {
                    $qrResult = $sip['client']->generarQr(
                        alias: $ref,
                        detalleGlosa: $evento['name'] ?? 'Inscripcion Evento',
                        monto: $montoParaPasarela,
                        moneda: $monedaParaPasarela,
                        fechaVencimiento: new \DateTimeImmutable('+3 days'),
                    );
                    $sipQrData = ['imagenQr' => $qrResult['imagenQr'], 'idQr' => $qrResult['idQr']];
                } catch (\Throwable $e) {
                    Log::error('[SIP] Error generando QR para referencia '.$ref.': '.$e->getMessage());

                    return response()->json(['error' => 'No se pudo generar el código QR de pago. Intente nuevamente en unos minutos.'], 502);
                }
            }
        }

        $mpOrder = null;
        if ($pasarela === 'multipago') {
            $mp = $this->qr->multipagoClient();
            if ($mp) {
                try {
                    $registrante = $calc['participantes'][0] ?? [];
                    $mpOrder = $mp['client']->createPayOrder(
                        serviceCode: $mp['config']->multipagoServiceCode,
                        items: [[
                            'id' => 1,
                            'unitaryPrice' => $montoParaPasarela,
                            'quantity' => 1,
                            'description' => substr($evento['name'] ?? 'Inscripcion Evento', 0, 60),
                        ]],
                        client: [
                            'name' => $registrante['nombre'] ?? '',
                            'lastName' => $registrante['apellido'] ?? '',
                            'phone' => $registrante['telefono'] ?? '',
                            'email' => $registrante['correo'] ?? '',
                        ],
                    );
                } catch (\Throwable $e) {
                    Log::error('[Multipago] Error creando orden de pago para referencia '.$ref.': '.$e->getMessage());

                    return response()->json(['error' => 'No se pudo iniciar el pago con Multipago. Intente nuevamente en unos minutos.'], 502);
                }
            }
        }

        $registro = [
            'referencia' => $ref,
            'fecha' => $fechaRegistro,
            'evento_id' => $eventoId,
            'form_types_id' => (int) $formTypeId,
            'evento_nombre' => $evento['name'],
            'tipo_pago' => $tipoPago,
            'pago_status' => 'pending',
            'qr_id' => $sipQrData['idQr'] ?? null,
            'pay_order_number' => isset($mpOrder['payOrderNumber']) ? (string) $mpOrder['payOrderNumber'] : null,
            'persona_registrante' => [
                'id' => $personaRegistrante['id'] ?? null,
                'correo' => $personaRegistrante['correo'] ?? $personaRegistrante['email'] ?? null,
            ],
            'totales' => $calc['totales'],
            'participantes' => $calc['participantes'],
            'moneda_pago' => $monedaPago,
            'tipo_cambio_aplicado' => $tipoCambioAplicado,
            'total_pagado' => $totalPagado,
        ];

        // Fase 2b — acá terminaba el forward('POST', '/registrations', ...)
        // HTTP real; ahora se resuelve el mismo StoreRegistrationRequest a
        // mano (envolvente `[0 => $registro]`, así lo exige su regla
        // `'0' => ['required','array']`) y se llama la Action directo.
        try {
            $formRequest = $this->resolveWithBody(StoreRegistrationRequest::class, $request, [$registro]);
            $dto = RegistrationDTO::fromArray($formRequest->validated()[0]);
            $registration = app(CrearInscripcionAction::class)->handle($dto);
        } catch (\DomainException $e) {
            // Self-heal: si el reintento chocó con su propia referencia (el
            // intento anterior en realidad sí se había guardado, solo tardó
            // más de lo esperado), no es un error real.
            $refPropioColisiono = str_contains($e->getMessage(), "referencia {$ref} ya existe")
                && $this->validacion->fetchExternalRegistro($ref) !== null;

            if (! $refPropioColisiono) {
                return response()->json(['error' => $e->getMessage()], 422);
            }
        }

        $newQrImage = null;
        if ($pasarela === 'sip' && $qrProvider === 'new') {
            $newQrImage = $this->qr->generateNew($ref);
        }

        return response()->json([
            'success' => true,
            'referencia' => $ref,
            'fecha' => $fechaRegistro,
            'totales' => $registro['totales'],
            'qr_image' => $sipQrData['imagenQr'] ?? $newQrImage,
            'url_to_pay' => $mpOrder['urlToPay'] ?? null,
            'message' => '¡Registro confirmado exitosamente!',
        ]);
    }

    public function update(Request $request, string $reference): JsonResponse
    {
        $eventoId = trim((string) $request->input('evento_id', ''));
        $formTypeId = $request->input('form_type_id');
        $participantes = $request->input('participantes', []);

        if ($eventoId === '') {
            return response()->json(['error' => 'Falta el ID del evento.'], 400);
        }
        if (empty($participantes) || ! is_array($participantes)) {
            return response()->json(['error' => 'Se requiere al menos un participante.'], 400);
        }

        $evento = $this->validacion->fetchExternalEvento($eventoId);
        if (! $evento) {
            return response()->json(['error' => 'Evento no encontrado.'], 404);
        }

        $ftResult = $this->validacion->resolverFormType($evento, $formTypeId);
        if (isset($ftResult['error'])) {
            return response()->json(['error' => $ftResult['error']], 422);
        }

        $calc = $this->validacion->validarYCalcularParticipantes($evento, $ftResult['formType'], $participantes);
        if (isset($calc['error'])) {
            return response()->json(['error' => $calc['error']], 422);
        }

        try {
            $formRequest = $this->resolveWithBody(UpdateRegistrationRequest::class, $request, [
                'participantes' => $calc['participantes'],
                'totales' => $calc['totales'],
            ]);
            app(ActualizarInscripcionAction::class)->handle($reference, $formRequest->validated());
        } catch (ModelNotFoundException) {
            return response()->json(['error' => 'Inscripción no encontrada.'], 404);
        } catch (\DomainException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'referencia' => $reference,
            'totales' => $calc['totales'],
            'message' => '¡Inscripción actualizada exitosamente!',
        ]);
    }

    public function marcarPagada(Request $request, string $reference): JsonResponse
    {
        $eventoId = trim((string) $request->input('evento_id', ''));
        $formTypeId = $request->input('form_type_id');
        $participantes = $request->input('participantes', []);
        $confirmacion = (bool) $request->input('confirmacion', false);

        if ($eventoId === '') {
            return response()->json(['error' => 'Falta el ID del evento.'], 400);
        }
        if (empty($participantes) || ! is_array($participantes)) {
            return response()->json(['error' => 'Se requiere al menos un participante.'], 400);
        }
        if (! $confirmacion) {
            return response()->json(['error' => 'Debe confirmar el costo adicional para modificar una inscripción pagada.'], 422);
        }

        $evento = $this->validacion->fetchExternalEvento($eventoId);
        if (! $evento) {
            return response()->json(['error' => 'Evento no encontrado.'], 404);
        }

        $ftResult = $this->validacion->resolverFormType($evento, $formTypeId);
        if (isset($ftResult['error'])) {
            return response()->json(['error' => $ftResult['error']], 422);
        }

        $calc = $this->validacion->validarYCalcularParticipantes($evento, $ftResult['formType'], $participantes);
        if (isset($calc['error'])) {
            return response()->json(['error' => $calc['error']], 422);
        }

        try {
            $formRequest = $this->resolveWithBody(UpdatePaidRegistrationRequest::class, $request, [
                'participantes' => $calc['participantes'],
                'totales' => $calc['totales'],
                'confirmacion' => true,
            ]);
            $validated = $formRequest->validated();
            $validated['_usuario'] = $request->user()?->email ?? $request->ip();
            $result = app(ActualizarInscripcionPagadaAction::class)->handle($reference, $validated);
        } catch (ModelNotFoundException) {
            return response()->json(['error' => 'Inscripción no encontrada.'], 404);
        } catch (\DomainException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'referencia' => $reference,
            'totales' => $calc['totales'],
            'costo_adicion' => (float) ($result['costo_adicion'] ?? 0),
            'message' => '¡Inscripción actualizada exitosamente!',
        ]);
    }
}
