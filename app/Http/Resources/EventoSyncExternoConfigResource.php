<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Sync periódico (pull) de participantes de un evento con registro propio
 * externo (17/09/2026) — `token` viaja ENMASCARADO (últimos 4 caracteres),
 * nunca el valor real de vuelta al navegador. Para cambiarlo hace falta
 * mandar uno nuevo (ver StoreEventoSyncExternoConfigRequest/controller).
 */
class EventoSyncExternoConfigResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'eventoId' => $this->evento_id,
            'formTypesId' => $this->form_types_id,
            'formTypeNombre' => $this->formType?->name,
            'nombreFuente' => $this->nombre_fuente,
            'url' => $this->url,
            'tieneToken' => $this->token !== null && $this->token !== '',
            'tokenPreview' => $this->token ? '••••' . substr($this->token, -4) : null,
            'activo' => (bool) $this->activo,
            'ultimaSincronizacionAt' => $this->ultima_sincronizacion_at,
            'ultimoResultado' => $this->ultimo_resultado,
        ];
    }
}
