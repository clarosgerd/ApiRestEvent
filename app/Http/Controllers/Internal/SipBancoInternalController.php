<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Models\Evento;
use App\Models\SipBanco;
use Illuminate\Http\JsonResponse;

/**
 * SIP multi-banco (28/08/2026) — ver
 * brain/api_rest_event/PLAN-SIP-MULTIBANCO-28082026.md.
 *
 * Endpoints server-to-server ÚNICAMENTE (protegidos por
 * `RequiresInternalSecret`, no por `auth:admins`/Sanctum) — los llama el
 * backend PHP de `elascenso/event` para resolver con qué banco cobrar por
 * SIP. Nunca deben quedar alcanzables por el navegador ni por ningún
 * cliente autenticado normal — devuelven credenciales reales.
 */
class SipBancoInternalController extends Controller
{
    /**
     * Banco SIP asignado al organizador DUEÑO de este evento, o
     * `banco: null` si no tiene ninguno — en ese caso el caller
     * (elascenso/event) cae al `.env` de `sip-payment-integration` como
     * default, comportamiento de siempre.
     *
     * Recibe `evento`, no `organizador`, a propósito: los 4 puntos de
     * `elascenso/event` que hablan con SIP ya tienen el `evento_id` a
     * mano (de la inscripción o del evento cargado) — resolver acá el
     * organizador evita que ese lado tenga que hacer una llamada extra
     * solo para enterarse de quién es el organizador.
     */
    public function paraEvento(Evento $event): JsonResponse
    {
        $banco = SipBanco::where('organizador_id', $event->organizador_id)
            ->where('activo', true)
            ->first();

        if (!$banco) {
            return response()->json(['success' => true, 'banco' => null]);
        }

        $banco->makeVisible(['sip_password', 'sip_apikey', 'sip_apikey_servicio', 'callback_basic_password']);

        return response()->json([
            'success' => true,
            'banco' => [
                'nombre' => $banco->nombre,
                'sipUsername' => $banco->sip_username,
                'sipPassword' => $banco->sip_password,
                'sipApikey' => $banco->sip_apikey,
                'sipApikeyServicio' => $banco->sip_apikey_servicio,
                'sipBaseAuthUrl' => $banco->sip_base_auth_url,
                'sipBaseApiUrl' => $banco->sip_base_api_url,
                'callbackBasicUser' => $banco->callback_basic_user,
                'callbackBasicPassword' => $banco->callback_basic_password,
                // Cache de token por banco (28/08/2026) — TokenCache ya
                // acepta una cacheKey por constructor; con varios bancos
                // en simultáneo el token de uno no debe pisar el de otro.
                'cacheKey' => 'sip_token_banco_' . $banco->id,
            ],
        ]);
    }

    /**
     * Todas las credenciales de callback de bancos activos — usado por
     * `payment_callback.php` para validar el Basic Auth entrante contra
     * CUALQUIER banco configurado (no sabemos de antemano cuál banco
     * generó el QR que SIP está confirmando, y las credenciales de
     * callback pueden diferir por cuenta — ver PLAN-SIP-MULTIBANCO-28082026.md,
     * decisión explícita del usuario de asumir el caso más estricto).
     */
    public function callbackCredenciales(): JsonResponse
    {
        $bancos = SipBanco::where('activo', true)->get(['id', 'callback_basic_user', 'callback_basic_password']);

        return response()->json([
            'success' => true,
            'callbacks' => $bancos->map(fn (SipBanco $b) => [
                'user' => $b->callback_basic_user,
                'password' => $b->makeVisible('callback_basic_password')->callback_basic_password,
            ])->values(),
        ]);
    }
}
