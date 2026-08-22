<?php

namespace App\Http\Controllers\Inscripcion\Webhooks;

use App\Http\Controllers\Controller;
use App\Services\QrProviderService;
use App\Services\RegistrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use SipPayment\Sip\CallbackAuthenticator;
use SipPayment\Support\Logger;

/**
 * Consolidación monolito (22/08/2026), Fase 2b — reemplaza
 * `elascenso-blade\Webhooks\SipCallbackController`. Endpoint que SIP invoca
 * (spec 3.1) para confirmar el pago de un QR generado. El `forward('PATCH',
 * '/registrations/{ref}/payment', ...)` pasa a ser una llamada directa a
 * `RegistrationService::updatePaymentStatus()`.
 *
 * Seguridad sin cambios: Basic Auth vía `CallbackAuthenticator`, con
 * respaldo por query string (`?callback_token=`) para hostings donde el
 * header Authorization no llega a PHP (mod_lsapi, ver
 * [[project_uat_mod_lsapi_auth_bug]]) — no es un proxy del JS del frontend,
 * queda fuera de CSRF (`webhooks/*` en bootstrap/app.php).
 */
class SipCallbackController extends Controller
{
    public function __construct(
        private readonly QrProviderService $qr,
        private readonly RegistrationService $registrationService,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $sip = $this->qr->sipClient();
        if (! $sip) {
            return response()->json(['codigo' => '9999', 'mensaje' => 'Integración SIP no configurada'], 503);
        }

        $config = $sip['config'];
        $logger = new Logger($config->storagePath);

        $headers = [];
        foreach ($request->headers->all() as $key => $values) {
            $headers[$key] = $values[0] ?? '';
        }
        $authenticator = new CallbackAuthenticator($config->callbackBasicUser, $config->callbackBasicPassword);

        $autorizadoPorHeader = $authenticator->isAuthorized($headers);
        $autorizadoPorToken = $config->callbackToken !== ''
            && $request->query('callback_token')
            && hash_equals($config->callbackToken, (string) $request->query('callback_token'));

        if (! $autorizadoPorHeader && ! $autorizadoPorToken) {
            $logger->warning('payment_callback', 'Acceso no autorizado al callback', [
                'ip' => $request->ip(),
            ]);

            return response()->json(['codigo' => '9999', 'mensaje' => 'No autorizado'], 401);
        }

        $alias = (string) $request->input('alias', '');
        if ($alias === '') {
            return response()->json(['codigo' => '9999', 'mensaje' => 'Se requiere el campo "alias" en el cuerpo'], 400);
        }

        $logger->info('payment_callback', 'Callback de pago recibido', [
            'alias' => $alias,
            'idQr' => $request->input('idQr'),
            'monto' => $request->input('monto'),
        ]);

        $referencia = $alias;

        try {
            $this->registrationService->updatePaymentStatus($referencia, 'paid');
        } catch (\Throwable $e) {
            $logger->error('payment_callback', 'Error al marcar pago', [
                'alias' => $alias,
                'excepcion' => $e->getMessage(),
            ]);

            return response()->json(['codigo' => '9999', 'mensaje' => 'Error interno al procesar el pago'], 500);
        }

        $logger->info('payment_callback', 'Pago confirmado y procesado correctamente', ['alias' => $alias]);

        return response()->json(['codigo' => '0000', 'mensaje' => 'Registro Exitoso']);
    }
}
