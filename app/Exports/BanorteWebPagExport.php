<?php

namespace App\Exports;

use Carbon\Carbon;
use Illuminate\Support\Collection;

class BanorteWebPagExport
{
    protected Collection $data;
    protected string $cuentaOrigen;
    protected string $fechaAplicacion;
    protected string $emisora = '05797';

    public function __construct(
        Collection $data,
        string $cuentaOrigen,
        string $fechaAplicacion
    ) {
        $this->data = $data;
        $this->cuentaOrigen = $cuentaOrigen;
        $this->fechaAplicacion = $fechaAplicacion;
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
        $detalle = $this->data
            //->filter(fn ($row) => ($row['claveBanco'] ?? null) === '072')
            ->values();

        $lines = [];

        $lines[] = implode('', $this->buildHeader($detalle));

        foreach ($detalle as $row) {
            $lines[] = implode('', $this->buildDetail((array) $row));
        }

        return $lines;
    }

    private function buildHeader(Collection $detalle): array
    {
        $importeTotal = $detalle->sum(fn ($r) => (float) ($r['importe'] ?? 0));

        return [
            'H',                                      // 1
            'NE',                                     // 2
            $this->formatNumeric($this->emisora, 5),  // 3
            $this->formatDate($this->fechaAplicacion),// 4
            '01',                                     // 5 consecutivo
            $this->formatNumeric($detalle->count(), 6), // 6 total registros enviados
            $this->formatAmount($importeTotal, 15),   // 7 importe total enviados
            str_repeat('0', 6),                       // 8 total altas
            str_repeat('0', 15),                      // 9 importe altas
            str_repeat('0', 6),                       // 10 total bajas
            str_repeat('0', 15),                      // 11 importe bajas
            str_repeat('0', 6),                       // 12 cuentas a verificar
            '0',                                      // 13 acción
            str_repeat('0', 4),                       // 14 espacios
            str_repeat('0', 8),                       // 15 fecha adelanto fin
            $this->formatNumeric($this->cuentaOrigen, 10), // 16 cuenta cargo
            str_repeat('0', 55),                      // 17 filler
        ];
    }

    private function buildDetail(array $row): array
    {
        return [
            'D',                                                // 1
            $this->formatDate($this->fechaAplicacion),           // 2
            $this->formatNumeric($row['codigoempleado'], 10), // 3
            str_repeat(' ', 40),                                // 4 referencia servicio
            str_repeat(' ', 40),                                // 5 referencia leyenda ordenante
            $this->formatAmount($row['importe'] ?? 0, 15),       // 6 importe
            '072',                                              // 7 banco receptor
            '01',                                               // 8 tipo cuenta
            $this->formatNumeric($row['cuentaPagoElectronico'] ?? '', 18), // 9 número cuenta
            '0',                                                // 10 tipo movimiento
            str_repeat(' ', 1),                                 // 11 acción
            str_repeat('0', 8),                                 // 12 IVA
            str_repeat(' ', 18),                                // 13 filler
        ];
    }

    private function formatDate(?string $value): string
    {
        return Carbon::parse($value)->format('Ymd');
    }

    private function formatNumeric($value, int $length): string
    {
        $value = preg_replace('/\D/', '', (string) $value);
        $value = substr($value, 0, $length);

        return str_pad($value, $length, '0', STR_PAD_LEFT);
    }

    private function formatAmount($value, int $length): string
    {
        $value = (string) round(((float) $value) * 100);
        $value = substr($value, 0, $length);

        return str_pad($value, $length, '0', STR_PAD_LEFT);
    }
}
