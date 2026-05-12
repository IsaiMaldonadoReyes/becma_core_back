<?php 

namespace App\Http\Services\Excel\Factories;

use App\Http\Services\Excel\Strategies\Contracts\ColumnStrategyInterface;
use App\Http\Services\Excel\Strategies\Export\ComboColumnStrategy;
use App\Http\Services\Excel\Strategies\Export\DateColumnStrategy;
use App\Http\Services\Excel\Strategies\Export\TextColumnStrategy;
use App\Http\Services\Excel\Strategies\Export\NumberColumnStrategy;


class ColumnStrategyFactory
{
    public static function make(string $type): ColumnStrategyInterface
    {
        return match ($type) {
            'text' => new TextColumnStrategy(),
            'number' => new NumberColumnStrategy(),
            'date' => new DateColumnStrategy(),
            'combo' => new ComboColumnStrategy(),
            default => throw new \Exception("Tipo no soportado: $type"),
        };
    }
}