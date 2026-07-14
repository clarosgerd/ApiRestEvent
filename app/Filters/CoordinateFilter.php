<?php
namespace App\Filters;

use Illuminate\Http\Request;

class CoordinateFilter extends ApiFilter
{
    /**/
    protected $safeParams = [
    'id'=>['eq'] ,
    'event_id'=>['eq'] ,
    'lat'=>['eq'] ,
    'lng'=>['eq'] ,
    ];
    protected $columnMap = [
        'id'=>'id',
        'event_id'=>'event_id',
        'lat'=>'lat',
        'lng'=>'lng'
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
