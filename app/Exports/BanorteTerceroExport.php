<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class BanorteTerceroExport implements FromCollection, WithHeadings, WithCustomCsvSettings, WithTitle
{
    protected Collection $data;
    protected string $claveId;
    protected string $cuentaOrigen;
    protected string $rfcOrdenante;

    public function __construct(Collection $data, string $claveId, string $cuentaOrigen, string $rfcOrdenante)
    {
        $this->data = $data;
        $this->claveId = $claveId;
        $this->cuentaOrigen = $cuentaOrigen;
        $this->rfcOrdenante = $rfcOrdenante;
    }

    public function collection(): Collection
    {
        return $this->data->map(function ($row) {
            $nombreCompleto = trim($row->nombre . ' ' . $row->apellidopaterno . ' ' . $row->apellidomaterno);
            $descripcion = strlen($nombreCompleto) > 30
                ? substr($nombreCompleto, 0, 30)
                : $nombreCompleto;
            $descripcion = str_replace(["\t", "\r", "\n"], ' ', $descripcion);

            return [
                '02',
                "'{$this->claveId}",
                "'{$this->cuentaOrigen}",
                "'{$row->cuentapagoelectronico}",
                number_format($row->importe, 2, '.', ''),
                '',
                $descripcion,
                $this->rfcOrdenante,
                '0.00',
                now()->format('Y-m-d'),
                'X',
                '',
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Operación',
            'ClaveID',
            'CuentaOrigen',
            'CLABE',
            'Importe',
            'Referencia',
            'Descripción',
            'RFC',
            'IVA',
            'FechaAplicacion',
            'InstruccionPago',
            'ClaveTipoCambio'
        ];
    }

    public function getCsvSettings(): array
    {
        return [
            'delimiter' => "\t",
            'enclosure' => '',
            'line_ending' => "\r\n",
            'use_bom' => false,
        ];
    }

    public function title(): string
    {
        return 'BanorteTercero';
    }
}
