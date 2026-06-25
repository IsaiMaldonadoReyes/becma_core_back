<?php

namespace App\Exports;

use Illuminate\Support\Collection;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

class BankaoolExcelExport
{
    protected Collection $data;

    protected string $referencia;
    protected string $descripcion;

    protected string $nombreHoja = 'SPEI';
    protected string $rutaPlantilla = 'plantillas/dispersion/Bankaool.xlsx';

    public function __construct(Collection $data, string $referencia, string $descripcion)
    {
        $this->data = $data;
        $this->referencia = $referencia;
        $this->descripcion = $descripcion;
    }


    public function store(string $relativePath, string $disk = 'public'): string
    {
        $spreadsheet = $this->loadTemplate($this->rutaPlantilla);
        $sheet = $this->getWorksheet($spreadsheet, $this->nombreHoja);

        $this->fillSheet($sheet);

        $fullPath = storage_path("app/{$disk}/{$relativePath}");

        $dir = dirname($fullPath);

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save($fullPath);

        return $fullPath;
    }

    private function fillSheet(Worksheet $sheet): void
    {
        $filaInicio = 7;
        $filaActual = $filaInicio;

        foreach ($this->collection() as $row) {
            $sheet->setCellValue("A{$filaActual}", $row['banco_destino']);

            $sheet->setCellValueExplicit(
                "B{$filaActual}",
                $row['cuenta_clabe'],
                DataType::TYPE_STRING
            );

            $sheet->setCellValue("C{$filaActual}", $row['nombre_beneficiario']);
            $sheet->setCellValue("D{$filaActual}", $row['monto']);
            $sheet->setCellValue("E{$filaActual}", $row['referencia']);
            $sheet->setCellValue("F{$filaActual}", $row['concepto_pago']);

            $filaActual++;
        }
    }

    private function collection(): Collection
    {
        return $this->data
            ->map(fn($row) => $this->buildRow((array) $row))
            ->values();
    }

    private function buildRow(array $row): array
    {
        return [
            'banco_destino' => $row['bancoDestinoBankaool'],
            'cuenta_clabe' => $this->formatNumericField($row['clabeInterbancaria']),
            'nombre_beneficiario' => $this->cleanText($row['nombreCompleto']),
            'monto' => $this->formatAmount($row['importe'] ?? 0),
            'referencia' => $this->referencia,
            'concepto_pago' => $this->descripcion,
        ];
    }

    private function getWorksheet($spreadsheet, string $sheetName): Worksheet
    {
        $sheet = $spreadsheet->getSheetByName($sheetName);

        if (!$sheet) {
            throw new \Exception("La hoja '{$sheetName}' no existe en la plantilla Bankaool.");
        }

        return $sheet;
    }

    private function loadTemplate(string $path)
    {
        $fullPath = storage_path("app/public/" . $path);

        if (!file_exists($fullPath)) {
            throw new \Exception("La plantilla no existe: {$fullPath}");
        }

        return IOFactory::load($fullPath);
    }

    private function cleanText(?string $value, ?int $maxLength = null): string
    {
        $value = trim((string) $value);
        $value = str_replace(["\t", "\r", "\n"], ' ', $value);
        $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        $value = preg_replace('/[^A-Za-z0-9 ]/', '', $value);

        return $maxLength ? substr($value, 0, $maxLength) : $value;
    }

    private function formatNumericField(?string $value): string
    {
        return preg_replace('/\D/', '', (string) $value);
    }

    private function formatAmount($value): float
    {
        return round((float) $value, 2);
    }
}
