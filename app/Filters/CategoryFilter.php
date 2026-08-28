<?php
namespace App\Filters;

use Illuminate\Http\Request;

class CategoryFilter extends ApiFilter
{
    /**/
    protected $safeParams = [
    'id'=>['eq'] ,
    'name'=>['eq'] ,
    'price'=>['eq'] ,
    // Categorías por form_type (27/08/2026) — filtros nuevos, index() no
    // filtraba ni siquiera por evento. Hoy nada consume GET /category
    // directo (las categorías llegan anidadas en EventoResource), pero
    // deja la API consistente para cuando haga falta.
    'event_id'=>['eq'] ,
    'formulario_id'=>['eq'] ,
    ];
    protected $columnMap = [
        'id'=>'id',
        'name'=>'name',
        'event_id'=>'event_id',
        'formulario_id'=>'formulario_id',
    ];
    protected $operatorMap = [
      'eq'=>'=',
      'gt'=>'>',
      'lt'=>'<',
      'gte'=>'>=',
      'lte'=>'<=',
      'ne'=>'!=',        
    ];
   


}
