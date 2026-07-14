<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SouvenirResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
      return [
            'id'              =>$this->id,
            //'event_id'            =>$this->event_id,
            'name'            =>$this->name ,
            'icon'            =>$this->icon,
            'price'           =>$this->price,            
   
    ];
    }
}
