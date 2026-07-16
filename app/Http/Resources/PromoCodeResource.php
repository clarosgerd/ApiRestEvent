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
            
   
    ];
    }
}
