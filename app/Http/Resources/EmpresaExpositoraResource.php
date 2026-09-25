<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * SmartStand (25/09/2026) — nunca expone la contraseña ni su hash.
 * `leadsCount` solo viene si la consulta hizo withCount('leads').
 */
class EmpresaExpositoraResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                    => $this->id,
            'eventoId'              => $this->evento_id,
            'registrationId'        => $this->registration_id,
            'nombre'                => $this->nombre,
            'email'                 => $this->email,
            'stand'                 => $this->stand,
            'categoriaId'           => $this->categoria_id,
            'tamanoStand'           => $this->whenLoaded('categoria', fn () => $this->categoria?->name),
            'activo'                => (bool) $this->activo,
            'credencialesEnviadas'  => $this->credenciales_enviadas_at !== null,
            'credencialesEnviadasAt' => optional($this->credenciales_enviadas_at)->toIso8601String(),
            'leadsCount'            => $this->when(isset($this->leads_count), fn () => (int) $this->leads_count),
            'createdAt'             => optional($this->created_at)->toIso8601String(),
        ];
    }
}
