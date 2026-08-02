<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RouteResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
       return [
            'id'             =>$this->id,
           // 'event_id'                  =>$this->event_id,
            'lat'            =>$this->lat,
            'lng'            =>$this->lng,
            'label'          =>$this->label,

    ];
    }
}
