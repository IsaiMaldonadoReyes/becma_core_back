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

    public function __construct(Collection $data)
    {
        $this->data = $data;
    }

    public function collection(): Collection
    {
        return $this->data->map(function ($row) {
            $clabe = "'" . $row->cuentapagoelectronico; // apóstrofe para evitar notación científica
            $nombre = strtoupper(trim(preg_replace('/[^\x20-\x7E]/', '', $row->nombre . ' ' . $row->apellidopaterno . ' ' . $row->apellidomaterno)));
            $monto = number_format($row->importe, 2, '.', '');

            return [
                $clabe,
                $nombre,
                $monto,
                'PAGO', // Concepto fijo
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
