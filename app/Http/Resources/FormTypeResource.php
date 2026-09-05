<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
class FormTypeResource extends JsonResource
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
           
            'name'            =>$this->name ,
            'icon'            =>$this->icon,
            // Tarjeta de tipo de formulario simplificada (19/08/2026) —
            // imagen opcional cargada desde admin-eventos; el frontend la
            // muestra en vez del emoji de `icon` cuando está presente.
            'imagenUrl'       =>$this->imagen_url ?: null,
            'description'     =>$this->description,
            'tipo'            =>$this->tipo,
            'cupo_total'      =>$this->cupo_total,
            // Kit/tallas/stock (11/08/2026) — antes `activo` no se
            // exponía y nada lo volvía a chequear al aceptar una
            // inscripción nueva (ver "Hallazgo adicional" del PRD
            // kit-tallas-stock-lista-espera.md); ahora el frontend
            // puede mostrar "cupo lleno" y ofrecer lista de espera.
            'activo'          => (bool) $this->activo,
            'cupoDisponible'  => $this->cupoDisponible(),
            'precio_base'     =>$this->precio_base,
            'costo_edicion'   =>$this->costo_edicion,
            'color'           =>$this->color,
            'moneda'          =>$this->moneda,
            'permite_lista_espera'  =>$this->permite_lista_espera,
            'permite_inscripcion_grupal' => (bool) $this->permite_inscripcion_grupal,
            'hasTeam'                    => (bool) $this->has_team,
            'hasDelivery'                => (bool) $this->has_delivery,
            'hasDonation'                => (bool) $this->has_donation,
            'hasPromoCode'               => (bool) $this->has_promo_code,
            // Ver brain/PLAN-ASIGNACION-STAFF-SESIONES-CONGRESO-13082026.md —
            // (bool) explícito por el mismo motivo que el resto de estos
            // flags, ver project_bug_cast_bool_hasshirt_formtyperesource.
            'esStaff'                    => (bool) $this->es_staff,
            'esPonente'                  => (bool) $this->es_ponente,
            'requiereCategoria'          => (bool) $this->requiere_categoria,
            // Caja para eventos tipo congreso (20/08/2026) — ver migración
            // add_requiere_contacto_emergencia_to_form_types_table.
            'requiereContactoEmergencia' => (bool) $this->requiere_contacto_emergencia,
            // Ocultar Dirección/Ciudad/Teléfono/Alias por tipo de
            // formulario (01/09/2026) — array de strings, nunca null (el
            // frontend siempre puede hacer .includes() sin chequear antes).
            'camposOcultos'              => $this->campos_ocultos ?? [],
            // Edición restringida a solo souvenirs/talleres (04/09/2026).
            'edicionSoloExtras'          => (bool) $this->edicion_solo_extras,
            'max_integrantes_grupo'      => (int) $this->max_integrantes_grupo,
            'descuento_registrante_pct'  => (float) $this->descuento_registrante_pct,
            // Bug real encontrado 24/08/2026: este resource nunca expuso
            // tiempo_expiracion_min desde que existe — admin-eventos no
            // podía mostrar/editar el valor real de la BD, así que la
            // vista lo hardcodeaba a 30 en vez de leerlo. El ciclo de
            // expiración (ExpirarInscripcionesPendientesAction) lo lee
            // directo del modelo, ajeno a este resource, por eso siempre
            // funcionó bien aunque el panel nunca pudiera mostrarlo.
            'tiempo_expiracion_min'      => (int) $this->tiempo_expiracion_min,
            // Deprecación hasshirt/costo_polera (12/08) — sin este cast,
            // en hostings donde PDO devuelve tinyint como string ("0"),
            // `!selectedFormType.hasshirt` en index.php nunca ocultaba la
            // sección legacy ("0" es truthy en JS). Nunca se notó antes
            // porque hasshirt casi siempre era 1/"1" (ambos truthy).
            'hasshirt'              => (bool) $this->hasshirt,
            'costo_polera'          =>$this->costo_polera,
             'hasQuestion'              =>$this->hasQuestion,
            'requiere_talla'        =>$this->requiere_talla,
            //'souvenirs'             =>new SouvenirResource($this->form_types_id),  // Souvenirs del evento
            //'souvenirs' => SouvenirResource::make($this->whenLoaded('souvenirs')),
       
            'souvenirs' =>SouvenirResource::collection($this->whenLoaded('souvenirs')),
            'preguntas' =>FormularioCamposResource::collection($this->whenLoaded('formularioCampos')),
   
    ];
    }
}
