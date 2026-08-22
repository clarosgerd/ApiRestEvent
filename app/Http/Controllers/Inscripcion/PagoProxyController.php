<?php

namespace App\Http\Controllers\Inscripcion;

use App\Http\Controllers\Controller;
use App\Services\QrProviderService;
use App\Services\RegistrationService;
use App\Services\RegistroValidacionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * Consolidación monolito (22/08/2026), Fase 2b — reemplaza
 * `elascenso-blade\Api\PagoProxyController`. Consulta el estado de pago
 * según el proveedor configurado (SIP/Multipago/QR nuevo) y solo cae al
 * fallback simulado (90s desde la creación) si no hay gateway configurado o
 * falla — mismo comportamiento que el original, solo que `marcarPagado()`
 * pasa de `forward('PATCH', '/registrations/{ref}/payment', ...)` a llamar
 * directo `RegistrationService::updatePaymentStatus()` (la misma que usa
 * `RegistrationController::updatePayment()`).
 */
class PagoProxyController extends Controller
{
    public function __construct(
        private readonly RegistroValidacionService $validacion,
        private readonly QrProviderService $qr,
        private readonly RegistrationService $registrationService,
    ) {
    }

    public function estado(string $referencia): JsonResponse
    {
        $registro = $this->validacion->fetchExternalRegistro($referencia);
        if (! $registro) {
            return response()->json(['error' => 'Referencia no encontrada.', 'status' => 'not_found'], 404);
        }

        if (($registro['pago_status'] ?? '') === 'paid') {
            return response()->json(['success' => true, 'status' => 'paid', 'referencia' => $referencia]);
        }

        $qrProvider = $this->qr->provider();
        $tipoPago = $registro['tipo_pago'] ?? '';

        if ($tipoPago === 'sip' && $qrProvider === 'new') {
            $estado = $this->qr->statusNew($referencia);
            if ($estado === 'paid') {
                $this->marcarPagado($referencia);

                return response()->json(['success' => true, 'status' => 'paid', 'referencia' => $referencia]);
            }
            if ($estado === 'pending') {
                return response()->json(['success' => true, 'status' => 'pending', 'referencia' => $referencia]);
            }

            return $this->fallbackSimulado($registro, $referencia);
        }

        if ($tipoPago === 'sip' && $qrProvider === 'sip') {
            $sip = $this->qr->sipClient();
            if ($sip) {
                try {
                    $estado = $sip['client']->estadoTransaccion($referencia);
                    if (($estado['estadoActual'] ?? '') === 'PAGADO') {
                        $this->marcarPagado($referencia);

                        return response()->json(['success' => true, 'status' => 'paid', 'referencia' => $referencia]);
                    }

                    return response()->json(['success' => true, 'status' => 'pending', 'referencia' => $referencia]);
                } catch (\Throwable $e) {
                    Log::error('[SIP] Error consultando estado para '.$referencia.': '.$e->getMessage());
                }
            }
        }

        if ($tipoPago === 'multipago' && ! empty($registro['pay_order_number'])) {
            $mp = $this->qr->multipagoClient();
            if ($mp) {
                try {
                    $estado = $mp['client']->getPayOrderByNumber($registro['pay_order_number']);
                    if (($estado['statusOrder'] ?? '') === 'Confirmada') {
                        $this->marcarPagado($referencia);

                        return response()->json(['success' => true, 'status' => 'paid', 'referencia' => $referencia]);
                    }

                    return response()->json(['success' => true, 'status' => 'pending', 'referencia' => $referencia]);
                } catch (\Throwable $e) {
                    Log::error('[Multipago] Error consultando estado para '.$referencia.': '.$e->getMessage());
                }
            }
        }

        return $this->fallbackSimulado($registro, $referencia);
    }

    private function fallbackSimulado(array $registro, string $referencia): JsonResponse
    {
        $creado = strtotime($registro['fecha'] ?? 'now');
        $esperados = 90;
        $restante = $esperados - (time() - $creado);

        if ($restante <= 0) {
            if ($this->marcarPagado($referencia)) {
                return response()->json(['success' => true, 'status' => 'paid', 'referencia' => $referencia]);
            }

            return response()->json(['success' => true, 'status' => 'pending', 'referencia' => $referencia, 'remaining' => 0]);
        }

        return response()->json(['success' => true, 'status' => 'pending', 'referencia' => $referencia, 'remaining' => $restante]);
    }

    private function marcarPagado(string $referencia): bool
    {
        try {
            $this->registrationService->updatePaymentStatus($referencia, 'paid');

            return true;
        } catch (\Throwable $e) {
            Log::error('[Pago] No se pudo marcar pagada la referencia '.$referencia.': '.$e->getMessage());

            return false;
        }
    }
}
