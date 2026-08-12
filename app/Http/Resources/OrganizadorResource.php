<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrganizadorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'razon_social'      => $this->razon_social,
            'nombre_comercial'  => $this->nombre_comercial,
            // Nombre a mostrar en selects (panel de administración) — el
            // comercial si existe, si no la razón social.
            'nombre'            => $this->nombre_comercial ?: $this->razon_social,
            'rut_nit'           => $this->rut_nit,
            'email'             => $this->email,
            'telefono'          => $this->telefono,
            'pais_id'           => $this->pais_id,
            'ciudad_id'         => $this->ciudad_id,
            'direccion'         => $this->direccion,
            'logo_url'          => $this->logo_url,
            'plan_id'           => $this->plan_id,
            'comision_especial' => $this->comision_especial !== null ? (float) $this->comision_especial : null,
            'convenio_notas'    => $this->convenio_notas,
            'activo'            => (bool) $this->activo,
            // withCount('eventos')/loadCount('eventos') deja un atributo
            // plano `eventos_count` en el modelo — no una relación cargada
            // (relationLoaded('eventos') da false igual), por eso se chequea
            // isset() sobre el atributo en vez de whenLoaded().
            'eventos_count'     => $this->when(isset($this->eventos_count), fn () => (int) $this->eventos_count),
        ];
    }
}
