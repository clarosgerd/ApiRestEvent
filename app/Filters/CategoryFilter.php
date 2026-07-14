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
    
    ];
    protected $columnMap = [
        'id'=>'id',
        'name'=>'name',
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
