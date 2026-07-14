<?php
namespace App\Filters;

use Illuminate\Http\Request;

class PromoCodeFilter extends ApiFilter
{
    /**/
    protected $safeParams = [
    'id'=>['eq'] ,
    'price'=>['eq'] ,
   
    ];
    protected $columnMap = [
        'id'=>'id',
        'price'=>'price',
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
