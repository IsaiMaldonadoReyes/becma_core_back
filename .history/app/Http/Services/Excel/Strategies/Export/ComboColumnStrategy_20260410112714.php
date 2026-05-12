<?php 

namespace App\Http\Services\Excel\Strategies\Export;

use App\Http\Services\Excel\Strategies\Contracts\ColumnStrategyInterface;

class ComboColumnStrategy implements ColumnStrategyInterface
{
    public function apply($sheet, $columnConfig, $row)
    {
        $validation = $sheet->getCell($columnConfig['col'] . $row)
            ->getDataValidation();

        $validation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
        $validation->setAllowBlank($columnConfig['required'] ?? false);
        $validation->setShowDropDown(true);

        // Valores del catálogo
        $options = implode(',', $columnConfig['options']);

        $validation->setFormula1("\"$options\"");
    }
}