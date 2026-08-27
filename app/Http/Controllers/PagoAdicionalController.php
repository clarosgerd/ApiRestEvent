<?php

namespace App\Http\Controllers;

use App\Actions\CalcularCostoAdicionalAction;
use App\Actions\ConfirmarPagoAdicionalAction;
use App\Actions\GenerarPagoAdicionalAction;
use App\Http\Requests\UpdatePaidRegistrationRequest;
use App\Http\Resources\RegistrationCollectionResource;
use App\Models\PagoAdicionalInscripcion;
use App\Models\Registration;
use App\Services\RegistrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Cobro real por SIP del monto adicional al agregar un taller a una
 * inscripción pagada (26/08/2026) — ver
 * PLAN-COBRO-SIP-ADICIONAL-26082026.md. Controller propio, separado de
 * RegistrationController (ya grande) — mismo criterio que CajaController.
 *
 * Reusa UpdatePaidRegistrationRequest para validar el payload (mismo
 * shape que update-paid: participantes + totales) — no se duplica
 * validación.
 */
class PagoAdicionalController extends Controller
{
    /**
     * Crea el intento de pago 'pending' — NO toca la inscripción todavía.
     */
    public function store(
        UpdatePaidRegistrationRequest $request,
        string $reference,
        GenerarPagoAdicionalAction $action,
        CalcularCostoAdicionalAction $calcular,
    ): JsonResponse {
        $registration = Registration::where('referencia', $reference)->firstOrFail();
        $validated = $request->validated();

        try {
            // El monto NUNCA se toma de lo que manda el cliente (mismo
            // criterio que precioCategoria en el resto del sistema) — se
            // recalcula server-side con las mismas reglas que aplicaría
            // ActualizarInscripcionPagadaAction al confirmar, sin aplicar
            // nada todavía.
            $monto = $calcular->handle($registration, $validated['participantes']);
            $pago = $action->handle(
                $registration,
                $validated['participantes'],
                $validated['totales'],
                $monto,
                (string) $request->input('moneda_pago', 'BOB'),
            );
        } catch (\DomainException $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'referencia_adicional' => $pago->referencia,
            'monto' => (float) $pago->monto,
        ], 201);
    }

    /**
     * Guarda el idQr que devolvió SIP al generar el QR — para diagnóstico
     * (ver GenerarPagoAdicionalAction, no se persistía nada equivalente
     * hasta esta feature). Llamado por elascenso/event justo después de
     * generar el QR.
     */
    public function guardarQrId(Request $request, string $referenciaAdicional): JsonResponse
    {
        $pago = PagoAdicionalInscripcion::where('referencia', $referenciaAdicional)->firstOrFail();
        $pago->update(['qr_id' => $request->input('qr_id')]);

        return response()->json(['success' => true]);
    }

    /**
     * Estado actual del pago adicional — para polling desde
     * elascenso/event (api/pago_status_adicional.php).
     */
    public function show(string $referenciaAdicional, RegistrationService $registrationService): JsonResponse
    {
        $pago = PagoAdicionalInscripcion::where('referencia', $referenciaAdicional)->firstOrFail();

        // Si ya está 'paid' (ej. el webhook de SIP llegó antes que este
        // poll), se incluye la inscripción ya actualizada — mismo shape
        // que usa el resto del frontend (RegistrationCollectionResource),
        // así elascenso/event puede refrescar el e-ticket sin otra
        // llamada aparte. loadRelations() es el mismo helper que ya usa
        // ActualizarInscripcionPagadaAction, sin reimplementar los eager
        // loads necesarios (RegistrationResource usa whenLoaded()).
        $data = [
            'success' => true,
            'pago_status' => $pago->pago_status,
            'monto' => (float) $pago->monto,
            // created_at (26/08/2026): pago_status_adicional.php lo necesita
            // para su propio fallback simulado de 90s (mismo criterio que
            // pago_status.php con `registro['fecha']`) cuando no hay SIP
            // configurado o falla la consulta — sin esto no puede calcular
            // el tiempo transcurrido desde que se creó el intento.
            'created_at' => optional($pago->created_at)->format('Y-m-d H:i:s'),
        ];
        if ($pago->pago_status === 'paid') {
            $data['data'] = new RegistrationCollectionResource($registrationService->loadRelations($pago->registration));
        }

        return response()->json($data);
    }

    /**
     * Confirma el pago y recién ACÁ aplica el cambio real (delegado a
     * ConfirmarPagoAdicionalAction, que reusa ActualizarInscripcionPagadaAction).
     * Llamado por payment_callback.php (SIP) cuando el alias no matchea
     * ninguna inscripción real, o por el polling si detecta el pago
     * confirmado antes de que llegue el callback.
     */
    public function confirmar(string $referenciaAdicional, ConfirmarPagoAdicionalAction $action): JsonResponse
    {
        try {
            $result = $action->handle($referenciaAdicional);
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
