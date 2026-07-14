<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;

class EventoResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public $preserveKeys = true;
    public function toArray(Request $request): array
    {
      //  dd($this->resource);
     return [
            'id'                        =>$this->id,
      //      'organizador_id'            =>$this->organizador_id,
      //      'tipo_evento_id'            =>$this->tipo_evento_id,
     //       'subtipo_evento_id'         =>$this->subtipo_evento_id,
     //       'status'                    =>$this->estado_evento_id,
    //        'pais_id'                   =>$this->pais_id,
    //        'ciudad_id'                 =>$this->ciudad_id,
            'name'                      =>$this->nombre,
            'date'                      =>$this->fecha_inicio,
            'localTime'                 =>Carbon::parse($this->localTime)->format('H:i:s'),
            'location'                  =>$this->direccion, 
            'coordinates'               =>CoordinateResource::collection($this->whenLoaded('coordinates')),  // Latitud geográfica del evento
            'route'                     =>RouteResource::collection($this->whenLoaded('routes')),  // Ruta del evento
            //new UserResource($this->whenLoaded('author')),
            'status'                    =>$this->estado_evento_id,
     //       'nombre_corto'              =>$this->nombre_corto,
     //       'url_slug'                  =>$this->url_slug,
     //       'keyword'                   =>$this->keyword,
            'image'                     =>$this->imagen_portada_url,
            'video'                     =>$this->video_url,
            'description'               =>$this->descripcion,
            'longDescription'           =>$this->longDescription,
            'hasDonation'                 =>$this->hasDonation,
            'categories'                =>CategoryResource::collection($this->whenLoaded('categories')),  // Categorías del evento
            'formTypes'                 =>FormTypeResource::collection($this->whenLoaded('formTypes')),  // Tipos de formulario del evento  
            'souvenirs'                  =>SouvenirResource::collection($this->whenLoaded('souvenirs')),  // Souvenirs del evento
       //     'reglamento'                =>$this->reglamento,
       //     'deslinde'                  =>$this->deslinde,
       //     'fecha_fin'                 =>$this->fecha_fin,
       //     'fecha_apertura_inscrip'    =>$this->fecha_apertura_inscrip,
       //     'fecha_cierre_inscrip'      =>$this->fecha_cierre_inscrip,
       //     'mensaje_cierre'            =>$this->mensaje_cierre,
       //     'lugar'                     =>$this->lugar,
       //     'modalidad'                 =>$this->modalidad,
       //     'url_virtual'               =>$this->url_virtual, 
       //     'aforo_total'               =>$this->aforo_total,
       //     'color_id'                  =>$this->color_id, 
       //     'logo_url'                  =>$this->logo_url,
       //     'icono_url'                 =>$this->icono_url,
       //     'gpx_url'                   =>$this->gpx_url,
       //     'link_strava'               =>$this->link_strava,
       //     'checkin_tipo'              =>$this->checkin_tipo,
       //     'tiene_delivery'            =>$this->tiene_delivery,
       //     'tiene_punto_venta'         =>$this->tiene_punto_venta,
       //     'tiene_desafios'            =>$this->tiene_desafios,
       //     'publicado'                 =>$this->publicado,
       //     'destacado'                 =>$this->destacado,
       //   'hasDonation'                 =>$this->hasDonation,
            'promoCodes'                 =>PromoCodeResource::collection($this->whenLoaded('promoCodes')),  // Códigos promocionales del evento
    //        'contador_visitas'          =>$this->contador_visitas
    ];
    
    }
}
