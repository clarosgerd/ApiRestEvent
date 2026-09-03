<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SouvenirParticipanteResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * Bug real encontrado 02/09/2026 (mientras se armaba la edición de
     * souvenirs en una inscripción pagada): `id`/`talla`/`sexo` nunca se
     * exponían acá — solo `nombre`/`precio`/`participante_id`. El frontend
     * (elascenso/event, `editParticipant()`) ya intentaba restaurar el
     * souvenir marcado y su talla/sexo comparando `s.id` contra
     * `sc.dataset.id`, así que en CUALQUIER reapertura de una inscripción
     * existente (pendiente o pagada) para editar, las tarjetas de souvenir
     * quedaban siempre sin marcar y sin talla/sexo restaurados — el
     * `undefined` nunca calzaba con ningún id real. No es nuevo de esta
     * feature, ya estaba roto antes.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
         return [
            'id' => $this->souvenir_id,
            'participante_id' => $this->participante_id,
            'nombre' => $this->nombre,
            'precio' => (float)$this->precio,
            'talla' => $this->talla,
            'sexo' => $this->sexo,
    ];
    }
}
