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

        $path = storage_path('app/public/plantillas/formato_prenomina.xlsx');

        $spreadsheet = IOFactory::load($path);

        $sheet = $spreadsheet->getSheetByName('prenomina');


        // Agrupar columnas C a E (Enero a Marzo)
        for ($i = 9; $i <= 40; $i++) {
            $colLetter = Coordinate::stringFromColumnIndex($i);

            $sheet->getColumnDimension($colLetter)
                ->setOutlineLevel(1)
                ->setVisible(false)
                ->setCollapsed(true);
        }


        // Es importante activar el resumen a la derecha
        $sheet->setShowSummaryRight(true);



        // Congeral la fila 3 y columna H
        $sheet->freezePane('I4');
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
}
