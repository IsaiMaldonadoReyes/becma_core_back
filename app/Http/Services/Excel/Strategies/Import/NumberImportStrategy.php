<?php

use App\Services\Excel\Strategies\Contracts\ImportStrategyInterface;

class NumberImportStrategy implements ImportStrategyInterface
{
    public function validate($value, array $columnConfig)
    {
        if (!is_numeric($value)) {
            throw new \Exception("El campo {$columnConfig['name']} debe ser numérico");
        }
    }

    public function transform($value, array $columnConfig)
    {
        return (float)$value;
    }
}