<?php

namespace App\Services;

use App\Http\Controllers\RegistrationController;
use App\Models\Evento;
use App\Models\SipBanco;
use Illuminate\Support\Facades\Log;
use SipPayment\Config\Config as SipConfig;
use SipPayment\Sip\SipClient;
use SipPayment\Sip\TokenCache;
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

    /**
     * SIP multi-banco (28/08/2026, sincronizado 14/09/2026) — port 1:1 de
     * `elascenso/event/api/sip_bank_resolver.php::resolve_sip_bank()`, pero
     * in-process: en vez de una llamada HTTP a `/internal/event/{id}/
     * sip-banco`, consulta `SipBanco` directo (mismo query que
     * `SipBancoInternalController::paraEvento()`) — sin el estado
     * intermedio 'api_unavailable' (ya no existe "la red cayó" para una
     * consulta Eloquent en el mismo proceso), solo 'ok'/'sin_banco'.
     *
     * Diseño explícitamente defensivo, igual que el original: 'sin_banco'
     * es la única señal que el caller DEBE bloquear — nunca debe generarse
     * un QR contra el banco default del .env para un organizador que
     * confirmadamente no tiene SipBanco propio (bug real de plata cruzada,
     * ver [[project_sip_banco_seguro_multipago_adicional]]).
     *
     * @return array{config: SipConfig, cacheKey: string, status: 'ok'|'sin_banco'}
     */
    public function resolveSipBank(int $eventoId, SipConfig $sipConfigDefault): array
    {
        $evento = Evento::find($eventoId);
        if (! $evento) {
            return ['config' => $sipConfigDefault, 'cacheKey' => 'sip_token', 'status' => 'sin_banco'];
        }

        $banco = SipBanco::where('organizador_id', $evento->organizador_id)
            ->where('activo', true)
            ->first();

        if (! $banco) {
            return ['config' => $sipConfigDefault, 'cacheKey' => 'sip_token', 'status' => 'sin_banco'];
        }

        $banco->makeVisible(['sip_password', 'sip_apikey', 'sip_apikey_servicio', 'callback_basic_password']);

        $config = SipConfig::fromArray([
            'sipUsername' => $banco->sip_username,
            'sipPassword' => $banco->sip_password,
            'sipApiKey' => $banco->sip_apikey,
            'sipApiKeyServicio' => $banco->sip_apikey_servicio,
            'sipAuthUrl' => $banco->sip_base_auth_url,
            'sipApiUrl' => $banco->sip_base_api_url,
            'callbackBasicUser' => $banco->callback_basic_user,
            'callbackBasicPassword' => $banco->callback_basic_password,
        ]);

        return ['config' => $config, 'cacheKey' => 'sip_token_banco_'.$banco->id, 'status' => 'ok'];
    }

    /**
     * Credenciales de callback de TODOS los bancos SIP activos — usado por
     * el webhook (`SipCallbackController`) para aceptar el Basic Auth
     * entrante contra CUALQUIER banco configurado, porque no se sabe de
     * antemano con qué banco se generó el QR que confirma. Mismo criterio
     * que `SipBancoInternalController::callbackCredenciales()`.
     *
     * @return list<array{user: string, password: string}>
     */
    public function activeSipBankCallbackCredentials(): array
    {
        return SipBanco::where('activo', true)
            ->get(['id', 'callback_basic_user', 'callback_basic_password'])
            ->map(fn (SipBanco $b) => [
                'user' => $b->callback_basic_user,
                'password' => $b->makeVisible('callback_basic_password')->callback_basic_password,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  int|null  $eventoId  Cuando se pasa, resuelve el SipBanco del
     *                              organizador dueño de ese evento — ver
     *                              resolveSipBank(). Sin evento (contexto de
     *                              webhook, donde todavía no se sabe con qué
     *                              banco se generó el QR que confirma) cae
     *                              al banco default del .env, comportamiento
     *                              de siempre.
     * @return array{client: SipClient, config: object, cacheKey: string, status: 'ok'|'sin_banco'}|null
     */
    public function sipClient(?int $eventoId = null): ?array
    {
        if (! $this->sipAvailable()) {
            return null;
        }

        $sipConfigDefault = require $this->sipBootstrapPath();

        if ($eventoId !== null) {
            $resolved = $this->resolveSipBank($eventoId, $sipConfigDefault);
            $config = $resolved['config'];
            $cacheKey = $resolved['cacheKey'];
            $status = $resolved['status'];
        } else {
            $config = $sipConfigDefault;
            $cacheKey = 'sip_token';
            $status = 'ok';
        }

        $logger = new SipLogger($config->storagePath);
        $tokenCache = new TokenCache($config->storagePath, $cacheKey);

        return ['client' => new SipClient($config, $logger, tokenCache: $tokenCache), 'config' => $config, 'cacheKey' => $cacheKey, 'status' => $status];
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
