<?php

namespace App\Http\Controllers\Inscripcion\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\Registration;
use App\Services\QrProviderService;
use App\Services\RegistrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use MultipagoPayment\Multipago\MultipagoException;
use MultipagoPayment\Support\Logger;

/**
 * Consolidación monolito (22/08/2026), Fase 2b — reemplaza
 * `elascenso-blade\Webhooks\MultipagoCallbackController`. Multipago no
 * firma este callback de ninguna forma, así que el body JAMÁS es fuente de
 * verdad: antes de marcar cualquier cosa como pagada se reverifica
 * server-to-server vía `MultipagoClient::getPayOrderByNumber()`. Los 2
 * `forward()` del original (buscar la referencia por `pay_order_number` y
 * marcar el pago) pasan a ser una consulta Eloquent directa y una llamada a
 * `RegistrationService::updatePaymentStatus()`.
 */
class MultipagoCallbackController extends Controller
{
    public function __construct(
        private readonly QrProviderService $qr,
        private readonly RegistrationService $registrationService,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $mp = $this->qr->multipagoClient();
        if (! $mp) {
            return response()->json(['ok' => false, 'error' => 'Integración Multipago no configurada'], 503);
        }

        $logger = new Logger($mp['config']->storagePath);
        $payOrderNumber = (string) $request->input('pay_order_number', '');

        if ($payOrderNumber === '') {
            return response()->json(['ok' => false, 'error' => 'Se requiere el campo "pay_order_number" en el cuerpo'], 400);
        }

        $logger->info('payment_callback_multipago', 'Callback de Multipago recibido (sin verificar todavía)', [
            'pay_order_number' => $payOrderNumber,
            'status_order' => $request->input('status_order'),
        ]);

        try {
            $estado = $mp['client']->getPayOrderByNumber($payOrderNumber);
        } catch (MultipagoException $e) {
            $logger->error('payment_callback_multipago', 'No se pudo reverificar la orden de pago', [
                'pay_order_number' => $payOrderNumber,
                'mensaje' => $e->getMessage(),
            ]);

            return response()->json(['ok' => false, 'error' => 'No se pudo verificar el estado real de la orden'], 502);
        }

        if (($estado['statusOrder'] ?? '') !== 'Confirmada') {
            return response()->json(['ok' => true, 'accion' => 'ignorado', 'status_order' => $estado['statusOrder'] ?? null]);
        }

        $referencia = Registration::where('pay_order_number', $payOrderNumber)->value('referencia');

        if (! $referencia) {
            $logger->error('payment_callback_multipago', 'No se encontró referencia para pay_order_number', [
                'pay_order_number' => $payOrderNumber,
            ]);

            return response()->json(['ok' => false, 'error' => 'No se encontró una inscripción para esta orden de pago'], 404);
        }

        try {
            $this->registrationService->updatePaymentStatus($referencia, 'paid');
        } catch (\Throwable $e) {
            $logger->error('payment_callback_multipago', 'Error al marcar pago', [
                'referencia' => $referencia,
                'excepcion' => $e->getMessage(),
            ]);

            return response()->json(['ok' => false, 'error' => 'Error interno al procesar el pago'], 500);
        }

        $logger->info('payment_callback_multipago', 'Pago confirmado y procesado correctamente', [
            'referencia' => $referencia,
            'pay_order_number' => $payOrderNumber,
        ]);

        return response()->json(['ok' => true, 'accion' => 'confirmado', 'referencia' => $referencia]);
    }
}
