<?php 

namespace App\Services\Excel\Builders;

use App\Services\Excel\Factories\ColumnStrategyFactory;
use App\Services\Excel\Factories\ColumnStrategyFactory;

class ExcelLayoutBuilder
{
    public function apply($sheet, array $cellsConfig)
    {
        foreach ($cellsConfig as $column) {

            $strategy = ColumnStrategyFactory::make($column['tipoDeColumna']);

            $col = $column['columna'];
            $rowStart = $column['filaInicialInformacion'];

            for ($row = $rowStart; $row <= 1000; $row++) {
                $cell = $col . $row;

                $strategy->apply($sheet, $cell, $column);
            }

            // 👉 Título
            $sheet->setCellValue(
                $column['columna'] . $column['filaInicialTitulos'],
                $column['titulo']
            );
        }
    }
}