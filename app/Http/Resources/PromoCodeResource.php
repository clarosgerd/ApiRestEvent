<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PromoCodeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                  =>$this->id,
            'event_id'            =>$this->event_id,
            'promo_code' => $this->promo_code,
            'price'            =>$this->price ,
            'discount_type'    => $this->discount_type ?? 'fixed_price',
            'discount_percent' => $this->discount_percent !== null ? (float) $this->discount_percent : null,
            'usado'            => (bool) $this->usado,
            // Multi-uso (13/09/2026) — aditivo, admin-eventos/elascenso/event
            // que todavía no los usan los ignoran sin romperse.
            'max_uses'         => (int) $this->max_uses,
            'times_used'       => (int) $this->times_used,

    ];
    }
}
