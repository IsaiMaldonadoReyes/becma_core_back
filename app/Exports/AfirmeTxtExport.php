<?php

namespace App\Exports;

use Carbon\Carbon;
use Illuminate\Support\Collection;

class AfirmeTxtExport
{
    protected Collection $data;
    protected string $cuentaOrigen;
    protected string $rfc;
    protected string $razonSocial;
    protected string $fechaAplicacion;
    protected string $descripcion;

    public function __construct(
        Collection $data,
        string $cuentaOrigen,
        string $rfc,
        string $razonSocial,
        string $fechaAplicacion,
        string $descripcion
    ) {
        $this->data = $data;
        $this->cuentaOrigen = $cuentaOrigen;
        $this->rfc = $rfc;
        $this->razonSocial = $razonSocial;
        $this->fechaAplicacion = $fechaAplicacion;
        $this->descripcion = $descripcion;
    }

    public function storeTxt(string $relativePath): string
    {
        $content = implode("\r\n", $this->buildLines());

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
        $detalle = $this->data->values();

        $lines = [];

        $lines[] = implode('', $this->buildHeader($detalle));

        foreach ($detalle as $index => $row) {
            $lines[] = implode('', $this->buildDetail((array) $row, $index + 2));
        }

        $lines[] = implode('', $this->buildSummary($detalle, $detalle->count() + 2));

        return $lines;
    }

    private function buildHeader(Collection $detalle): array
    {
        return [
            '01',
            $this->formatSecuencia(1),
            $this->formatDate($this->fechaAplicacion),
            '01', // código divisa
            '1',  // modalidad
            '062', // banco presentador Afirme
            '02', // tipo operación
            $this->formatDate($this->fechaAplicacion),
            '40', // tipo cuenta ordenante
            $this->formatNumeric($this->cuentaOrigen, 20),
            $this->formatText($this->razonSocial, 40),
            $this->formatText($this->rfc, 18),
            $this->formatSecuencia($detalle->count()),
            $this->formatAmount($detalle->sum(fn($r) => (float) ($r['importe'] ?? 0)), 18),
            $this->formatText($this->descripcion, 40),
        ];
    }

    private function buildDetail(array $row, int $secuencia): array
    {
        $referenciaNumerica = $secuencia - 1;

        return [
            '02',
            $this->formatSecuencia($secuencia),
            '60', // código operación
            $this->formatNumeric($row['claveBanco'] ?? '', 3),
            $this->formatAmount($row['importe'] ?? 0, 15),
            '40', // tipo cuenta receptor
            $this->formatNumeric($row['clabeInterbancaria'] ?? '', 20),
            $this->formatText($row['nombreCompleto'], 40),
            $this->formatText($row['rfc'], 18),
            str_repeat(' ', 40), // referencia servicio vacío
            str_repeat(' ', 40), // nombre titular servicio vacío
            str_repeat('0', 15), // IVA vacío
            $this->formatSecuencia($referenciaNumerica),
            $this->formatText($this->descripcion, 40),
        ];
    }

    private function buildSummary(Collection $detalle, int $secuencia): array
    {
        return [
            '09',
            $this->formatSecuencia($secuencia),
            $this->formatSecuencia($detalle->count()),
            $this->formatAmount($detalle->sum(fn($r) => (float) ($r['importe'] ?? 0)), 18),
        ];
    }

    private function formatSecuencia(int $value): string
    {
        return str_pad((string) $value, 7, '0', STR_PAD_LEFT);
    }

    private function formatDate(?string $value): string
    {
        return Carbon::parse($value)->format('Ymd');
    }

    private function formatAmount($value, int $length): string
    {
        $value = (string) round(((float) $value) * 100);

        return str_pad(substr($value, 0, $length), $length, '0', STR_PAD_LEFT);
    }

    private function formatNumeric(?string $value, int $length): string
    {
        $value = preg_replace('/\D/', '', (string) $value);
        $value = substr($value, 0, $length);

        return str_pad($value, $length, '0', STR_PAD_LEFT);
    }

    private function formatText(?string $value, int $length): string
    {
        $value = $this->cleanText((string) $value);
        $value = substr($value, 0, $length);

        return str_pad($value, $length, ' ', STR_PAD_RIGHT);
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
