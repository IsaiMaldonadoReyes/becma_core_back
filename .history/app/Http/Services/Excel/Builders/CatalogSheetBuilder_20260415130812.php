<?php

namespace App\Http\Services\Excel\Builders;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CatalogSheetBuilder
{
    protected string $sheetName = 'catalogos';
    protected array $catalogMap = []; // para reutilizar rangos

    public function build(Spreadsheet $spreadsheet, array &$cellsConfig): void
    {
        $sheet = new Worksheet($spreadsheet, $this->sheetName);
        $spreadsheet->addSheet($sheet);

        $currentColumn = 'A';

        foreach ($cellsConfig as &$column) {

            if (($column['tipoDeColumna'] ?? null) !== 'combo') continue;

            $options = $column['options'] ?? [];

            if (empty($options)) continue;

            // 🔥 CLAVE: reutilizar si ya existe
            $key = md5(json_encode($options));

            if (isset($this->catalogMap[$key])) {
                $column['catalogRange'] = $this->catalogMap[$key];
                continue;
            }

            $row = 1;

            foreach ($options as $option) {
                $sheet->setCellValue("{$currentColumn}{$row}", $option);
                $row++;
            }

            #$range = "{$this->sheetName}!{$currentColumn}1:{$currentColumn}" . ($row - 1);
            $range = "{$this->sheetName}!\${$currentColumn}\$1:\${$currentColumn}\$" . ($row - 1);

            $column['catalogRange'] = $range;

            $this->catalogMap[$key] = $range;

            // siguiente columna
            $currentColumn++;
        }

        // ocultar hoja
        $sheet->setSheetState(Worksheet::SHEETSTATE_HIDDEN);
    }
}