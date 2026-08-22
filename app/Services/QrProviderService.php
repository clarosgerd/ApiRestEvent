<?php

namespace App\Services;

use App\Http\Controllers\RegistrationController;
use Illuminate\Support\Facades\Log;
use SipPayment\Sip\SipClient;
use SipPayment\Support\Logger as SipLogger;
use MultipagoPayment\Multipago\MultipagoClient;
use MultipagoPayment\Support\Logger as MultipagoLogger;

/**
 * Consolidación monolito (22/08/2026), Fase 2b — port 1:1 de
 * `elascenso-blade\App\Services\QrProviderService` (a su vez, traducción a
 * Laravel de `api/qr_provider.php`). Ningún cambio real más que el
 * namespace: los dos SDKs de pago (`sip-payment-integration/`,
 * `multipago-payment-integration/`) siguen viviendo como carpetas hermanas
 * de `elascenso/event` — `ApiRestEvent-monolito` es TAMBIÉN hermano directo
 * de esa carpeta bajo `htdocs/` (igual que `elascenso-blade` lo era), así
 * que la ruta relativa `base_path('../elascenso/event/...')` resuelve
 * exactamente igual sin tocar nada. Se los requiere directo (`require
 * $bootstrap`, autoload manual propio del SDK) en vez de como repositorio
 * `path` de Composer — mismo criterio ya documentado en el original.
 */
class QrProviderService
{
    public function provider(): string
    {
        return strtolower((string) config('services.qr.provider', 'none'));
    }

    private function sipBootstrapPath(): string
    {
        return base_path('../elascenso/event/sip-payment-integration/bootstrap.php');
    }

    private function multipagoBootstrapPath(): string
    {
        return base_path('../elascenso/event/multipago-payment-integration/bootstrap.php');
    }

    public function sipAvailable(): bool
    {
        return file_exists($this->sipBootstrapPath());
    }

    public function multipagoAvailable(): bool
    {
        return file_exists($this->multipagoBootstrapPath());
    }

    /** @return array{client: SipClient, config: object}|null */
    public function sipClient(): ?array
    {
        if (! $this->sipAvailable()) {
            return null;
        }

        $config = require $this->sipBootstrapPath();
        $logger = new SipLogger($config->storagePath);

        return ['client' => new SipClient($config, $logger), 'config' => $config];
    }

    /** @return array{client: MultipagoClient, config: object}|null */
    public function multipagoClient(): ?array
    {
        if (! $this->multipagoAvailable()) {
            return null;
        }

        $config = require $this->multipagoBootstrapPath();
        $logger = new MultipagoLogger($config->storagePath);

        return ['client' => new MultipagoClient($config, $logger), 'config' => $config];
    }

    /**
     * Genera un QR usando la propia API REST (ApiRestEvent). Fase 2b: a
     * diferencia del resto de este archivo (que sí son SDKs externos
     * genuinos), esto YA apuntaba a la propia ApiRestEvent — como ahora
     * vivimos dentro de esa misma app, se reemplaza el `Http::get()` (un
     * auto-llamado por loopback HTTP, frágil en tests que no levantan
     * servidor) por invocar `RegistrationController::generaQr()`
     * in-process — misma regla dura que el resto de la consolidación.
     *
     * @return string|null Base64 puro (sin prefijo data:) o null si falla.
     */
    public function generateNew(string $referencia): ?string
    {
        try {
            $decoded = app(RegistrationController::class)->generaQr($referencia)->getData(true);
        } catch (\Throwable $e) {
            Log::error('[NEW-QR] generaQr excepción para '.$referencia.': '.$e->getMessage());

            return null;
        }

        if (empty($decoded['success'])) {
            Log::error('[NEW-QR] generaQr sin success para '.$referencia.': '.json_encode($decoded));

            return null;
        }

        $qr = $decoded['data']['qr'] ?? null;
        if (! $qr) {
            return null;
        }

        if (str_contains($qr, 'base64,')) {
            $qr = substr($qr, strrpos($qr, 'base64,') + 7);
        }

        return $qr ?: null;
    }

    /** @return string|null 'paid' | 'pending' | null si hay error. */
    public function statusNew(string $referencia): ?string
    {
        try {
            $decoded = app(RegistrationController::class)->estadoTransaccion($referencia)->getData(true);
        } catch (\Throwable $e) {
            Log::error('[NEW-QR] estadoTransaccion excepción para '.$referencia.': '.$e->getMessage());

            return null;
        }

        if (empty($decoded['success'])) {
            return null;
        }

        $estado = $decoded['data']['estado']
            ?? $decoded['data']['estadoActual']
            ?? $decoded['estado']
            ?? '';

        return in_array(strtoupper($estado), ['PAGADO', 'PAID', 'COMPLETED'], true) ? 'paid' : 'pending';
    }
}
