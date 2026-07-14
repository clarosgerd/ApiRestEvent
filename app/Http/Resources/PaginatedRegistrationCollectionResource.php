<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class PaginatedRegistrationCollectionResource extends ResourceCollection
{
    public function toArray(Request $request): array
    {
        return [

            'items'=>RegistrationResource::collection(
                $this->collection
            ),
            'pagination'=>[
                'current_page'=>$this->currentPage(),
                'last_page'=>$this->lastPage(),
                'per_page'=>$this->perPage(),
                'total'=>$this->total()

            ]

        ];
    }
}