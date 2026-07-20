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
            
            'event_id'            =>$this->event_id,
            'promo_code' => $this->promo_code,
            'price'            =>$this->price ,
            'discount_type'    => $this->discount_type ?? 'fixed_price',
            'discount_percent' => $this->discount_percent !== null ? (float) $this->discount_percent : null,

    ];
    }
}
