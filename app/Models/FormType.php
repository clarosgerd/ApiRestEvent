<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FormType extends Model
{
    /** @use HasFactory<\Database\Factories\FormTypeFactory> */
    use HasFactory;
      public $timestamps = false;
     protected $fillable = [
        'event_id',
        'name',
        'icon',
        'description',
        'tipo',
        'cupo_total',
        'precio_base',
        'color',
        'moneda',
        'activo',
        'permite_lista_espera',
        'requiere_categoria',
        'requiere_talla',
        'requiere_distancia',
        'hasshirt',
        'costo_polera',
        'hasQuestion',
        'permite_inscripcion_grupal',
        'max_integrantes_grupo',
        'descuento_registrante_pct',
        'hasQuestion',
        'costo_edicion',
        'tiempo_expiracion_min',
        'texto_boton',
    ];


    
   public function evento()  {
        return $this->belongsTo('App\Models\Evento','id');
     }

   public function souvenirs()
   {
      return $this->hasMany('App\Models\Souvenir', 'form_types_id');
   }

    public function formularioCampos()
   {
      return $this->hasMany('App\Models\FormularioCampos', 'form_types_id');
   }

  

}
