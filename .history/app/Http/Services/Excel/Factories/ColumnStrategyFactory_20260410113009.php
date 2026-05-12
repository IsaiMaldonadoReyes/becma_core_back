<?php 

namespace App\Http\Services\Excel\Factories;

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