<?php
namespace App\Filters;

class AuspiciadorFilter extends ApiFilter
{
    protected $safeParams = [
        'id'       => ['eq'],
        'event_id' => ['eq'],
        'nombre'   => ['eq'],
    ];
    protected $columnMap = [
        'id'       => 'id',
        'event_id' => 'event_id',
        'nombre'   => 'nombre',
    ];
    protected $operatorMap = [
        'eq'  => '=',
        'gt'  => '>',
        'lt'  => '<',
        'gte' => '>=',
        'lte' => '<=',
        'ne'  => '!=',
    ];
}
