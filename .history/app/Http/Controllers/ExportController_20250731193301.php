<?php

namespace App\Http\Controllers;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Chart\Chart;
use PhpOffice\PhpSpreadsheet\Chart\ChartColor;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues;
use PhpOffice\PhpSpreadsheet\Chart\Layout;
use PhpOffice\PhpSpreadsheet\Chart\Legend as ChartLegend;
use PhpOffice\PhpSpreadsheet\Chart\PlotArea;
use PhpOffice\PhpSpreadsheet\Chart\Title;
use Symfony\Component\HttpFoundation\StreamedResponse;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Settings;
use Illuminate\Http\Request;

class ExportController extends Controller
{
    public function exportExcel(Request $request)
    {

        $id = $request->id;

        $data = $request->info;

        $tipoGrafica = $request->tipo;

        //return $data;

        $spreadsheet = new Spreadsheet();
        $worksheet = $spreadsheet->getActiveSheet();
        $worksheet->fromArray($data);
        /*$worksheet->fromArray(
            [
                ['', 2010, 2011, 2012],
                ['Q1', 12, 15, 21],
                ['Q2', 56, 73, 86],
                ['Q3', 52, 61, 69],
                ['Q4', 30, 32, 0],
            ]
            [
                [null,"2024-09-01","2024-09-08","2024-09-15","2024-09-22"],
                ["HELVEX",0,28,0,0],
                ["URREA",32,3,0,0]
            ]
        );*/

        // Obtener los encabezados (primera fila)
        $headers = $data[0];

        $dataSeriesLabels = [];
        $colIndex = 'B'; // Empieza desde la columna B

        for ($i = 1; $i < count($headers); $i++) {
            $dataSeriesLabels[] = new DataSeriesValues(
                DataSeriesValues::DATASERIES_TYPE_STRING,
                "Worksheet!\${$colIndex}\$1",
                null,
                1
            );

            $colIndex++; // Avanza a la siguiente columna (B, C, D, etc.)
        }

        // Custom colors for dataSeries (gray, blue, red, orange)
        /*$colors = [
            'BB3337',
            'FF6000',
            'ED3237',
            '00335F',
        ];*/
        $colors = $request->colors;

        // Set the Labels for each data series we want to plot
        //     Datatype
        //     Cell reference for data
        //     Format Code
        //     Number of datapoints in series
        //     Data values
        //     Data Marker
        /*$dataSeriesLabels = [
            new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, 'Worksheet!$B$1', null, 1), // 2010
            new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, 'Worksheet!$C$1', null, 1), // 2011
            new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, 'Worksheet!$D$1', null, 1), // 2012
        ];*/
        // Set the X-Axis Labels
        //     Datatype
        //     Cell reference for data
        //     Format Code
        //     Number of datapoints in series
        //     Data values
        //     Data Marker
        $xAxisTickValues = [
            new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, 'Worksheet!$A$2:$A$5', null, 4, [], null, $colors), // Q1 to Q4
        ];
        // Set the Data values for each data series we want to plot
        //     Datatype
        //     Cell reference for data
        //     Format Code
        //     Number of datapoints in series
        //     Data values
        //     Data Marker
        $rowCount = count($data);
        $colorCount = count($colors);

        $dataSeriesValues = [];
        $colIndex = 'B'; // Empieza en la columna B

        for ($i = 1; $i < count($headers); $i++) {
            $range = "Worksheet!\${$colIndex}\$2:\${$colIndex}\$$rowCount"; // Dinamizar el rango de filas
            $color = $colors[($i - 1) % $colorCount]; // Ciclar colores predefinidos

            $dataSeriesValues[] = new DataSeriesValues(
                DataSeriesValues::DATASERIES_TYPE_NUMBER,
                $range,
                null,
                $rowCount - 1,
                [],
                'diamond',
                $color,
                8
            );

            $colIndex++; // Avanzar a la siguiente columna (B, C, D, ...)
        }
        //$xAxisTickValues[0]->setFillColor($colors);

        // Build the dataseries
        $series = new DataSeries(
            DataSeries::TYPE_LINECHART, // plotType
            null, //DataSeries::GROUPING_STANDARD, // plotGrouping
            range(0, count($dataSeriesValues) - 1), // plotOrder
            $dataSeriesLabels, // plotLabel
            $xAxisTickValues, // plotCategory
            $dataSeriesValues        // plotValues
        );
        // Set additional dataseries parameters
        //     Make it a vertical column rather than a horizontal bar graph

        switch ($tipoGrafica) {
            case "tabBarV":
                $series = new DataSeries(
                    DataSeries::TYPE_BARCHART, // plotType
                    DataSeries::GROUPING_STANDARD, // plotGrouping
                    range(0, count($dataSeriesValues) - 1), // plotOrder
                    $dataSeriesLabels, // plotLabel
                    $xAxisTickValues, // plotCategory
                    $dataSeriesValues        // plotValues
                );
                $series->setPlotDirection(DataSeries::DIRECTION_COLUMN);
                break;
            case "tabBarH":
                $series = new DataSeries(
                    DataSeries::TYPE_BARCHART, // plotType
                    DataSeries::GROUPING_STANDARD, // plotGrouping
                    range(0, count($dataSeriesValues) - 1), // plotOrder
                    $dataSeriesLabels, // plotLabel
                    $xAxisTickValues, // plotCategory
                    $dataSeriesValues        // plotValues
                );
                $series->setPlotDirection(DataSeries::DIRECTION_BAR);
                break;
            case "tabLine":
                $series = new DataSeries(
                    DataSeries::TYPE_LINECHART, // plotType
                    null, //DataSeries::GROUPING_STANDARD, // plotGrouping
                    range(0, count($dataSeriesValues) - 1), // plotOrder
                    $dataSeriesLabels, // plotLabel
                    $xAxisTickValues, // plotCategory
                    $dataSeriesValues        // plotValues
                );
                break;
        }
        

        // Set the series in the plot area
        $plotArea = new PlotArea(null, [$series]);
        // Set the chart legend
        $legend = new ChartLegend(ChartLegend::POSITION_RIGHT, null, false);

        $title = new Title('Test Column Chart');
        //$yAxisLabel = new Title('Value ($k)');

        // Create the chart
        $chart = new Chart(
            'chart1', // name
            $title, // title
            $legend, // legend
            $plotArea, // plotArea
            true, // plotVisibleOnly
            DataSeries::EMPTY_AS_GAP, // displayBlanksAs
            null, // xAxisLabel
            null  // yAxisLabel
        );

        // Set the position where the chart should appear in the worksheet
        $chart->setTopLeftPosition('A7');
        $chart->setBottomRightPosition('O30');

        // Add the chart to the worksheet
        $worksheet->addChart($chart);

        // Crear un writer para guardar el archivo
        $writer = new Xlsx($spreadsheet);

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
        //$response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Type', 'application/pdf');
        $response->headers->set('Content-Disposition', 'attachment;filename="' . $filename . '"');
        $response->headers->set('Content-Transfer-Encoding', 'binary');
        $response->headers->set('Cache-Control', 'must-revalidate');
        $response->headers->set('Pragma', 'public');

        return $response;
    }
}
