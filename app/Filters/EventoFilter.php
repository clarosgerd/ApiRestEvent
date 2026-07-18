<?php
namespace App\Filters;

use Illuminate\Http\Request;

class EventoFilter extends ApiFilter
{
    /**/
    protected $safeParams = [
    'id'=>['eq'] ,
    'organizador_id'=>['eq'] ,
    'tipo_evento_id'=>['eq'] ,
    'subtipo_evento_id'=>['eq',], 
    'estado_evento_id'=>['eq'] ,
    'pais_id'=>['eq','gt'] ,
    'ciudad_id'=>['eq'], 
    'nombre'=>['eq','li'] ,
    'keyword'=>['eq'] ,
    'descripcion'=>['eq'] ,
    'hasDonation'=>['eq'],
    'fecha_inicio'=>['eq', 'gte','lte'] ,
    'fecha_fin'=>['eq', 'gte','lte'] ,
    'fecha_apertura_inscrip'=>['eq', 'gte','lte'] ,
    'fecha_cierre_inscrip'=>['eq', 'gte','lte'] ,
    'mensaje_cierre'=>['eq'] ,
    'lugar'=>['eq'] ,
    'direccion'=>['eq'] ,
    'modalidad'=>['eq'] ,
    'url_virtual'=>['eq'], 
    'aforo_total'=>['eq'] ,
    'color_id'=>['eq'] ,
    
    'checkin_tipo'=>['eq'] ,
    'tiene_delivery'=>['eq'], 
    'tiene_punto_venta'=>['eq'], 
    'tiene_desafios'=>['eq'] ,
    'publicado'=>['eq'] ,
    'destacado'=>['eq'] ,
    'hasDonation'=>['eq'] ,
    //'contador_visitas'=>['eq'] 
    ];
    protected $columnMap = [
        'id'=>'id',
        'nombre'=>'nombre',
    ];
    protected $operatorMap = [
      'eq'=>'=',
      'gt'=>'>',
      'lt'=>'<',
      'gte'=>'>=',
      'lte'=>'<=',
      'ne'=>'!=',
      'li'=>'like',            
    ];
   


}
