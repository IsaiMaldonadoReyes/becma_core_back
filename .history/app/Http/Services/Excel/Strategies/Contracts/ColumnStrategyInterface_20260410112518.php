<?php 

namespace App\Http\Services\Excel\Strategies\Contracts;

interface ColumnStrategyInterface
{
    public function apply($sheet, $columnConfig, $row);
}