<?php

namespace App\Exports;

use Illuminate\Support\Collection;

class SantanderInterbancariosTxtExport
{
    protected Collection $data;
    protected string $cuentaOrigen;
    protected string $referencia;
    protected string $descripcion;

    public function __construct(
        Collection $data,
        string $cuentaOrigen,
        string $referencia,
        string $descripcion
    ) {
        $this->data = $data;
        $this->cuentaOrigen = $cuentaOrigen;
        $this->referencia = $referencia;
        $this->descripcion = $descripcion;
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
        return $this->data
            ->filter(fn($row) => ($row['claveBanco'] ?? null) !== '014')
            ->map(function ($row) {
                return implode('', $this->buildRow((array) $row));
            })
            ->values()
            ->toArray();
    }

    private function buildRow(array $row): array
    {
        return [
            'LTX05',
            $this->formatCuentaCargo($this->cuentaOrigen),
            $this->formatClabe($row['clabeInterbancaria'] ?? ''),
            $this->formatBancoReceptor($row['bancoClaveTransferencia'] ?? ''),
            $this->formatNombre($row['nombreCompleto'] ?? ''),
            '0101',
            $this->formatAmountSantander($row['importe'] ?? 0),
            '01001',
            $this->formatDescripcion($this->descripcion ?? ''),
            $this->formatReferencia($this->referencia ?? ''),
            str_repeat(' ', 40),
        ];
    }

    private function formatCuentaCargo(string $value): string
    {
        $value = substr(trim($value), 0, 18);

        return str_pad($value, 18, ' ', STR_PAD_RIGHT);
    }

    private function formatClabe(?string $value): string
    {
        $value = preg_replace('/\D/', '', (string) $value);
        $value = substr($value, 0, 20);

        return str_pad($value, 20, '0', STR_PAD_LEFT);
    }

    private function formatBancoReceptor(?string $value): string
    {
        // Asegurar string
        $value = strtoupper(trim((string) $value));

        // Limpiar caracteres raros (opcional)
        $value = $this->cleanText($value);

        // Cortar a 5 caracteres
        $value = substr($value, 0, 5);

        // Rellenar con espacios a la derecha
        return str_pad($value, 5, ' ', STR_PAD_RIGHT);
    }

    private function formatNombre(string $value): string
    {
        $value = substr($this->cleanText($value), 0, 40);

        return str_pad($value, 40, ' ', STR_PAD_RIGHT);
    }

    private function formatAmountSantander($value): string
    {
        $value = (string) round(((float) $value) * 100);

        return str_pad($value, 18, '0', STR_PAD_LEFT);
    }

    private function formatDescripcion(string $value): string
    {
        $value = substr($this->cleanText($value), 0, 40);

        return str_pad($value, 40, ' ', STR_PAD_RIGHT);
    }

    private function formatReferencia(?string $value): string
    {
        $value = preg_replace('/\D/', '', (string) $value);
        $value = substr($value, 0, 7);

        return str_pad($value, 7, ' ', STR_PAD_LEFT);
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
