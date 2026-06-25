<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;
use Maatwebsite\Excel\Concerns\Exportable;

class AztecaExcelExport implements FromCollection, WithCustomCsvSettings
{
    use Exportable;

    protected Collection $data;
    protected string $concepto;

    public function __construct(Collection $data, string $concepto)
    {
        $this->data = $data;
        $this->concepto = $concepto;
    }

    private function cleanText(?string $value): string
    {
        $value = trim($value ?? '');
        $value = str_replace(["\t", "\r", "\n"], ' ', $value);
        $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        $value = preg_replace('/[^A-Za-z0-9 ]/', '', $value);

        return trim($value);
    }

    private function formatClabe(?string $clabe): string
    {
        $clabe = trim($clabe ?? '');

        // Solo números
        return "'" . $clabe;
    }

    public function collection(): Collection
    {
        return $this->data->map(function ($row) {

            $nombre = $this->cleanText($row['nombreCompleto'] ?? '');

            $clabe  = $this->formatClabe($row['clabeInterbancaria'] ?? '');

            $monto  = number_format((float) $row['importe'], 2, '.', '');

            $campoextra1 = $this->cleanText($row['campoextra1'] ?? '');

            return [
                $clabe,
                $monto,
                $this->concepto, // Concepto fijo
                $campoextra1,
                $nombre,
            ];
        });
    }

    public function getCsvSettings(): array
    {
        return [
            'delimiter' => ',',
            'enclosure' => '',
            'line_ending' => "\r\n",
            'use_bom' => false,
            'include_separator_line' => false,
        ];
    }
}
