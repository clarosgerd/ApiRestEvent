<?php
namespace App\Filters;

use Illuminate\Http\Request;

class ParticipanteFilter extends ApiFilter
{
    /**/
    protected $safeParams = [
    'id'=>['eq'] ,
   
    ];
    protected $columnMap = [
        'id'=>'id',
    
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
