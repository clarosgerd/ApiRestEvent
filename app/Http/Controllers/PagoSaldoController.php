<?php

namespace App\Http\Controllers;

use App\Actions\ConfirmarPagoSaldoAction;
use App\Actions\GenerarPagoSaldoAction;
use App\Http\Resources\RegistrationCollectionResource;
use App\Models\PagoSaldoInscripcion;
use App\Models\Registration;
use App\Services\RegistrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Pago online del saldo pendiente — autoservicio (22/10/2026, pedido real
 * del usuario). Espejo de PagoAdicionalController, simplificado: store()
 * no recibe participantes/totales (no hay nada que editar, solo se cobra
 * el saldo existente).
 */
class PagoSaldoController extends Controller
{
    public function store(string $reference, GenerarPagoSaldoAction $action): JsonResponse
    {
        $registration = Registration::where('referencia', $reference)->firstOrFail();

        try {
            $pago = $action->handle($registration);
        } catch (\DomainException $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'referencia_saldo' => $pago->referencia,
            'saldo' => (float) $pago->saldo,
            'fee' => (float) $pago->fee,
            'monto' => (float) $pago->monto,
        ], 201);
    }

    public function guardarQrId(Request $request, string $referenciaSaldo): JsonResponse
    {
        $pago = PagoSaldoInscripcion::where('referencia', $referenciaSaldo)->firstOrFail();
        $pago->update(['qr_id' => $request->input('qr_id')]);

        return response()->json(['success' => true]);
    }

    public function guardarPayOrderNumber(Request $request, string $referenciaSaldo): JsonResponse
    {
        $pago = PagoSaldoInscripcion::where('referencia', $referenciaSaldo)->firstOrFail();
        $pago->update(['pay_order_number' => $request->input('pay_order_number')]);

        return response()->json(['success' => true]);
    }

    public function findByPayOrder(string $payOrderNumber): JsonResponse
    {
        $pago = PagoSaldoInscripcion::where('pay_order_number', $payOrderNumber)->firstOrFail();

        return response()->json(['success' => true, 'referencia' => $pago->referencia]);
    }

    public function show(string $referenciaSaldo, RegistrationService $registrationService): JsonResponse
    {
        $pago = PagoSaldoInscripcion::where('referencia', $referenciaSaldo)->firstOrFail();

        $data = [
            'success' => true,
            'pago_status' => $pago->pago_status,
            'saldo' => (float) $pago->saldo,
            'fee' => (float) $pago->fee,
            'monto' => (float) $pago->monto,
            'created_at' => optional($pago->created_at)->format('Y-m-d H:i:s'),
            'eventoId' => $pago->registration->evento_id,
            'tipoPagoOriginal' => $pago->registration->tipo_pago,
            'payOrderNumber' => $pago->pay_order_number,
        ];
        if ($pago->pago_status === 'paid') {
            $data['data'] = new RegistrationCollectionResource($registrationService->loadRelations($pago->registration));
        }

        return response()->json($data);
    }

    public function confirmar(string $referenciaSaldo, ConfirmarPagoSaldoAction $action): JsonResponse
    {
        try {
            $result = $action->handle($referenciaSaldo);
        } catch (\DomainException $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'pago_status' => $result['pago']->pago_status,
            'data' => $result['registration'] ? new RegistrationCollectionResource($result['registration']) : null,
        ]);
    }
}
