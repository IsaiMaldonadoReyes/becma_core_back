<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;

class BanorteInterbancarioTxtExport implements FromCollection, WithCustomCsvSettings
{
    protected Collection $data;
    protected string $claveId;
    protected string $cuentaOrigen;
    protected string $rfcOrdenante;
    protected string $referencia;
    protected string $descripcion;

    public function __construct(Collection $data, string $cuentaOrigen, string $rfcOrdenante, string $referencia, string $descripcion)
    {
        $this->data = $data;
        $this->cuentaOrigen = $cuentaOrigen;
        $this->rfcOrdenante = $rfcOrdenante;
        $this->referencia = $referencia;
        $this->descripcion = $descripcion;
    }

    public function collection(): Collection
    {
        return $this->data
            ->filter(fn($row) => ($row['claveBanco'] ?? null) !== '072')
            ->map(fn($row) => $this->buildRow($row))
            ->values(); // 🔑 reindexa
    }

    private function buildRow(array $row): array
    {
        return [
            '04',
            trim($row['campoextra3'] ?? ''),
            $this->formatNumericField($this->cuentaOrigen),
            $this->formatNumericField($row['clabeInterbancaria'] ?? ''),
            $this->formatAmount($row['importe'] ?? 0),
            $this->referencia ?? '',
            $this->descripcion ?? '',
            $this->cleanText($this->rfcOrdenante),
            '', // iva
            '', // fecha
            $this->buildDescription($row['nombreCompleto'] ?? ''),
            '', // clave tipo cambio
        ];
    }

    /* ===============================
     |  Sanitizers / Formatters
     ===============================*/

    private function cleanText(string $value, ?int $maxLength = null): string
    {
        $value = trim($value);

        // Quitar saltos de línea y tabs
        $value = str_replace(["\t", "\r", "\n"], ' ', $value);

        // Quitar acentos y caracteres especiales
        $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        $value = preg_replace('/[^A-Za-z0-9 ]/', '', $value);

        return $maxLength
            ? substr($value, 0, $maxLength)
            : $value;
    }

    private function buildDescription(string $nombre): string
    {
        return $this->cleanText($nombre, 100);
    }

    private function formatNumericField(string $value): string
    {
        $value = trim($value);
        return "{$value}";
    }

    private function formatAmount($value): string
    {
        return number_format((float) $value, 2, '.', '');
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
}
