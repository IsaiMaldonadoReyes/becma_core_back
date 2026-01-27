<?php

namespace App\Http\Controllers\nomina;

use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Chart\Chart;
use PhpOffice\PhpSpreadsheet\Chart\ChartColor;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues;
use PhpOffice\PhpSpreadsheet\Chart\Layout;
use PhpOffice\PhpSpreadsheet\Chart\Legend as ChartLegend;
use PhpOffice\PhpSpreadsheet\Chart\PlotArea;
use PhpOffice\PhpSpreadsheet\Chart\Title;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Settings;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

use App\Http\Controllers\Controller;

class PruebaController extends Controller
{
    public function exportExcel(Request $request)
    {

        // Celdas de filtros seleccionados
        $xlCeldaCliente = 'C2';
        $xlCeldaTipoDeEmpresa = 'C3';
        $xlCeldaEmpresa = 'C4';
        $xlCeldaEjercicio = 'C5';
        $xlCeldaTipoDePeriodo = 'C6';
        $xlCeldaRangoDePeriodos = 'C7';
        $xlCeldaRangoDeDepartamentos = 'C8';
        $xlCeldaRangoDeEmpleados = 'C9';

        // Celdas para tomar formatos de referencia base
        $xlCeldaFormatoEncabezadoFijo = 'A10';
        $xlCeldaFormatoTituloDinamico = 'I9';
        $xlCeldaFormatoEncabezadoNormalDinamico = 'I9';
        $xlCeldaFormatoEncabezadoTotalDinamico = 'J9';
        $xlCeldaFormatoEncabezadoNetoDinamico = 'K9';



        $path = storage_path('app/public/plantillas/formato_prenomina.xlsx');

        $spreadsheet = IOFactory::load($path);

        $sheet = $spreadsheet->getSheetByName('prenomina');

        // 2. Leer JSON
        $jsonPath = storage_path('app/public/json/datos_fijos_full.json');
        $empleados = json_decode(file_get_contents($jsonPath), true);

        // 3. Fila base
        $filaBase = 10;   // la fila que contiene el formato
        $filaInicio = 10; // aquí empieza el primer empleado

        foreach ($empleados as $index => $emp) {

            $filaActual = $filaInicio + $index;

            // 4. Copiar formato de la fila base
            if ($filaActual !== $filaBase) {
                copyRow($sheet, $filaBase, $filaActual);
            }

            // 5. Escribir datos en columnas A–H
            $sheet->setCellValue("A{$filaActual}", $emp['id']);
            $sheet->setCellValue("B{$filaActual}", $emp['nombre']);
            $sheet->setCellValue("C{$filaActual}", $emp['puesto']);
            $sheet->setCellValue("D{$filaActual}", $emp['fechaDeIngresoCliente']);
            $sheet->setCellValue("E{$filaActual}", $emp['fechaDeIngresoGape']);
            $sheet->setCellValue("F{$filaActual}", $emp['nss']);
            $sheet->setCellValue("G{$filaActual}", $emp['rfc']);
            $sheet->setCellValue("H{$filaActual}", $emp['curp']);
        }



        // Agrupador de filtros seleccionados
        $this->rowRangeGroup($sheet, 1, 9);  // Agrupa filas 5–12

        // Agrupadores de columnas
        $this->columnRangeGroup($sheet, 'I', 'AM'); // Nómina en base a ingresos reales

        $this->columnRangeGroup($sheet, 'AP', 'BD');

        //$sheet->insertNewColumnBefore('J', 1);

        //$this->duplicateColumn($sheet, 'I', 'J');
        //$this->duplicateColumnFormat($sheet, 'I', 'J');
        //$this->duplicateColumnFormatFromRow($sheet, 'I', 'J', 11);
        // $this->copyEntireColumn($sheet, 'I', 'J');


        // Es importante activar el resumen a la derecha
        $sheet->setShowSummaryRight(true);



        // Congeral la fila 3 y columna H
        $sheet->freezePane('C12');
        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        //$writer->save(storage_path('app/public/resultados/modificado.xlsx'));


        // Descargar el archivo
        $response = new StreamedResponse(function () use ($writer) {

            // Limpiar el buffer de salida
            if (ob_get_level()) {
                ob_end_clean();
            }
            $writer->setIncludeCharts(true); // Incluir gráficas en el archivo

            $writer->save('php://output');
        });

        // Configurar los headers para la descarga
        $filename = "myfile.xlsx";
        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Disposition', 'attachment;filename="' . $filename . '"');
        $response->headers->set('Content-Transfer-Encoding', 'binary');
        $response->headers->set('Cache-Control', 'must-revalidate');
        $response->headers->set('Pragma', 'public');

        return $response;
    }

    function columnRangeGroup($sheet, string $startCol, string $endCol)
    {
        $startIndex = Coordinate::columnIndexFromString($startCol);
        $endIndex   = Coordinate::columnIndexFromString($endCol);

        for ($i = $startIndex; $i <= $endIndex; $i++) {
            $colLetter = Coordinate::stringFromColumnIndex($i);

            $sheet->getColumnDimension($colLetter)
                ->setOutlineLevel(1)
                ->setVisible(false)
                ->setCollapsed(true);
        }
    }

    function rowRangeGroup($sheet, int $startRow, int $endRow)
    {
        for ($row = $startRow; $row <= $endRow; $row++) {
            $sheet->getRowDimension($row)
                ->setOutlineLevel(1)
                ->setVisible(false)
                ->setCollapsed(false);
        }
    }

    function duplicateColumn($sheet, string $sourceColumn, string $targetColumn)
    {
        $sourceIndex = Coordinate::columnIndexFromString($sourceColumn);
        $targetIndex = Coordinate::columnIndexFromString($targetColumn);

        $highestRow = $sheet->getHighestRow();

        // 1. Copiar valores y fórmulas
        for ($row = 1; $row <= $highestRow; $row++) {
            $value = $sheet->getCellByColumnAndRow($sourceIndex, $row)->getValue();
            $sheet->setCellValueByColumnAndRow($targetIndex, $row, $value);
        }

        // 2. Copiar estilos
        $styleSource = $sheet->getStyle($sourceColumn . '1:' . $sourceColumn . $highestRow);
        $sheet->duplicateStyle($styleSource, $targetColumn . '1:' . $targetColumn . $highestRow);

        // 3. Copiar ancho de columna
        $width = $sheet->getColumnDimension($sourceColumn)->getWidth();
        $sheet->getColumnDimension($targetColumn)->setWidth($width);
    }

    function duplicateColumnFormatMultiple($sheet, string $sourceColumn, string $startTargetColumn, int $count)
    {
        $startIndex = Coordinate::columnIndexFromString($startTargetColumn);

        for ($i = 0; $i < $count; $i++) {
            $targetColumn = Coordinate::stringFromColumnIndex($startIndex + $i);
            $this->duplicateColumnFormat($sheet, $sourceColumn, $targetColumn);
        }
    }

    function duplicateColumnFormat($sheet, string $sourceColumn, string $targetColumn)
    {
        $highestRow = $sheet->getHighestRow();

        // Copiar estilos completos
        $styleSource = $sheet->getStyle($sourceColumn . '1:' . $sourceColumn . $highestRow);
        $sheet->duplicateStyle($styleSource, $targetColumn . '1:' . $targetColumn . $highestRow);

        // Copiar ancho
        $width = $sheet->getColumnDimension($sourceColumn)->getWidth();
        $sheet->getColumnDimension($targetColumn)->setWidth($width);

        // Copiar autoSize si aplica
        $autoSize = $sheet->getColumnDimension($sourceColumn)->getAutoSize();
        $sheet->getColumnDimension($targetColumn)->setAutoSize($autoSize);
    }

    function duplicateColumnFormatFromRow($sheet, string $sourceColumn, string $targetColumn, int $startRow)
    {
        $highestRow = $sheet->getHighestRow(); // Última fila con contenido en la hoja

        // Copiar el estilo desde rowStart → highestRow
        $styleSource = $sheet->getStyle($sourceColumn . $startRow . ':' . $sourceColumn . $highestRow);
        $sheet->duplicateStyle(
            $styleSource,
            $targetColumn . $startRow . ':' . $targetColumn . $highestRow
        );

        // Copiar ancho de columna completo
        $width = $sheet->getColumnDimension($sourceColumn)->getWidth();
        $sheet->getColumnDimension($targetColumn)->setWidth($width);

        // Copiar autoSize si aplica
        $autoSize = $sheet->getColumnDimension($sourceColumn)->getAutoSize();
        $sheet->getColumnDimension($targetColumn)->setAutoSize($autoSize);
    }


    function copyEntireColumn($sheet, string $sourceColumn, string $targetColumn)
    {
        $srcIndex = Coordinate::columnIndexFromString($sourceColumn);
        $tgtIndex = Coordinate::columnIndexFromString($targetColumn);
        $highestRow = $sheet->getHighestRow();

        // 1. Copiar valores y fórmulas
        for ($row = 1; $row <= $highestRow; $row++) {
            $cell = $sheet->getCellByColumnAndRow($srcIndex, $row);
            $sheet->setCellValueByColumnAndRow($tgtIndex, $row, $cell->getValue());
        }

        // 2. Copiar estilos
        $sourceStyle = $sheet->getStyle($sourceColumn . '1:' . $sourceColumn . $highestRow);
        $sheet->duplicateStyle($sourceStyle, $targetColumn . '1:' . $targetColumn . $highestRow);

        // 3. Copiar ancho de la columna
        $width = $sheet->getColumnDimension($sourceColumn)->getWidth();
        $sheet->getColumnDimension($targetColumn)->setWidth($width);

        // 4. Copiar autosize
        $autoSize = $sheet->getColumnDimension($sourceColumn)->getAutoSize();
        $sheet->getColumnDimension($targetColumn)->setAutoSize($autoSize);

        // 5. Copiar merges (celdas combinadas)
        foreach ($sheet->getMergeCells() as $merge) {
            if (strpos($merge, $sourceColumn) === 0) {
                // Ejemplo: "H5:H7"
                [$start, $end] = explode(':', $merge);

                $startRow = preg_replace('/\D/', '', $start);
                $endRow   = preg_replace('/\D/', '', $end);

                $newStart = $targetColumn . $startRow;
                $newEnd   = $targetColumn . $endRow;

                $sheet->mergeCells("$newStart:$newEnd");
            }
        }

        // 6. Copiar data validation (listas, números, fechas)
        for ($row = 1; $row <= $highestRow; $row++) {
            $validation = $sheet->getCell($sourceColumn . $row)->getDataValidation();

            $sheet->getCell($targetColumn . $row)->setDataValidation(clone $validation);
        }
    }
}
