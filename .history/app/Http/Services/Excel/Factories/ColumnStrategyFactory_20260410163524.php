<?php

namespace App\Services\Excel\Factories;

use App\Services\Excel\Strategies\Export\TextColumnStrategy;
use App\Services\Excel\Strategies\Export\NumberColumnStrategy;
use App\Services\Excel\Strategies\Export\DateColumnStrategy;
use App\Services\Excel\Strategies\Export\ComboColumnStrategy;

class ColumnStrategyFactory
{
    public static function make(string $type)
    {
        return match ($type) {
            'texto' => new TextColumnStrategy(),
            'numero' => new NumberColumnStrategy(),
            'fecha' => new DateColumnStrategy(),
            'combo' => new ComboColumnStrategy(),
            default => throw new \Exception("Tipo no soportado: $type"),
        };
    }
}