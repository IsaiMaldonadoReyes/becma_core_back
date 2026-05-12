<?php 

namespace App\Http\Services\Excel\Builders;

class ExcelLayoutBuilder
{
    public function build($sheet, array $columns)
    {
        foreach ($columns as $column) {
            $strategy = ColumnStrategyFactory::make($column['type']);

            for ($row = $column['row_start']; $row <= 1000; $row++) {
                $strategy->apply($sheet, $column, $row);
            }
        }
    }
}