<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AnswerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'form_types_id' => $this->form_types_id,
            'question_id'   => $this->question_id,
            'value'         => $this->value,
        ];
    }
}
