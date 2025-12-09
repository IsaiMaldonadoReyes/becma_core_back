<?php

namespace App\Http\Services\Nomina;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ExportIncidenciasService
{
    /**
     * Genera el archivo Excel aplicando toda la configuración.
     */
    public function generarExcel(array $config, array $data)
    {
        $spreadsheet = $this->loadTemplate($config['path']);
        $sheet = $this->getWorksheet($spreadsheet, $config['sheet_name']);

        // Validar si hay datos
        if (empty($data)) {
            throw new \Exception("No hay datos para exportar.");
        }

        // Construir matriz ordenada (AQUI INTEGRAMOS TU LÓGICA)
        $matrix = $this->buildMatrix($data);

        // Insertar y escribir
        $this->insertRows($sheet, $config['fila_insert'], count($matrix));

        $this->fillMatrix($sheet, $matrix, $config['col_inicio']);

        // Autosize
        $this->autosizeColumns($sheet, $config['auto_cols'][0], $config['auto_cols'][1]);

        // Grouping
        $this->applyGrouping($sheet, $config['group_rows']);

        // Freeze pane
        $this->applyFreezePane($sheet, $config['freeze_cell']);

        return $spreadsheet; // El controlador hará el StreamedResponse
    }

    /* ===========================================================
     *     MÉTODOS PRIVADOS PARA MANTENER CÓDIGO LIMPIO
     * ===========================================================
     */

    private function loadTemplate(string $path)
    {
        $fullPath = storage_path("app/public/" . $path);

        if (!file_exists($fullPath)) {
            throw new \Exception("La plantilla no existe: {$fullPath}");
        }

        return IOFactory::load($fullPath);
    }

    private function getWorksheet($spreadsheet, string $sheetName): Worksheet
    {
        $sheet = $spreadsheet->getSheetByName($sheetName);

        if (!$sheet) {
            throw new \Exception("La hoja '{$sheetName}' no existe en el archivo Excel.");
        }

        return $sheet;
    }

    private function buildMatrix(array $data): array
    {
        // Obtener orden de columnas del primer registro
        $columnKeys = array_keys($data[0]);

        $matrix = [];

        foreach ($data as $row) {
            $fila = [];

            // Agregar columnas en el orden correcto
            foreach ($columnKeys as $key) {
                $fila[] = $row[$key] ?? null;
            }

            // Agregar columna vacía como separador
            $fila[] = null;

            $matrix[] = $fila;
        }

        return $matrix;
    }

    private function insertRows(Worksheet $sheet, int $filaInsert, int $cantidad)
    {
        $sheet->insertNewRowBefore($filaInsert, $cantidad);
    }

    private function fillMatrix(Worksheet $sheet, array $data, string $startCell)
    {
        $sheet->fromArray($data, null, $startCell);
    }

    private function autosizeColumns(Worksheet $sheet, string $colStart, string $colEnd)
    {
        foreach (range($colStart, $colEnd) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }

    private function applyGrouping(Worksheet $sheet, array $groupRows)
    {
        [$start, $end] = $groupRows;

        for ($i = $start; $i <= $end; $i++) {
            $sheet->getRowDimension($i)->setOutlineLevel(1);
            $sheet->getRowDimension($i)->setCollapsed(true);
        }

        $sheet->setShowSummaryRight(true);
    }

    private function applyFreezePane(Worksheet $sheet, string $startCell)
    {
        /**
         * El freezePane funciona así:
         * freezePane("C10") congela todo lo anterior a la celda C10:
         * → columnas A y B
         * → filas 1 a 9
         */

        $sheet->freezePane($startCell);
    }
}
