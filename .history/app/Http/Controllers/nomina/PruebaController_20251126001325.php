<?php

namespace App\Http\Controllers\nomina;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
use PhpOffice\PhpSpreadsheet\Shared\Font;

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



















        /** =======================================================
         * 1. Cargar JSON fijos
         * ======================================================= */
        $jsonFijosPath = storage_path('app/public/plantillas/datos_fijos.json');
        $empleadosFijos = json_decode(file_get_contents($jsonFijosPath), true);



        /** =======================================================
         * 2. Cargar JSON dinámico del pivote SQL
         * ======================================================= */
        $jsonNominasPath = storage_path('app/public/plantillas/datos_nominas.json');
        $empleadosNominas = json_decode(file_get_contents($jsonNominasPath), true);


        // Formato excel
        $path = storage_path('app/public/plantillas/formato_prenomina.xlsx');
        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getSheetByName('prenomina');

        // Información json
        $jsonPathDetalleEmpleado = storage_path('app/public/plantillas/detalleEmpleado.json');
        $jsonPathNominaIngresosReales = storage_path('app/public/plantillas/nominaIngresosReales.json');
        $jsonPathPercepciones  = storage_path('app/public/plantillas/percepciones.json');
        $jsonPathExcedente  = storage_path('app/public/plantillas/excedente.json');
        $jsonPathProvisiones  = storage_path('app/public/plantillas/provisiones.json');
        $jsonPathCargaSocial = storage_path('app/public/plantillas/cargaSocial.json');

        // Obtener data
        $dataDetalleEmpleado = json_decode(file_get_contents($jsonPathDetalleEmpleado), true);
        $dataNominaIngresosReales = json_decode(file_get_contents($jsonPathNominaIngresosReales), true);
        $dataPercepciones = json_decode(file_get_contents($jsonPathPercepciones), true);
        $dataExcedente = json_decode(file_get_contents($jsonPathExcedente), true);
        $dataProvisiones = json_decode(file_get_contents($jsonPathProvisiones), true);
        $dataCargaSocial = json_decode(file_get_contents($jsonPathCargaSocial), true);

        // Obtener índices de las columnas
        $indicesDetalleEmpleado = array_keys($dataDetalleEmpleado[0]);
        $indicesNominaIngresosReales = array_keys($dataNominaIngresosReales[0]);
        $indicesPercepciones = array_keys($dataPercepciones[0]);
        $indicesExcedentes = array_keys($dataExcedente[0]);
        $indicesProvisiones = array_keys($dataProvisiones[0]);
        $indicesCargaSocial = array_keys($dataCargaSocial[0]);

        // Aquí quiero obtener las columna de Inicio y fin de las columnas correspondientes a cada sección para utilizarlos en agrupadores

        // Pongo fijo la columna inicio y fin de la sección  DetalleEmpleado por que está fija en el formato excel
        $xlColumnaInicioDetalleEmpleado = 1;
        $xlColumnaFinDetalleEmpleado = 17;

        // Aquí quiero tomar la última columna de sección anterior y le agrego una columna más de separación
        $xlColumnaInicioNominaIngresosReales = $xlColumnaFinDetalleEmpleado + 1;
        $xlColumnaFinNominaIngresosReales = count($indicesNominaIngresosReales) + $xlColumnaInicioNominaIngresosReales;

        $xlColumnaInicioPercepciones = $xlColumnaFinNominaIngresosReales + 1;
        $xlColumnaFinPercepciones = count($indicesPercepciones) + $xlColumnaInicioPercepciones;

        $xlColumnaInicioExcedente = $xlColumnaFinPercepciones + 1;
        $xlColumnaFinExcedente = count($indicesExcedentes) + $xlColumnaInicioExcedente;

        $xlColumnaInicioProvisiones = $xlColumnaFinExcedente + 1;
        $xlColumnaFinProvisiones = count($indicesProvisiones) + $xlColumnaInicioProvisiones;

        $xlColumnaInicioCargaSocial = $xlColumnaFinProvisiones + 1;
        $xlColumnaFinCargaSocial = count($indicesCargaSocial) + $xlColumnaInicioCargaSocial;

        // Cantidad de columnas totales que abarcaron todas las secciones
        $xlTotalColumnas = $xlColumnaFinCargaSocial;

        // Total solo de columnas dinámicas
        $xlTotalColumnasDinamicas = $xlTotalColumnas - $xlColumnaFinDetalleEmpleado;

        // Insertar columnas dinámicas por que las que están fijas que son 17 ya no es necesario agregarlas
        $sheet->insertNewColumnBefore(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($xlColumnaFinDetalleEmpleado), $xlTotalColumnasDinamicas);


        $xlFilaInicioDatos = 12;
        $xlFilaFinDatos = 12;
        $xlFilaEncabezados = 11;
        // Asigno última columna que existe, en este caso es la que está fija en el formato que es xlColumnaFinDetalleEmpleado por que las demás todavía no existen
        $xlColumnaActual = $xlColumnaFinDetalleEmpleado;

        // Poner encabezados de la sección NominaIngresosReales
        foreach ($indicesNominaIngresosReales as $key) {
            $xlColumna = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($xlColumnaActual);
            $sheet->setCellValue($xlColumna . $xlFilaEncabezados, $key);
            $xlColumnaActual++;
        }

        // Me quedo con el útimo recorrido anterior y sumo otra columna más de separación
        $xlColumnaActual++;

        // Poner encabezados de la sección Percepciones
        foreach ($indicesPercepciones as $key) {
            $xlColumna = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($xlColumnaActual);
            $sheet->setCellValue($xlColumna . $xlFilaEncabezados, $key);
            $xlColumnaActual++;
        }

        // Me quedo con el útimo recorrido anterior y sumo otra columna más de separación
        $xlColumnaActual++;

        // Poner encabezados de la sección Percepciones
        foreach ($indicesExcedentes as $key) {
            $xlColumna = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($xlColumnaActual);
            $sheet->setCellValue($xlColumna . $xlFilaEncabezados, $key);
            $xlColumnaActual++;
        }

        // Me quedo con el útimo recorrido anterior y sumo otra columna más de separación
        $xlColumnaActual++;

        // Poner encabezados de la sección Provisiones
        foreach ($indicesProvisiones as $key) {
            $xlColumna = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($xlColumnaActual);
            $sheet->setCellValue($xlColumna . $xlFilaEncabezados, $key);
            $xlColumnaActual++;
        }

        // Me quedo con el útimo recorrido anterior y sumo otra columna más de separación
        $xlColumnaActual++;

        // Poner encabezados de la sección CargaSocial
        foreach ($$indicesCargaSocial as $key) {
            $xlColumna = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($xlColumnaActual);
            $sheet->setCellValue($xlColumna . $xlFilaEncabezados, $key);
            $xlColumnaActual++;
        }

        // Construir matriz con todas las secciones
        $xlMatriz = [];
        foreach ($dataDetalleEmpleado as $objDetalleEmpleado) {

            $objNominaIngresosReales = collect($dataNominaIngresosReales)->firstWhere('codigoEmpleado', $objDetalleEmpleado['id']);
            $objPercepciones = collect($dataPercepciones)->firstWhere('codigoEmpleado', $objDetalleEmpleado['id']);
            $objExcedentes = collect($dataExcedente)->firstWhere('codigoEmpleado', $objDetalleEmpleado['id']);
            $objProvisiones = collect($dataProvisiones)->firstWhere('codigoEmpleado', $objDetalleEmpleado['id']);
            $objCargaSocial = collect($dataCargaSocial)->firstWhere('codigoEmpleado', $objDetalleEmpleado['id']);

            $fila = [];

            // DetalleEmpleado
            foreach ($indicesDetalleEmpleado as $k) {
                $fila[] = $emp[$k] ?? null;
            }

            // NominaIngresosReales
            foreach ($indicesNominaIngresosReales as $k) {
                $fila[] = $objNominaIngresosReales[$k] ?? null;
            }

            // Percepciones
            foreach ($indicesPercepciones as $k) {
                $fila[] = $objPercepciones[$k] ?? null;
            }

            // Excedentes
            foreach ($indicesExcedentes as $k) {
                $fila[] = $objExcedentes[$k] ?? null;
            }

            // Provisiones
            foreach ($indicesProvisiones as $k) {
                $fila[] = $objProvisiones[$k] ?? null;
            }

            // CargaSocial
            foreach ($indicesCargaSocial as $k) {
                $fila[] = $objCargaSocial[$k] ?? null;
            }

            $xlMatriz[] = $fila;
        }





        /** =======================================================
         * 3. Columnas fijas (A–H)
         * ======================================================= */
        /*$fixedKeys = [
            'id',
            'nombre',
            'puesto',
            'fechaIngresoCliente',
            'fechaIngresoGape',
            'nss',
            'rfc',
            'curp',
            'sueldoMensual',
            'sueldoDiario',
            'diasPeriodo',
            'diasRetroactivos',
            'incapacidad',
            'faltas',
            'faltasTotal',
            'diasPagados',
            'sueldo',
        ];*/

        $fixedKeys = array_keys($empleadosFijos[0]);



        /** =======================================================
         * 4. Detectar columnas dinámicas del pivote
         * ======================================================= */
        $allKeys = array_keys($empleadosNominas[0]);
        $dynamicKeys = array_diff($allKeys, ['empleado']); // si no existe id, ajústalo

        /** =======================================================
         * 5. Insertar columnas dinámicas
         * ======================================================= */
        $startDynamicColumn = 'R';
        $numDynamic = count($dynamicKeys);
        $sheet->insertNewColumnBefore($startDynamicColumn, $numDynamic);

        /** =======================================================
         * 6. Poner encabezados dinámicos
         * ======================================================= */
        $baseRow = 12;
        $startIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($startDynamicColumn);
        $colIndex = $startIndex;

        foreach ($dynamicKeys as $key) {
            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
            $sheet->setCellValue($col . 11, $key);
            $colIndex++;
        }

        /** =======================================================
         * 7. Construir matriz fijos + dinámicos
         * ======================================================= */
        $matriz = [];

        foreach ($empleadosFijos as $emp) {

            $dinamico = collect($empleadosNominas)->firstWhere('empleado', $emp['nombre']);

            $fila = [];

            // Fijos
            foreach ($fixedKeys as $k) {
                $fila[] = $emp[$k] ?? null;
            }

            // Dinámicos
            foreach ($dynamicKeys as $dyn) {
                $fila[] = $dinamico[$dyn] ?? null;
            }

            $matriz[] = $fila;
        }

        /** =======================================================
         * 8. Insertar filas nuevas sin sobrescribir
         * ======================================================= */
        $startRow = 12;
        $endRow = $startRow + count($matriz);

        $sheet->insertNewRowBefore($startRow + 1, count($matriz) - 1);

        /** =======================================================
         * 9. Copiar estilo columna × columna
         * ======================================================= */
        $lastColIndex = $startIndex + $numDynamic - 1;
        $lastColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($lastColIndex);

        $this->copiarEstiloPorColumna($sheet, $baseRow, $startRow, $endRow, $lastColLetter);

        /** =======================================================
         * 10. Insertar datos masivamente
         * ======================================================= */
        $sheet->fromArray($matriz, null, "A{$startRow}");

        /** =======================================================
         * 11. Aplicar formato dinámico (cantidad/monto)
         * ======================================================= */
        $this->aplicarFormatoDinamico($sheet, 11, $startRow, $endRow);








        /* // Convertir JSON en matriz:
        $matriz = [];

        foreach ($empleados as $row) {
            $matriz[] = [
                $row['id'],
                $row['nombre'],
                $row['puesto'],
                $row['fechaIngresoCliente'],
                $row['fechaIngresoGape'],
                $row['nss'],
                $row['rfc'],
                $row['curp'],
                $row['sueldoMensual'],
                $row['sueldoDiario'],
                $row['diasPeriodo'],
                $row['diasRetroactivos'],
                $row['incapacidad'],
                $row['faltas'],
                $row['faltasTotal'],
                $row['diasPagados'],
                $row['sueldo'],
            ];
        }

        $baseRow    = 12;                           // Donde está tu formato
        $startRow   = 12;                           // Donde inician los datos
        $endRow     = $startRow + count($matriz) + 1; // Última fila dinámica
        $lastColumn = 'H';

        $cantidadFilas = count($matriz);
        // Inserta n filas nuevas después de la fila base 12
        $sheet->insertNewRowBefore(13, count($matriz) - 1);

        $sheet->insertNewColumnBefore('R', 3);

        // Copiar estilos según la celda correspondiente de la fila 12
        $this->copiarEstiloPorColumna($sheet, $baseRow, $startRow, $endRow, $lastColumn);

        // 2. Insertar datos masivamente
        $sheet->fromArray($matriz, null, "A12");

        */

        //2. Usar setCellValueExplicit() en modo batch

        //Si necesitas respetar formatos especiales (RFC, CURP, NSS largo):
        /*$sheet->getCellCollection()->setArray(
            $matriz,
            'A10'
        );*/



        /*// 3. Fila base
        $filaBase = 12;   // la fila que contiene el formato
        $filaInicio = 12; // aquí empieza el primer empleado

        foreach ($empleados as $index => $emp) {

            $filaActual = $filaInicio + $index;

            // 4. Copiar formato de la fila base
            if ($filaActual !== $filaBase) {
                $this->copyRow($sheet, $filaBase, $filaActual);
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
        }*/



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

    public function exportPrenomina(Request $request)
    {

        $this->index();
        // Formato excel
        $path = storage_path('app/public/plantillas/formato_prenomina.xlsx');
        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getSheetByName('prenomina');

        // Información json
        $jsonPathDetalleEmpleado = storage_path('app/public/plantillas/data/01detalleEmpleado.json');
        $jsonPathNominaIngresosReales = storage_path('app/public/plantillas/data/02nominaIngresosReales.json');
        $jsonPathPercepciones  = storage_path('app/public/plantillas/data/03percepciones.json');
        $jsonPathExcedente  = storage_path('app/public/plantillas/data/04excedente.json');
        $jsonPathProvisiones  = storage_path('app/public/plantillas/data/05provisiones.json');
        $jsonPathCargaSocial = storage_path('app/public/plantillas/data/06cargaSocial.json');



        // Obtener data
        $dataDetalleEmpleado = json_decode(file_get_contents($jsonPathDetalleEmpleado), true);
        $dataNominaIngresosReales = json_decode(file_get_contents($jsonPathNominaIngresosReales), true);
        $dataPercepciones = json_decode(file_get_contents($jsonPathPercepciones), true);
        $dataExcedente = json_decode(file_get_contents($jsonPathExcedente), true);
        $dataProvisiones = json_decode(file_get_contents($jsonPathProvisiones), true);
        $dataCargaSocial = json_decode(file_get_contents($jsonPathCargaSocial), true);


        /*dd([
            'ruta' => $jsonPathPercepciones,
            'existe' => file_exists($jsonPathPercepciones),
            'decoded' => $dataPercepciones,
            'count' => is_array($dataPercepciones) ? count($dataPercepciones) : 'NO ES ARRAY',
        ]);*/



        // Obtener índices de las columnas
        $indicesDetalleEmpleado = array_keys($dataDetalleEmpleado[0]);
        $indicesNominaIngresosReales = array_keys($dataNominaIngresosReales[0]);
        $indicesPercepciones = array_keys($dataPercepciones[0]);
        $indicesExcedentes = array_keys($dataExcedente[0]);
        $indicesProvisiones = array_keys($dataProvisiones[0]);
        $indicesCargaSocial = array_keys($dataCargaSocial[0]);

        $omit = ['codigoEmpleado'];

        $indicesNominaIngresosReales = array_diff($indicesNominaIngresosReales, $omit);
        $indicesPercepciones = array_diff($indicesPercepciones, $omit);
        $indicesExcedentes = array_diff($indicesExcedentes, $omit);
        $indicesProvisiones = array_diff($indicesProvisiones, $omit);
        $indicesCargaSocial = array_diff($indicesCargaSocial, $omit);


        // Aquí quiero obtener las columna de Inicio y fin de las columnas correspondientes a cada sección para utilizarlos en agrupadores

        // Pongo fijo la columna inicio y fin de la sección  DetalleEmpleado por que está fija en el formato excel
        $xlColumnaInicioDetalleEmpleado = 1;
        $xlColumnaFinDetalleEmpleado = 17;

        // Aquí quiero tomar la última columna de sección anterior y le agrego una columna más de separación
        $xlColumnaInicioNominaIngresosReales = $xlColumnaFinDetalleEmpleado + 1;
        $xlColumnaFinNominaIngresosReales = count($indicesNominaIngresosReales) + $xlColumnaInicioNominaIngresosReales;

        $xlColumnaInicioPercepciones = $xlColumnaFinNominaIngresosReales + 1;
        $xlColumnaFinPercepciones = count($indicesPercepciones) + $xlColumnaInicioPercepciones;

        $xlColumnaInicioExcedente = $xlColumnaFinPercepciones + 1;
        $xlColumnaFinExcedente = count($indicesExcedentes) + $xlColumnaInicioExcedente;

        $xlColumnaInicioProvisiones = $xlColumnaFinExcedente + 1;
        $xlColumnaFinProvisiones = count($indicesProvisiones) + $xlColumnaInicioProvisiones;

        $xlColumnaInicioCargaSocial = $xlColumnaFinProvisiones + 1;
        $xlColumnaFinCargaSocial = count($indicesCargaSocial) + $xlColumnaInicioCargaSocial;


        // Cantidad de columnas totales que abarcaron todas las secciones
        $xlTotalColumnas = $xlColumnaFinCargaSocial;

        // Total solo de columnas dinámicas
        $xlTotalColumnasDinamicas = $xlTotalColumnas - $xlColumnaFinDetalleEmpleado;

        // Insertar columnas dinámicas por que las que están fijas que son 17 ya no es necesario agregarlas
        $sheet->insertNewColumnBefore(Coordinate::stringFromColumnIndex($xlColumnaInicioNominaIngresosReales), $xlTotalColumnasDinamicas);



        $xlFilaEncabezados = 11;
        // Asigno última columna que existe, en este caso es la que está fija en el formato que es xlColumnaFinDetalleEmpleado por que las demás todavía no existen
        $xlColumnaActual = $xlColumnaInicioNominaIngresosReales;

        $xlColumnasExcluidas = [];

        // Poner encabezados de la sección NominaIngresosReales
        foreach ($indicesNominaIngresosReales as $key) {
            $xlColumna = Coordinate::stringFromColumnIndex($xlColumnaActual);
            $sheet->setCellValue($xlColumna . $xlFilaEncabezados, $key);
            $xlColumnaActual++;
        }

        // Me quedo con el útimo recorrido anterior y sumo otra columna más de separación
        $xlColumnasExcluidas[] = $xlColumnaActual;
        $xlColumnaActual++;

        // Poner encabezados de la sección Percepciones
        foreach ($indicesPercepciones as $key) {
            $xlColumna = Coordinate::stringFromColumnIndex($xlColumnaActual);
            $sheet->setCellValue($xlColumna . $xlFilaEncabezados, $key);
            $xlColumnaActual++;
        }

        // Me quedo con el útimo recorrido anterior y sumo otra columna más de separación
        $xlColumnasExcluidas[] = $xlColumnaActual;
        $xlColumnaActual++;

        // Poner encabezados de la sección Percepciones
        foreach ($indicesExcedentes as $key) {
            $xlColumna = Coordinate::stringFromColumnIndex($xlColumnaActual);
            $sheet->setCellValue($xlColumna . $xlFilaEncabezados, $key);
            $xlColumnaActual++;
        }

        // Me quedo con el útimo recorrido anterior y sumo otra columna más de separación
        $xlColumnasExcluidas[] = $xlColumnaActual;
        $xlColumnaActual++;

        // Poner encabezados de la sección Provisiones
        foreach ($indicesProvisiones as $key) {
            $xlColumna = Coordinate::stringFromColumnIndex($xlColumnaActual);
            $sheet->setCellValue($xlColumna . $xlFilaEncabezados, $key);
            $xlColumnaActual++;
        }

        // Me quedo con el útimo recorrido anterior y sumo otra columna más de separación
        $xlColumnasExcluidas[] = $xlColumnaActual;
        $xlColumnaActual++;

        // Poner encabezados de la sección CargaSocial
        foreach ($indicesCargaSocial as $key) {
            $xlColumna = Coordinate::stringFromColumnIndex($xlColumnaActual);
            $sheet->setCellValue($xlColumna . $xlFilaEncabezados, $key);
            $xlColumnaActual++;
        }

        $xlColumnasExcluidas[] = $xlColumnaActual;



        // Construir matriz con todas las secciones
        $xlMatriz = [];
        foreach ($dataDetalleEmpleado as $objDetalleEmpleado) {

            $objNominaIngresosReales = collect($dataNominaIngresosReales)->firstWhere('codigoEmpleado', $objDetalleEmpleado['nombre']);
            $objPercepciones = collect($dataPercepciones)->firstWhere('codigoEmpleado', $objDetalleEmpleado['nombre']);
            $objExcedentes = collect($dataExcedente)->firstWhere('codigoEmpleado', $objDetalleEmpleado['nombre']);
            $objProvisiones = collect($dataProvisiones)->firstWhere('codigoEmpleado', $objDetalleEmpleado['nombre']);
            $objCargaSocial = collect($dataCargaSocial)->firstWhere('codigoEmpleado', $objDetalleEmpleado['nombre']);

            $fila = [];

            // DetalleEmpleado
            foreach ($indicesDetalleEmpleado as $k) {
                $fila[] = $objDetalleEmpleado[$k] ?? null;
            }


            // NominaIngresosReales
            foreach ($indicesNominaIngresosReales as $nir) {
                //$fila[] = $objNominaIngresosReales[$nir] ?? 0;
                $fila[] = 0;
            }

            $fila[] = null;

            // Percepciones
            foreach ($indicesPercepciones as $k) {
                $fila[] = $objPercepciones[$k] ?? null;
            }

            $fila[] = null;

            // Excedentes
            foreach ($indicesExcedentes as $k) {
                $fila[] = $objExcedentes[$k] ?? null;
            }

            $fila[] = null;

            // Provisiones
            foreach ($indicesProvisiones as $k) {
                $fila[] = $objProvisiones[$k] ?? null;
            }

            $fila[] = null;

            // CargaSocial
            foreach ($indicesCargaSocial as $k) {
                $fila[] = $objCargaSocial[$k] ?? null;
            }

            $fila[] = null;

            $xlMatriz[] = $fila;
        }

        // Limpiar la matriz de valores null, vacios
        //$xlMatriz = $this->limpiarMatriz($xlMatriz);

        // Insertar filas nuevas sin sobrescribir
        $xlFilaInicioDatos = 13;
        $xlFilaFinDatos = $xlFilaInicioDatos + count($xlMatriz) - 1;



        $sheet->insertNewRowBefore($xlFilaInicioDatos, $xlFilaFinDatos);

        // Insertar datos masivamente
        $sheet->fromArray($xlMatriz, null, "A12", true);





        // Aplicar formato dinámico (cantidad/monto)
        //$this->aplicarFormatoDinamico($sheet, 11, 12, $xlFilaFinDatos);
        //$this->aplicarFormatoDinamicoPorValor($sheet, 12, $xlFilaFinDatos);

        // Agrupar filas de detalle de filtros
        $this->rowRangeGroup($sheet, 1, 9);




        // Agrupar sección nominaIngresosReales, está empieza de la I 
        $this->columnRangeGroup($sheet, 'I', Coordinate::stringFromColumnIndex($xlColumnaFinNominaIngresosReales - 1));
        $sheet->setCellValue("I10", "NÓMINA EN BASE A INGRESOS REALES");
        $sheet->mergeCells(
            'I10:' . Coordinate::stringFromColumnIndex($xlColumnaFinNominaIngresosReales - 1) . '10'
        );

        // Agrupar sección percepciones
        $this->columnRangeGroup($sheet, Coordinate::stringFromColumnIndex($xlColumnaInicioPercepciones), Coordinate::stringFromColumnIndex($xlColumnaFinPercepciones - 1));
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($xlColumnaInicioPercepciones) . "10", "PERCEPCIONES");
        $sheet->mergeCells(
            Coordinate::stringFromColumnIndex($xlColumnaInicioPercepciones) . '10:' . Coordinate::stringFromColumnIndex($xlColumnaFinPercepciones - 1) . '10'
        );

        // Agrupar sección Excedente
        $this->columnRangeGroup($sheet, Coordinate::stringFromColumnIndex($xlColumnaInicioExcedente), Coordinate::stringFromColumnIndex($xlColumnaFinExcedente  - 1));
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($xlColumnaInicioExcedente) . "10", "EXCEDENTE");
        $sheet->mergeCells(
            Coordinate::stringFromColumnIndex($xlColumnaInicioExcedente) . '10:' . Coordinate::stringFromColumnIndex($xlColumnaFinExcedente - 1) . '10'
        );

        // Agrupar sección Provisiones
        $this->columnRangeGroup($sheet, Coordinate::stringFromColumnIndex($xlColumnaInicioProvisiones), Coordinate::stringFromColumnIndex($xlColumnaFinProvisiones  - 1));
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($xlColumnaInicioProvisiones) . "10", "PROVISIONES");
        $sheet->mergeCells(
            Coordinate::stringFromColumnIndex($xlColumnaInicioProvisiones) . '10:' . Coordinate::stringFromColumnIndex($xlColumnaFinProvisiones - 1) . '10'
        );

        // Agrupar sección CargaSocial
        $this->columnRangeGroup($sheet, Coordinate::stringFromColumnIndex($xlColumnaInicioCargaSocial), Coordinate::stringFromColumnIndex($xlColumnaFinCargaSocial  - 1));
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($xlColumnaInicioCargaSocial) . "10", "CARGA SOCIAL");
        $sheet->mergeCells(
            Coordinate::stringFromColumnIndex($xlColumnaInicioCargaSocial) . '10:' . Coordinate::stringFromColumnIndex($xlColumnaFinCargaSocial - 1) . '10'
        );





        // AutoSize columnas A:H
        foreach (range('A', 'H') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
            $sheet->calculateColumnWidths();
        }

        // Asignar todo como monto
        $this->asignarFormatoMonto($sheet, "I:" . Coordinate::stringFromColumnIndex($xlColumnaFinCargaSocial - 1));

        /*$this->asignarFormatoCantidad($sheet, 'K:K');
        $this->asignarFormatoCantidad($sheet, 'L:L');
        $this->asignarFormatoCantidad($sheet, 'M:M');
        $this->asignarFormatoCantidad($sheet, 'N:N');
        $this->asignarFormatoCantidad($sheet, 'P:P');
        $this->asignarFormatoCantidad($sheet, 'T:T');
        $this->asignarFormatoCantidad($sheet, 'R:R');*/

        $this->aplicarFormatoDinamico($sheet, 12, $xlFilaFinDatos);





        // Fila total
        $start = Coordinate::columnIndexFromString("I");
        $end   = $xlColumnaFinCargaSocial;

        foreach (range($start, $end) as $i) {

            $colLetter = Coordinate::stringFromColumnIndex($i);

            // ❗ Si esta columna está en la lista de excluidas, saltar
            if (in_array($i, $xlColumnasExcluidas)) continue;

            $totalRow = $xlFilaFinDatos; // fila donde irá el total (lo moví +1 porque antes estaba pisando datos)

            // SUMA
            $sheet->setCellValue(
                "{$colLetter}{$totalRow}",
                "=SUM({$colLetter}12:{$colLetter}{$xlFilaFinDatos})"
            );

            // Borde inferior doble
            $sheet->getStyle("{$colLetter}{$totalRow}")
                ->getBorders()->getBottom()
                ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_DOUBLE);

            // Borde superior doble
            $sheet->getStyle("{$colLetter}{$totalRow}")
                ->getBorders()->getTop()
                ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_DOUBLE);
        }





        // Es importante activar el resumen a la derecha
        //$sheet->setShowSummaryRight(true);

        //Font::setAutoSizeMethod(Font::AUTOSIZE_METHOD_EXACT);

        // Habilitar ajuste de texto para la fila 11
        //$sheet->getStyle('A11:CC11')->getAlignment()->setWrapText(true);

        // ... (asegúrate de que los datos ya estén en las celdas de la fila 11) ...

        // Indicar que la altura de la fila 11 debe ser automática
        $sheet->getRowDimension(11)->setRowHeight(-1);



        /*foreach ($sheet->getColumnIterator() as $column) {
            $columnIndex = $column->getColumnIndex();

            //$sheet->getColumnDimension($columnIndex)->setWidth(5);
            $sheet->getColumnDimension($columnIndex)->setAutoSize(true);
        }*/



        // ESTA ES LA LÍNEA OBLIGATORIA
        //$sheet->calculateColumnWidths();




        // Congeral la fila 11 y columna B
        $sheet->freezePane('C12');
        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');


        // Descargar el archivo
        $response = new StreamedResponse(function () use ($writer) {

            // Limpiar el buffer de salida
            if (ob_get_level()) {
                ob_end_clean();
            }
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

    public function asignarFormatoMonto($sheet, string $rango)
    {
        // Aplicar formato al rango completo (esto sí acepta A:A, A:D, etc.)
        $sheet->getStyle($rango)
            ->getNumberFormat()
            ->setFormatCode('_-$* #,##0_-;-$* #,##0_-;_-$* "-"_-;_-@_-');

        // Si el rango contiene ":" entonces hay varias columnas
        if (strpos($rango, ':') !== false) {

            [$colInicio, $colFin] = explode(':', $rango);

            // Convertir letras a índices (A=1, B=2...)
            $startIndex = Coordinate::columnIndexFromString($colInicio);
            $endIndex   = Coordinate::columnIndexFromString($colFin);

            // Recorrer todas las columnas del rango
            for ($i = $startIndex; $i <= $endIndex; $i++) {
                $col = Coordinate::stringFromColumnIndex($i);
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }
        } else {
            // Rango de una sola columna
            $sheet->getColumnDimension($rango)->setAutoSize(true);
        }
    }

    public function asignarFormatoCantidad($sheet, string $rango)
    {
        // Aplicar formato a TODO el rango (esto sí funciona con I:I)
        $sheet->getStyle($rango)
            ->getNumberFormat()
            ->setFormatCode('#,##0');

        // Si el rango contiene ":" entonces hay varias columnas
        if (strpos($rango, ':') !== false) {

            [$colInicio, $colFin] = explode(':', $rango);

            // Convertir letras a índices (A=1, B=2...)
            $startIndex = Coordinate::columnIndexFromString($colInicio);
            $endIndex   = Coordinate::columnIndexFromString($colFin);

            // Recorrer todas las columnas del rango
            for ($i = $startIndex; $i <= $endIndex; $i++) {
                $col = Coordinate::stringFromColumnIndex($i);

                //apagar autosize
                $sheet->getColumnDimension($col)->setAutoSize(false);
                $sheet->getColumnDimension($col)->setWidth(10);
                $sheet->getStyle($col)->getAlignment()->setHorizontal('center');
            }
        } else {
            // Rango de una sola columna
            //apagar autosize
            $sheet->getColumnDimension($rango)->setAutoSize(false);
            $sheet->getColumnDimension($rango)->setWidth(10);
        }
    }




    public function index()
    {
        try {
            $empresas = DB::table('nomina_gape_empresa as nge')
                ->leftJoin('nomina_gape_cliente as ngc', 'nge.id_nomina_gape_cliente', '=', 'ngc.id')
                ->leftJoin('empresa_database as ed', 'nge.id_empresa_database', '=', 'ed.id')
                ->select(
                    'nge.id as id',
                    'ngc.nombre as cliente',
                    DB::raw("ISNULL(ed.nombre_empresa, 'Sin empresa fiscal') as empresa"),
                    DB::raw("CASE WHEN nge.fiscal = 0 THEN 'No fiscal' ELSE 'Fiscal' END as tipo"),
                    'nge.razon_social',
                    'nge.rfc',
                    'nge.codigo_interno',
                    DB::raw("FORMAT(nge.created_at, 'dd-MM-yyyy') as fecha_creacion")
                )
                ->orderBy('ngc.nombre')
                ->orderBy('tipo')
                ->orderBy('fecha_creacion')
                ->get();


            $formatos_columnas = [];
            $num_columnas = $empresas->columnCount();

            for ($i = 0; $i < $num_columnas; $i++) {
                $meta = $empresas->getColumnMeta($i);

                $nombre_columna = $meta['name'];
                $tipo_nativo = $meta['native_type'];
                $formatos_columnas[$nombre_columna] = $tipo_nativo;
            }

            dd([
                'meta' => $formatos_columnas,
            ]);

            return response()->json([
                'code' => 200,
                'message' => 'Datos obtenidos correctamente',
                'data' => $empresas,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'message' => 'Error al obtener los datos de las empresas',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function aplicarFormatoDinamicoPorValor($sheet, $startRow, $endRow)
    {
        $highestCol = $sheet->getHighestColumn();
        $highestIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestCol);

        for ($c = 1; $c <= $highestIndex; $c++) {

            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c);
            $range = "{$col}{$startRow}:{$col}{$endRow}";

            $hasDecimal = false;
            $hasInteger = false;

            // --- SCANEAR LA COLUMNA COMPLETA ---
            for ($row = $startRow; $row <= $endRow; $row++) {
                $cellValue = $sheet->getCell("{$col}{$row}")->getValue();

                if ($cellValue === null || $cellValue === '') {
                    continue;
                }

                if (is_numeric($cellValue)) {

                    // Detectar decimal
                    if (floor($cellValue) != $cellValue) {
                        $hasDecimal = true;
                    } else {
                        $hasInteger = true;
                    }
                }
            }

            // --------------------------
            //  FORMATO DECIMAL (MONTO)
            // --------------------------
            if ($hasDecimal) {

                $sheet->getStyle($range)->getNumberFormat()
                    ->setFormatCode('_-$* #,##0_-;-$* #,##0_-;_-$* "-"_-;_-@_-');

                $sheet->getColumnDimension($col)->setAutoSize(true);
                continue;
            }

            // --------------------------
            //  FORMATO ENTERO (CANTIDAD)
            // --------------------------
            if ($hasInteger) {

                $sheet->getStyle($range)->getNumberFormat()
                    ->setFormatCode('#,##0');

                $sheet->getColumnDimension($col)->setWidth(10);

                $sheet->getStyle($range)->getAlignment()
                    ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                continue;
            }

            // --------------------------
            //  SI LA COLUMNA ESTÁ VACÍA → NO FORMATEA
            // --------------------------

        }
    }





    public function datosQuery1()
    {

        try {
            $sql = "
                DECLARE @idPeriodo INT;
                DECLARE @idTipoPeriodo INT;
                DECLARE @idDepartamentoInicial INT;
                DECLARE @idDepartamentoFinal INT;

                SET @idPeriodo = 335;
                SET @idTipoPeriodo = 3;
                SET @idDepartamentoInicial = 1;
                SET @idDepartamentoFinal = 50;

                ;WITH Movimientos AS (
                    SELECT idempleado, idperiodo, idconcepto, valor, importetotal
                    FROM Nom10007
                    WHERE importetotal > 0
                    UNION ALL
                    SELECT idempleado, idperiodo, idconcepto, valor, importetotal
                    FROM Nom10008
                    WHERE importetotal > 0
                )
                SELECT 
                    emp.codigoempleado,
                    emp.nombrelargo AS nombrelargo,
                    ISNULL(puesto.descripcion, '') AS puesto,
                    FORMAT(emp.fechaalta, 'dd-MM-yyyy') AS fechaAlta,
                    ISNULL(emp.campoextra1, '') AS fechaAltaGape,
                    emp.numerosegurosocial AS nss,
                    emp.rfc 
                        + SUBSTRING(CONVERT(char(10),emp.fechanacimiento , 126), 3,2) 
                        + SUBSTRING(CONVERT(char(10),emp.fechanacimiento , 126), 6,2) 
                        + SUBSTRING(CONVERT(char(10),emp.fechanacimiento, 126), 9,2) 
                        + homoclave AS rfc,
                    emp.curpi 
                        + SUBSTRING(CONVERT(char(10),emp.fechanacimiento , 126), 3,2) 
                        + SUBSTRING(CONVERT(char(10),emp.fechanacimiento , 126), 6,2) 
                        + SUBSTRING(CONVERT(char(10),emp.fechanacimiento, 126), 9,2) 
                        + emp.curpf AS curp,
                    ISNULL(emp.ccampoextranumerico1, 0) AS sueldoMensual,
                    ISNULL(emp.ccampoextranumerico2, 0) AS sueldoDiario,
                    periodo.diasdepago AS diasPeriodo,
                    SUM(CASE WHEN con.descripcion = 'Retroactivo' THEN pdo.valor ELSE 0 END) AS diasRetroactivos,
                    ISNULL(empPeriodo.cdiasincapacidades, 0) AS incapacidad,
                    ISNULL(empPeriodo.cdiasausencia, 0) AS faltas,
                    ISNULL(empPeriodo.cdiaspagados, 0) AS diasPagados,
                    ISNULL((emp.ccampoextranumerico2 * empPeriodo.cdiaspagados), 0) AS sueldo
                FROM nom10001 emp
                    INNER JOIN nom10034 AS empPeriodo 
                        ON emp.idempleado = empPeriodo.idempleado
                        AND empPeriodo.cidperiodo = @idPeriodo
                    INNER JOIN Movimientos pdo 
                        ON empPeriodo.cidperiodo = pdo.idperiodo 
                        AND emp.idempleado = pdo.idempleado
                    INNER JOIN nom10004 con 
                        ON pdo.idconcepto = con.idconcepto
                    INNER JOIN nom10002 AS periodo 
                        ON empPeriodo.cidperiodo = periodo.idperiodo
                    LEFT JOIN nom10006 AS puesto 
                        ON emp.idpuesto = puesto.idpuesto
                WHERE emp.idtipoperiodo = @idTipoPeriodo
                    AND con.tipoconcepto IN ('P','D')
                    AND con.descripcion != 'Sueldo'
                    AND empPeriodo.iddepartamento BETWEEN @idDepartamentoInicial AND @idDepartamentoFinal
                GROUP BY 
                    codigoempleado,
                    nombrelargo,
                    puesto.descripcion,
                    emp.fechaalta,
                    emp.campoextra1,
                    emp.numerosegurosocial,
                    rfc,
                    emp.fechanacimiento,
                    homoclave,
                    emp.ccampoextranumerico1,
                    emp.ccampoextranumerico2,
                    periodo.diasdepago,
                    empPeriodo.cdiasincapacidades,
                    empPeriodo.cdiasausencia,
                    empPeriodo.cdiaspagados,
                    emp.curpi,
                    emp.curpf
                ORDER BY emp.codigoempleado;
                ";

            $result = DB::select(DB::raw($sql));
        } catch (\Exception $e) {
        }
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




    public function aplicarFormatoDinamico($sheet, $headerRow, $startRow, $endRow)
    {
        $highestCol = $sheet->getHighestColumn();
        $highestIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestCol);

        for ($c = 1; $c <= $highestIndex; $c++) {

            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c);

            // Leer encabezado original
            $header = (string) $sheet->getCell("{$col}{$headerRow}")->getValue();

            // Normalizar: minúsculas y acentos fuera
            $h = strtolower(trim($header));
            $h = str_replace(
                ['á', 'é', 'í', 'ó', 'ú'],
                ['a', 'e', 'i', 'o', 'u'],
                $h
            );

            // Rango de la columna
            $range = "{$col}{$startRow}:{$col}{$endRow}";

            // --- DETECTAR CANTIDAD ---
            if (
                str_contains($h, 'cantidad') ||
                str_contains($h, 'dias') ||
                str_contains($h, 'faltas')
            ) {
                $sheet->getStyle($range)->getNumberFormat()
                    ->setFormatCode('#,##0');

                $sheet->getColumnDimension($col)->setWidth(10);

                $sheet->getStyle($col)->getAlignment()->setHorizontal('center');
                continue;
            }
        }
    }











    function copyRow($sheet, int $sourceRow, int $targetRow)
    {
        $highestColumn = $sheet->getHighestColumn();
        $highestColumnIndex = Coordinate::columnIndexFromString($highestColumn);

        // 1. Copiar estilos celda por celda
        for ($col = 1; $col <= $highestColumnIndex; $col++) {
            $columnLetter = Coordinate::stringFromColumnIndex($col);
            $sourceCellStyle = $sheet->getStyle($columnLetter . $sourceRow);

            $sheet->duplicateStyle(
                $sourceCellStyle,
                $columnLetter . $targetRow
            );
        }

        // 2. Copiar altura de fila
        $height = $sheet->getRowDimension($sourceRow)->getRowHeight();
        if ($height > -1) {
            $sheet->getRowDimension($targetRow)->setRowHeight($height);
        }
    }

    function copiarEstiloPorColumna($sheet, int $baseRow, int $startRow, int $endRow, string $lastColumn = 'H')
    {
        $highestColumnIndex = Coordinate::columnIndexFromString($lastColumn);

        for ($col = 1; $col <= $highestColumnIndex; $col++) {

            // Convertir índice a letra (1 = A, 2 = B...)
            $colLetter = Coordinate::stringFromColumnIndex($col);

            // Obtener estilo base (A12, B12, C12, etc.)
            $sourceStyle = $sheet->getStyle($colLetter . $baseRow);

            // Crear rango destino (ej. A12:A200)
            $targetRange = $colLetter . $startRow . ':' . $colLetter . $endRow;

            // Copiar estilo completo de esa celda hacia abajo
            $sheet->duplicateStyle($sourceStyle, $targetRange);
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
