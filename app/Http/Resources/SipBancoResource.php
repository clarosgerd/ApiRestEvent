<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * SIP multi-banco (28/08/2026) — mismo criterio que FormasPagoResource:
 * las credenciales reales NUNCA viajan por acá, ni siquiera hacia
 * admin-eventos (que ya solo las escribe, nunca las vuelve a leer — ver
 * el form de edición, "dejar vacío para no cambiar"). Solo se expone si
 * cada secreto está cargado o no (para que el form sepa mostrar el
 * placeholder correcto), nunca el valor.
 */
class SipBancoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organizadorId' => $this->organizador_id,
            'organizadorNombre' => $this->whenLoaded('organizador', fn () => $this->organizador?->nombre_comercial ?: $this->organizador?->razon_social),
            'nombre' => $this->nombre,
            'sipUsername' => $this->sip_username,
            'sipBaseAuthUrl' => $this->sip_base_auth_url,
            'sipBaseApiUrl' => $this->sip_base_api_url,
            'callbackBasicUser' => $this->callback_basic_user,
            'activo' => (bool) $this->activo,
            'createdAt' => optional($this->created_at)->toIso8601String(),
        ];
    }
}
