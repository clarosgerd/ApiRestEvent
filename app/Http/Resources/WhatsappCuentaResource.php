<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * WhatsApp Business API oficial por organizador (08/10/2026) — mismo
 * criterio que SipBancoResource: `access_token` NUNCA viaja por acá, ni
 * siquiera hacia admin-eventos (que ya solo lo escribe, nunca lo vuelve a
 * leer — ver el form de edición, "dejar vacío para no cambiar").
 */
class WhatsappCuentaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organizadorId' => $this->organizador_id,
            'organizadorNombre' => $this->whenLoaded('organizador', fn () => $this->organizador?->nombre_comercial ?: $this->organizador?->razon_social),
            'nombre' => $this->nombre,
            'phoneNumberId' => $this->phone_number_id,
            'businessAccountId' => $this->business_account_id,
            'templateName' => $this->template_name,
            'templateLang' => $this->template_lang,
            'activo' => (bool) $this->activo,
            'createdAt' => optional($this->created_at)->toIso8601String(),
        ];
    }
}
