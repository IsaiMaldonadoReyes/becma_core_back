<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;
use Maatwebsite\Excel\Concerns\Exportable;

class FondeadoraExport implements FromCollection, WithHeadings, WithCustomCsvSettings
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

        // Quitar acentos y caracteres especiales
        $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);

        // Eliminar cualquier carácter que no sea letra, número o espacio
        return preg_replace('/[^A-Za-z0-9 ]/', '', $value);
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

            $nombre = $this->cleanText($row['nombre'] ?? '');

            $clabe  = $this->formatClabe($row['clabeInterbancaria'] ?? '');

            $monto  = number_format((float) $row['importe'], 2, '.', '');

            return [
                $clabe,
                $nombre,
                $monto,
                $this->concepto, // Concepto fijo
                '', // Email (opcional)
                '', // Tags (opcional)
                '', // Comentario (opcional)
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Clabe destinatario',
            'Nombre o razon social destinatario',
            'Monto',
            'Concepto',
            'Email (opcional)',
            'Tags separados por comas (opcional)',
            'Comentario (opcional)',
        ];
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
