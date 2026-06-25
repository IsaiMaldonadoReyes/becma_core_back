<?php

namespace App\Exports;

use Illuminate\Support\Collection;

class SantanderTercerosTxtExport
{
    protected Collection $data;
    protected string $cuentaOrigen;
    protected string $fechaAplicacion;

    public function __construct(
        Collection $data,
        string $cuentaOrigen,
        ?string $fechaAplicacion = null
    ) {
        $this->data = $data;
        $this->cuentaOrigen = $cuentaOrigen;
        $this->fechaAplicacion = $fechaAplicacion ?? now()->format('mdY');
    }

    public function storeTxt(string $relativePath): string
    {
        $lines = $this->buildLines();

        $content = implode("\r\n", $lines);

        $fullPath = storage_path("app/public/{$relativePath}");

        $dir = dirname($fullPath);

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($fullPath, $content);

        return $fullPath;
    }

    public function buildLines(): array
    {
        $detalle = $this->data
            ->filter(fn($row) => ($row['claveBanco'] ?? null) !== '014')
            ->values();

        $lines = [];

        // Encabezado
        $lines[] = implode('', $this->buildHeader());

        // Detalle
        foreach ($detalle as $index => $row) {
            $lines[] = implode('', $this->buildDetail((array) $row, $index + 2));
        }

        // Resumen
        $lines[] = implode('', $this->buildSummary($detalle));

        return $lines;
    }

    private function buildHeader(): array
    {
        return [
            '1',
            $this->formatSecuencia(1),
            'E',
            $this->formatFecha($this->fechaAplicacion),
            $this->formatCuentaCargo($this->cuentaOrigen, 16),
            $this->formatFecha($this->fechaAplicacion),
        ];
    }

    private function buildDetail(array $row, int $secuencia): array
    {
        return [
            '2',
            $this->formatSecuencia($secuencia),
            $this->formatAlpha($row['codigoempleado'] ?? '', 7),
            $this->formatAlpha($row['ap'] ?? '', 30),
            $this->formatAlpha($row['am'] ?? '', 20),
            $this->formatAlpha($row['nombre'] ?? '', 30),
            $this->formatCuentaCargo($row['cuentaPagoElectronico'] ?? '', 16),
            $this->formatAmountSantander($row['importe'] ?? 0),
            str_repeat(' ', 2),
        ];
    }

    private function buildSummary(Collection $detalle): array
    {
        $totalRegistros = $detalle->count();
        $importeTotal = $detalle->sum(fn($row) => (float) ($row['importe'] ?? 0));

        return [
            '3',
            $this->formatSecuencia($totalRegistros + 2),
            $this->formatSecuencia($totalRegistros),
            $this->formatAmountSantander($importeTotal),
        ];
    }

    private function formatSecuencia(int $value): string
    {
        return str_pad((string) $value, 5, '0', STR_PAD_LEFT);
    }

    private function formatCuentaCargo(?string $value, int $length): string
    {
        $value = trim((string) $value);
        $value = substr($value, 0, $length);

        return str_pad($value, $length, ' ', STR_PAD_RIGHT);
    }

    private function formatAlpha(?string $value, int $length): string
    {
        $value = $this->cleanText((string) $value);
        $value = substr($value, 0, $length);

        return str_pad($value, $length, ' ', STR_PAD_RIGHT);
    }

    private function formatAmountSantander($value): string
    {
        $value = (string) round(((float) $value) * 100);

        return str_pad($value, 18, '0', STR_PAD_LEFT);
    }

    private function formatConceptoPago(?string $value): string
    {
        $value = preg_replace('/\D/', '', (string) $value);
        $value = substr($value, 0, 2);

        return str_pad($value ?: '01', 2, '0', STR_PAD_LEFT);
    }

    private function formatFecha(?string $value): string
    {
        if (empty($value)) {
            return now()->format('mdY');
        }

        try {
            return \Carbon\Carbon::parse($value)->format('mdY');
        } catch (\Throwable $e) {
            return now()->format('mdY');
        }
    }

    private function cleanText(string $value): string
    {
        $value = trim($value);
        $value = str_replace(["\t", "\r", "\n"], ' ', $value);
        $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        $value = preg_replace('/[^A-Za-z0-9 ]/', '', $value);

        return $value;
    }
}
