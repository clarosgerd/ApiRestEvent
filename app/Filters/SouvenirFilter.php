<?php
namespace App\Filters;

use Illuminate\Http\Request;

class SouvenirFilter extends ApiFilter
{
    /**/
    protected $safeParams = [
    'id'=>['eq'] ,
    'name'=>['eq'] ,
    'icon'=>['eq'] ,
    'price'=>['eq'] ,
    ];
    protected $columnMap = [
        'id'=>'id',
        'name'=>'name',
        'icon'=>'icon',
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
