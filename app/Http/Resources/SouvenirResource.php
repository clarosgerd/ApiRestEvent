<?php

namespace App\Http\Resources;

use App\Support\DisponibilidadItemData;
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
            'form_types_id'            =>$this->form_types_id ,
            'name'            =>$this->name ,
            'icon'            =>$this->icon,
            'price'           =>$this->price,
            // Kit/tallas/stock (11/08/2026) — ver
            // PRD-kit-tallas-stock-lista-espera.md. "ítem" es la palabra
            // que ve el organizador/participante, el modelo sigue
            // siendo Souvenir (decisión de terminología del PRD).
            'incluido'        => (bool) $this->incluido,
            'foto_url'        => $this->foto_url,
            'requiere_talla'  => (bool) $this->requiere_talla,
            'requiere_sexo'   => (bool) $this->requiere_sexo,
            // Souvenirs invisibles para el participante (22/08/2026) — un
            // souvenir con visible_participante=false NUNCA debería llegar
            // acá para un consumidor público (EventoController::index()/
            // show() filtra la relación antes del eager load si quien pide
            // no es admin/super_admin), pero se expone igual el flag para
            // que el panel admin pueda mostrarlo/editarlo.
            'visible_participante' => (bool) $this->visible_participante,
            // Cargo de servicio por souvenir individual (01/09/2026) — si
            // este ítem suma su precio a la base del cargo de servicio
            // (junto a inscripción/talleres) o queda afuera, igual que
            // siempre (default false). Ver CrearInscripcionAction::
            // validateFeePct() y api/_registro_validacion.php (fuente de
            // verdad del cálculo).
            'aplica_cargo_servicio' => (bool) $this->aplica_cargo_servicio,
            // Array vacío = disponibilidad no controlada (sin filas de
            // stock cargadas) — no significa "agotado", ver
            // DisponibilidadItemData.
            'tallas'          => DisponibilidadItemData::paraSouvenir($this->resource),

    ];
    }
}
