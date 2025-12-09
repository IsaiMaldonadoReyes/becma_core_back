<?php

namespace App\Http\Services\Nomina\Import\Incidencias;

use PhpOffice\PhpSpreadsheet\IOFactory;

class IncidenciasExcelReader
{
    protected $sheet;

    public function __construct($file)
    {
        $spreadsheet = IOFactory::load($file->getRealPath());
        $this->sheet = $spreadsheet->getActiveSheet();
    }

    public function getSheet()
    {
        return $this->sheet;
    }

    public function getRowCount(): int
    {
        return $this->sheet->getHighestRow();
    }

    public function get(string $col, int $row): string
    {
        return trim((string)$this->sheet->getCell("{$col}{$row}")->getValue());
    }
}
