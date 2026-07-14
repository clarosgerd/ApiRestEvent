<?php
namespace App\Filters;

use Illuminate\Http\Request;

class FormTypeFilter extends ApiFilter
{
    /**/
    protected $safeParams = [
    'id'=>['eq'] ,
    'name'=>['eq'] ,
    'description'=>['eq'] ,
    'color'=>['eq'] ,
    ];
    protected $columnMap = [
        'id'=>'id',
        'name'=>'name',
        'description'=>'description',
        'color'=>'color',
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
