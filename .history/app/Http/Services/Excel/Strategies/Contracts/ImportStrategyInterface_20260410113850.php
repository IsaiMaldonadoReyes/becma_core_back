<?php 

namespace App\Services\Excel\Strategies\Contracts;

interface ImportStrategyInterface
{
    public function validate($value, array $columnConfig);

    public function transform($value, array $columnConfig);
}