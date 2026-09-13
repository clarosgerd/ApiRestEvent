<?php
namespace App\Filters;

use Illuminate\Http\Request;

class PromoCodeFilter extends ApiFilter
{
    /**
     * event_id/usado (13/09/2026) — GET /v1/promo-code es público (sin
     * auth) y hasta ahora no tenía forma de filtrar por evento: devolvía
     * TODOS los códigos promocionales de TODOS los eventos (código real +
     * descuento), paginado pero enumerable por cualquiera. Confirmado que
     * ningún consumidor real (admin-eventos, elascenso/event) usa este
     * índice sin filtro — el controller ahora EXIGE event_id, ver
     * PromoCodeController::index().
     */
    protected $safeParams = [
    'id'=>['eq'] ,
    'price'=>['eq'] ,
    'event_id'=>['eq'] ,
    'usado'=>['eq'] ,
    ];
    protected $columnMap = [
        'id'=>'id',
        'price'=>'price',
        'event_id'=>'event_id',
        'usado'=>'usado',
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
