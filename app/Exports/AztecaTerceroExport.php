<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Excel;

class AztecaTerceroExport implements FromCollection, WithHeadings
{
    use Exportable;

    protected Collection $data;
    protected string $cuentaCargo;
    protected string $nombreOrdenante;
    protected string $rfcOrdenante;
    protected string $concepto;

    public function __construct(Collection $data, string $cuentaCargo, string $nombreOrdenante, string $rfcOrdenante, string $concepto)
    {
        $this->data = $data;
        $this->cuentaCargo = $cuentaCargo;
        $this->nombreOrdenante = $nombreOrdenante;
        $this->rfcOrdenante = $rfcOrdenante;
        $this->concepto = $concepto;
    }

    public function collection(): Collection
    {
        return $this->data
            ->filter(fn($row) => ($row['claveBanco'] ?? null) === '127')
            ->map(fn($row) => $this->buildRow($row))
            ->values(); // 🔑 reindexa
    }

    private function buildRow(array $row): array
    {
        return [
            "'" . $this->cuentaCargo,
            $this->cleanText($this->nombreOrdenante, 40),
            $this->rfcOrdenante,
            $this->concepto,
            $this->formatAmount($row['importe'] ?? 0),
            'MXP',
            '127',
            '40',
            "'" . $row['clabeInterbancaria'],
            $this->cleanText($row['nombreCompleto'], 40),
            $this->concepto,
            $row['rfc'],
            '0.00',
            '',
            '03'
        ];
    }

    private function formatAmount($value): string
    {
        return number_format((float) $value, 2, '.', '');
    }

    private function cleanText(string $value, ?int $maxLength = null): string
    {
        $value = trim($value);

        // Quitar saltos de línea y tabs
        $value = str_replace(["\t", "\r", "\n"], ' ', $value);

        // Quitar acentos y caracteres especiales
        return $maxLength
            ? substr($value, 0, $maxLength)
            : $value;
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

    /*
    public function getCsvSettings(): array
    {
        return [
            'delimiter' => "\t",
            'input_encoding' => 'UTF-8',
        ];
    }
    */
    public function title(): string
    {
        return 'Azteca Terceros';
    }
}
