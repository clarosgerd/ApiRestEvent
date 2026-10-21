<?php

namespace App\Http\Resources;

use App\Support\FechaReporteData;
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
            // Hora real de Bolivia (21/10/2026) — ambos son UTC de verdad
            // (ProvisionarCuentaExpositorAction::handle() usa now()); sin
            // convertir, se mostraban 4h adelantados.
            'credencialesEnviadasAt' => FechaReporteData::iso($this->credenciales_enviadas_at),
            'leadsCount'            => $this->when(isset($this->leads_count), fn () => (int) $this->leads_count),
            'createdAt'             => FechaReporteData::iso($this->created_at),
        ];
    }
}
