<?php
namespace App\Filters;

class AgendaItemFilter extends ApiFilter
{
    protected $safeParams = [
        'id'           => ['eq'],
        'event_id'     => ['eq'],
        'form_type_id' => ['eq'],
        'fecha'        => ['eq'],
    ];
    protected $columnMap = [
        'id'           => 'id',
        'event_id'     => 'event_id',
        'form_type_id' => 'form_type_id',
        'fecha'        => 'fecha',
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
