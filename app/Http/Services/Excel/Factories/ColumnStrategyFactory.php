<?php 

namespace App\Http\Services\Excel\Factories;

use App\Http\Services\Excel\Strategies\Contracts\ColumnStrategyInterface;
use App\Http\Services\Excel\Strategies\Export\ComboColumnStrategy;
use App\Http\Services\Excel\Strategies\Export\DateColumnStrategy;


class ColumnStrategyFactory
{
    public static function make(string $type): ColumnStrategyInterface
    {
        return match ($type) {
            'fecha' => new DateColumnStrategy(),
            'combo' => new ComboColumnStrategy(),
            default => throw new \Exception("Tipo no soportado: $type"),
        };
    }
}