<?php

namespace App\Services;

use App\Models\Registration;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class QrService
{
    private string $baseUrl;
    private string $apiKeyServicio;

    public function __construct()
    {
        $this->baseUrl = config('qrpago.base_api_url');
        $this->apiKeyServicio = config('qrpago.apikey_servicio');
    }

    /**
     * Generar token de autenticación contra la API de pagos.
     */
    public function generarToken(): array
    {
        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
        ])->post(config('qrpago.base_auth_url'), [
            'username' => config('qrpago.username'),
            'password' => config('qrpago.password'),
        ]);

        if ($response->failed()) {
            Log::error('QR: Error al generar token', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            throw new \Exception('No se pudo generar el token de pago.');
        }

        return $response->json();
    }

    /**
     * Consultar estado de una transacción.
     */
    public function estadoTransaccion(string $referencia, string $token): array
    {
        $response = Http::withHeaders([
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer ' . $token,
            'apikey'        => $this->apiKeyServicio,
        ])->post($this->baseUrl . 'estadoTransaccion', [
            'alias' => $referencia,
        ]);

        if ($response->failed()) {
            Log::error('QR: Error al consultar estado', [
                'referencia' => $referencia,
                'status'     => $response->status(),
                'body'       => $response->body(),
            ]);
            throw new \Exception('No se pudo consultar el estado de la transacción.');
        }

        return $response->json();
    }

    /**
     * Generar código QR para pago.
     */
    public function generaQr(Registration $registration, string $token): array
    {
        $totals = $registration->totals;

        // Reserva de cupo con seña (21/10/2026) — saldo_pendiente es 0
        // para cualquier inscripción normal (comportamiento intacto); si
        // hay un saldo (alguien reservó en vez de pagar completo), el QR
        // cobra solo la diferencia. Nunca se recalcula acá, ya viene
        // persistido desde el alta — ver ValidarCategoriaAction::run().
        $monto = $totals
            ? number_format($totals->grand_total - (float) $registration->saldo_pendiente, 2, '.', '')
            : '0.00';

        $payload = [
            'referencia'       => $registration->referencia,
            'callback'         => config('qrpago.callback'),
            'detalleGlosa'     => $registration->evento_nombre,
            'monto'            => $monto,
            'moneda'           => config('qrpago.moneda'),
            'fechaVencimiento' => now()->addDays(7)->format('d/m/Y'),
            'tipoSolicitud'    => config('qrpago.tipo_solicitud'),
        ];

        Log::info('QR: Generando código QR', $payload);

        $response = Http::withHeaders([
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer ' . $token,
            'apikey'        => $this->apiKeyServicio,
        ])->post($this->baseUrl . 'generaQr', $payload);

        if ($response->failed()) {
            Log::error('QR: Error al generar QR', [
                'referencia' => $registration->referencia,
                'status'     => $response->status(),
                'body'       => $response->body(),
            ]);
            throw new \Exception('No se pudo generar el código QR.');
        }

        return $response->json();
    }
}
