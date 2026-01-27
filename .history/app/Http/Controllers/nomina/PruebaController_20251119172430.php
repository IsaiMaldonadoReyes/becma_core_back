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



        // Agrupador de filtros seleccionados
        $this->rowRangeGroup($sheet, 1, 9);  // Agrupa filas 5–12

        // Agrupadores de columnas
        $this->columnRangeGroup($sheet, 'I', 'AM'); // Nómina en base a ingresos reales

        $this->columnRangeGroup($sheet, 'AP', 'BD');

        $sheet->insertNewColumnBefore('J', 1);

        $this->duplicateColumn($sheet, 'I', 'J');


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
}
