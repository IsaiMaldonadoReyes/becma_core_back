<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Excel;

class AztecaInterbancarioExport implements FromCollection, WithHeadings, WithCustomCsvSettings
{
    use Exportable;

    protected Collection $data;
    protected string $cuentaCargo;
    protected string $nombreOrdenante;
    protected string $rfcOrdenante;

    public function __construct(Collection $data, string $cuentaCargo, string $nombreOrdenante, string $rfcOrdenante)
    {
        $this->data = $data;
        $this->cuentaCargo = $cuentaCargo;
        $this->nombreOrdenante = $nombreOrdenante;
        $this->rfcOrdenante = $rfcOrdenante;
    }

    public function collection(): Collection
    {
        return $this->data->map(function ($row) {
            $nombreCompleto = trim($row->nombre . ' ' . $row->apellidopaterno . ' ' . $row->apellidomaterno);
            $nombreBeneficiario = substr($nombreCompleto, 0, 40);

            $leyendaAbono = substr($nombreCompleto, 0, 30);
            $leyendaCargo = $this->nombreOrdenante;

            return [
                "'" . $this->cuentaCargo,
                substr($this->nombreOrdenante, 0, 40),
                $this->rfcOrdenante,
                substr($leyendaCargo, 0, 30),
                number_format($row->importe, 2, '.', ''),
                'MXP',
                $row->bancopagoelectronico,
                '40', // Tipo de cuenta: 40 = CLABE
                "'" . $row->cuentapagoelectronico,
                substr($nombreBeneficiario, 0, 40),
                substr($leyendaAbono, 0, 30),
                $row->rfc,
                '0.00',
                '',
                '01', // Tipo de Pago: SPEI
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Numero Cuenta Cargo',
            'Nombre titular Cuenta Cargo',
            'RFC del Ordenante',
            'Leyenda Cargo',
            'Importe',
            'Moneda',
            'Banco Receptor',
            'Tipo de Cuenta Beneficiario',
            'Numero de Cuenta Beneficiario',
            'Nombre del Beneficiario',
            'Leyenda Abono',
            'RFC del Beneficiario',
            'IVA',
            'Referencia de Cobranza',
            'Tipo de Pago',
        ];
    }

    public function getCsvSettings(): array
    {
        return [
            'delimiter' => "\t",
            'input_encoding' => 'UTF-8',
        ];
    }
}
