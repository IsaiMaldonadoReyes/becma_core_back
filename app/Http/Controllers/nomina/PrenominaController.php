<?php

namespace App\Http\Controllers\nomina;

use App\Http\Controllers\Controller;
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
use PhpOffice\PhpSpreadsheet\Collection\CellsFactory;
use PhpOffice\PhpSpreadsheet\Cell\CellAddress;
use PhpOffice\PhpSpreadsheet\Collection\Memory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

use Illuminate\Support\Facades\DB;

use App\Http\Controllers\core\HelperController;

// refactor
use App\Http\Services\Core\HelperService;

use App\Http\Services\Nomina\Export\Prenomina\ConfigFormatoPrenominaService;
use App\Http\Services\Nomina\Export\Prenomina\PrenominaQueryService;

class PrenominaController extends Controller
{

    protected $helperController;

    public function __construct(helperController $helperController)
    {
        $this->helperController = $helperController;
    }

    public function datosQueryNoFiscal1($request = null)
    {
        try {
            $idNominaGapeEmpresa = $request->id_nomina_gape_empresa;
            $idEmpleadoInicial = $request->empleado_inicial;
            $idEmpleadoFinal = $request->empleado_final;

            $sql = "
                DECLARE @idEmpleadoInicial INT;
                DECLARE @idEmpleadoFinal INT;
                DECLARE @idNominaGapeEmpresa INT;

                SET @idEmpleadoInicial = $idEmpleadoInicial;
                SET @idEmpleadoFinal = $idEmpleadoFinal;
                SET @idNominaGapeEmpresa = $idNominaGapeEmpresa;

                SELECT
                    codigoempleado,
                    (apellidopaterno + ' ' + apellidomaterno + ' ' + nombre)  AS nombre,
                    FORMAT(fechaalta, 'dd-MM-yyyy') AS fechaAlta,
                    FORMAT(TRY_CONVERT(date, campoextra1), 'dd-MM-yyyy') AS fechaAltaGape,
                    ClabeInterbancaria AS nss,
                    cuentacw AS rfc,
                    ccampoextranumerico1 AS sueldoMensual,
                    ccampoextranumerico2 AS sueldoDiario

                FROM nomina_gape_empleado
                WHERE id_nomina_gape_empresa = @idNominaGapeEmpresa
                AND id BETWEEN @idEmpleadoInicial AND @idEmpleadoFinal
        ";

            $result = DB::connection()->select($sql);

            return $result;
        } catch (\Exception $e) {
            return [];
        }
    }

    public function datosQueryNoFiscal2($request = null)
    {
        try {
            $idNominaGapeEmpresa = $request->id_nomina_gape_empresa;
            $idEmpleadoInicial = $request->empleado_inicial;
            $idEmpleadoFinal = $request->empleado_final;

            $sql = "
                DECLARE @idNominaGapeEmpresa INT;
                DECLARE @cols NVARCHAR(MAX),
                        @query  NVARCHAR(MAX);

                SET @idNominaGapeEmpresa = $idNominaGapeEmpresa;
                ;WITH ParamConfig AS (
                    SELECT
                        sueldo_imss, sueldo_imss_tope, sueldo_imss_orden
                        , prev_social, prev_social_tope, prev_social_orden
                        , fondos_sind, fondos_sind_tope, fondos_sind_orden
                        , tarjeta_facil, tarjeta_facil_tope, tarjeta_facil_orden
                        , hon_asimilados, hon_asimilados_tope, hon_asimilados_orden
                        , gastos_compro, gastos_compro_tope, gastos_compro_orden
                    FROM nomina_gape_concepto_pago_parametrizacion
                    WHERE id_nomina_gape_empresa = @idNominaGapeEmpresa
                ),
                ConceptosParametrizados AS (
                    SELECT 'sueldo_imss' AS concepto, sueldo_imss AS activo, sueldo_imss_tope AS tope, sueldo_imss_orden AS orden FROM ParamConfig
                    UNION ALL SELECT 'prev_social'   , prev_social   , prev_social_tope   , prev_social_orden   FROM ParamConfig
                    UNION ALL SELECT 'fondos_sind'   , fondos_sind   , fondos_sind_tope   , fondos_sind_orden   FROM ParamConfig
                    UNION ALL SELECT 'tarjeta_facil' , tarjeta_facil , tarjeta_facil_tope , tarjeta_facil_orden FROM ParamConfig
                    UNION ALL SELECT 'hon_asimilados', hon_asimilados, hon_asimilados_tope, hon_asimilados_orden FROM ParamConfig
                    UNION ALL SELECT 'gastos_compro' , gastos_compro , gastos_compro_tope , gastos_compro_orden FROM ParamConfig
                ),
                ConceptosActivos AS (
                    SELECT concepto, CAST(tope AS DECIMAL(18,2)) AS tope, orden
                    FROM ConceptosParametrizados
                    WHERE activo = 1
                )
                SELECT
                    @cols = STUFF((
                        SELECT ', ' + QUOTENAME(concepto)
                        FROM ConceptosActivos
                        ORDER BY orden
                        FOR XML PATH(''), TYPE
                    ).value('.', 'NVARCHAR(MAX)'), 1, 2, '');

                SET @query = N'
                DECLARE @idNominaGapeEmpresa INT;
                DECLARE @idEmpleadoInicial INT;
                DECLARE @idEmpleadoFinal INT;

                SET @idEmpleadoInicial = $idEmpleadoInicial;
                SET @idEmpleadoFinal = $idEmpleadoFinal;
                SET @idNominaGapeEmpresa = $idNominaGapeEmpresa;

                ;WITH BaseEmpleados AS (
                    SELECT
                        codigoempleado,
                        CAST(ccampoextranumerico1 AS DECIMAL(18,2)) AS sueldoMensual
                    FROM nomina_gape_empleado
                    WHERE id_nomina_gape_empresa = @idNominaGapeEmpresa
                    AND id BETWEEN @idEmpleadoInicial AND @idEmpleadoFinal
                ),
                ParamConfig AS (
                    SELECT
                        sueldo_imss, sueldo_imss_tope, sueldo_imss_orden
                        , prev_social, prev_social_tope, prev_social_orden
                        , fondos_sind, fondos_sind_tope, fondos_sind_orden
                        , tarjeta_facil, tarjeta_facil_tope, tarjeta_facil_orden
                        , hon_asimilados, hon_asimilados_tope, hon_asimilados_orden
                        , gastos_compro, gastos_compro_tope, gastos_compro_orden
                    FROM nomina_gape_concepto_pago_parametrizacion
                    WHERE id_nomina_gape_empresa = @idNominaGapeEmpresa
                ),
                ConceptosParametrizados AS (
                    SELECT ''sueldo_imss'' AS concepto, sueldo_imss AS activo, sueldo_imss_tope AS tope, sueldo_imss_orden AS orden FROM ParamConfig
                    UNION ALL SELECT ''prev_social''   , prev_social   , prev_social_tope   , prev_social_orden   FROM ParamConfig
                    UNION ALL SELECT ''fondos_sind''   , fondos_sind   , fondos_sind_tope   , fondos_sind_orden   FROM ParamConfig
                    UNION ALL SELECT ''tarjeta_facil'' , tarjeta_facil , tarjeta_facil_tope , tarjeta_facil_orden FROM ParamConfig
                    UNION ALL SELECT ''hon_asimilados'', hon_asimilados, hon_asimilados_tope, hon_asimilados_orden FROM ParamConfig
                    UNION ALL SELECT ''gastos_compro'' , gastos_compro , gastos_compro_tope , gastos_compro_orden FROM ParamConfig
                ),
                ConceptosActivos AS (
                    SELECT concepto, CAST(tope AS DECIMAL(18,2)) AS tope, orden
                    FROM ConceptosParametrizados
                    WHERE activo = 1
                ),
                BaseProrrateo AS (
                    SELECT
                        e.codigoempleado,
                        e.sueldoMensual,
                        c.concepto,
                        c.tope,
                        c.orden,
                        ROW_NUMBER() OVER (PARTITION BY e.codigoempleado ORDER BY c.orden) AS rn
                    FROM BaseEmpleados e
                    CROSS JOIN ConceptosActivos c
                ),
                ProrrateoRecursivo AS (
                    SELECT
                        b.codigoempleado,
                        b.concepto,
                        b.orden,
                        b.rn,
                        b.tope,
                        CAST(b.sueldoMensual AS DECIMAL(18,2)) AS saldo_antes,
                        CAST(
                            CASE
                                WHEN b.sueldoMensual <= 0 THEN 0
                                WHEN b.tope IS NULL OR b.sueldoMensual >= b.tope THEN ISNULL(b.tope, b.sueldoMensual)
                                ELSE b.sueldoMensual
                            END
                        AS DECIMAL(18,2)) AS monto_asignado,
                        CAST(
                            b.sueldoMensual -
                            CASE
                                WHEN b.sueldoMensual <= 0 THEN 0
                                WHEN b.tope IS NULL OR b.sueldoMensual >= b.tope THEN ISNULL(b.tope, b.sueldoMensual)
                                ELSE b.sueldoMensual
                            END
                        AS DECIMAL(18,2)) AS saldo_despues
                    FROM BaseProrrateo b
                    WHERE b.rn = 1

                    UNION ALL

                    SELECT
                        b.codigoempleado,
                        b.concepto,
                        b.orden,
                        b.rn,
                        b.tope,
                        CAST(r.saldo_despues AS DECIMAL(18,2)) AS saldo_antes,
                        CAST(
                            CASE
                                WHEN r.saldo_despues <= 0 THEN 0
                                WHEN b.tope IS NULL OR r.saldo_despues >= b.tope THEN ISNULL(b.tope, r.saldo_despues)
                                ELSE r.saldo_despues
                            END
                        AS DECIMAL(18,2)) AS monto_asignado,
                        CAST(
                            r.saldo_despues -
                            CASE
                                WHEN r.saldo_despues <= 0 THEN 0
                                WHEN b.tope IS NULL OR r.saldo_despues >= b.tope THEN ISNULL(b.tope, r.saldo_despues)
                                ELSE r.saldo_despues
                            END
                        AS DECIMAL(18,2)) AS saldo_despues
                    FROM ProrrateoRecursivo r
                    JOIN BaseProrrateo b
                    ON r.codigoempleado = b.codigoempleado
                    AND r.rn + 1 = b.rn
                ),

                ProrrateoFinal AS (
                    SELECT codigoempleado, concepto, monto_asignado
                    FROM ProrrateoRecursivo
                )

                SELECT codigoempleado, ' + @cols + '
                FROM ProrrateoFinal
                PIVOT (
                    SUM(monto_asignado)
                    FOR concepto IN (' + @cols + ')
                ) p
                ORDER BY codigoempleado;
                ';
                EXEC(@query);
        ";

            $result = DB::connection('sqlsrv')->select($sql);
            return $result;
        } catch (\Exception $e) {
            return [];
        }
    }

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

    public function prenominaFiscal(
        Request $request,
        HelperService $helper,
        PrenominaQueryService $queryService
    ) {
        // ⚠ OPTIMIZACIÓN GLOBAL PARA EXCEL
        ini_set('max_execution_time', 0);
        ini_set('memory_limit', '2048M');


        $validated = $request->validate([
            'fiscal' => 'required|boolean',
            'id_nomina_gape_empresa' => 'required',

            // Si fiscal = true
            'id_tipo_periodo' => 'required_if:fiscal,true',
            'periodo_inicial' => 'required_if:fiscal,true',

            // Siempre obligatorios
            'empleado_inicial' => 'required',
            'empleado_final' => 'required',
        ]);

        $idNominaGapeEmpresa = $validated['id_nomina_gape_empresa'];
        $fiscal = $validated['fiscal'];

        $conexion = $helper->getConexionDatabaseNGE($idNominaGapeEmpresa, 'Nom');
        $helper->setDatabaseConnection($conexion, $conexion->nombre_base);

        // 1. CONFIG
        $config = ConfigFormatoPrenominaService::getConfig($fiscal);

        // Formato excel
        $spreadsheet = $this->loadTemplate($config['path']);

        $sheet = $this->getWorksheet($spreadsheet, $config['sheet_name']);

        return $queryService->getData('datosQuery2', $request);

        // Obtener data
        $dataDetalleEmpleado = collect($queryService->getData('datosQuery1', $request))
            ->map(fn($r) => (array)$r)
            ->toArray();

        $dataNominaIngresosReales = collect($queryService->getData('datosQuery2', $request))
            ->map(fn($r) => (array)$r)
            ->toArray();

        $dataPercepciones = collect($queryService->getData('datosQuery3', $request))
            ->map(fn($r) => (array)$r)
            ->toArray();

        $dataExcedente = collect($queryService->getData('datosQuery4', $request))
            ->map(fn($r) => (array)$r)
            ->toArray();

        $dataProvisiones = collect($queryService->getData('datosQuery5', $request))
            ->map(fn($r) => (array)$r)
            ->toArray();

        $dataCargaSocial = collect($queryService->getData('datosQuery6', $request))
            ->map(fn($r) => (array)$r)
            ->toArray();

        //$datosTotales = $queryService->getData('datosTotales7', $request);
        $datosTotales = null;

        // Obtener índices de las columnas
        $indicesDetalleEmpleado = array_keys($dataDetalleEmpleado[0]);
        $indicesNominaIngresosReales = array_keys($dataNominaIngresosReales[0]);
        $indicesPercepciones = array_keys($dataPercepciones[0]);
        $indicesExcedentes = array_keys($dataExcedente[0]);
        $indicesProvisiones = array_keys($dataProvisiones[0]);
        $indicesCargaSocial = array_keys($dataCargaSocial[0]);

        $omitEmp = ['codigoempleado'];

        $indicesNominaIngresosReales = array_diff($indicesNominaIngresosReales, $omitEmp);
        $indicesPercepciones = array_diff($indicesPercepciones, $omitEmp);
        $indicesExcedentes = array_diff($indicesExcedentes, $omitEmp);
        $indicesProvisiones = array_diff($indicesProvisiones, $omitEmp);
        $indicesCargaSocial = array_diff($indicesCargaSocial, $omitEmp);


        // Aquí quiero obtener las columna de Inicio y fin de las columnas correspondientes a cada sección para utilizarlos en agrupadores
        // Pongo fijo la columna inicio y fin de la sección  DetalleEmpleado por que está fija en el formato excel
        $xlColumnaInicioDetalleEmpleado = 1;
        $xlColumnaFinDetalleEmpleado = 16;

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
        //$sheet->insertNewColumnBefore(Coordinate::stringFromColumnIndex($xlColumnaInicioNominaIngresosReales), $xlTotalColumnasDinamicas);
        $sheet->insertNewColumnBefore($config['columna_inicio'], $xlTotalColumnasDinamicas);



        $xlFilaEncabezados = $config['fila_encabezado'];
        // Asigno última columna que existe, en este caso es la que está fija en el formato que es xlColumnaFinDetalleEmpleado por que las demás todavía no existen
        $xlColumnaActual = $xlColumnaInicioNominaIngresosReales;

        $xlColumnasExcluidas = [];

        // Poner encabezados de la sección NominaIngresosReales
        foreach ($indicesNominaIngresosReales as $key) {
            $xlColumna = Coordinate::stringFromColumnIndex($xlColumnaActual);
            $sheet->setCellValue($xlColumna . $xlFilaEncabezados, $key);
            $xlColumnaActual++;
        }

        // Guardar la columna de separación
        $xlColumnasExcluidas[] = $xlColumnaActual;
        // Me quedo con el útimo recorrido anterior y sumo otra columna más de separación
        $xlColumnaActual++;

        // Poner encabezados de la sección Percepciones
        foreach ($indicesPercepciones as $key) {
            $xlColumna = Coordinate::stringFromColumnIndex($xlColumnaActual);
            $sheet->setCellValue($xlColumna . $xlFilaEncabezados, $key);
            $xlColumnaActual++;
        }

        // Guardar la columna de separación
        $xlColumnasExcluidas[] = $xlColumnaActual;
        // Me quedo con el útimo recorrido anterior y sumo otra columna más de separación
        $xlColumnaActual++;

        // Poner encabezados de la sección Percepciones
        foreach ($indicesExcedentes as $key) {
            $xlColumna = Coordinate::stringFromColumnIndex($xlColumnaActual);
            $sheet->setCellValue($xlColumna . $xlFilaEncabezados, $key);
            $xlColumnaActual++;
        }

        // Guardar la columna de separación
        $xlColumnasExcluidas[] = $xlColumnaActual;
        // Me quedo con el útimo recorrido anterior y sumo otra columna más de separación
        $xlColumnaActual++;

        // Poner encabezados de la sección Provisiones
        foreach ($indicesProvisiones as $key) {
            $xlColumna = Coordinate::stringFromColumnIndex($xlColumnaActual);
            $sheet->setCellValue($xlColumna . $xlFilaEncabezados, $key);
            $xlColumnaActual++;
        }

        // Guardar la columna de separación
        $xlColumnasExcluidas[] = $xlColumnaActual;
        // Me quedo con el útimo recorrido anterior y sumo otra columna más de separación
        $xlColumnaActual++;

        // Poner encabezados de la sección CargaSocial
        foreach ($indicesCargaSocial as $key) {
            $xlColumna = Coordinate::stringFromColumnIndex($xlColumnaActual);
            $sheet->setCellValue($xlColumna . $xlFilaEncabezados, $key);
            $xlColumnaActual++;
        }

        // Guardar la columna de separación
        $xlColumnasExcluidas[] = $xlColumnaActual;

        // Construir matriz con todas las secciones
        $xlMatriz = [];
        foreach ($dataDetalleEmpleado as $objDetalleEmpleado) {

            $objNominaIngresosReales = collect($dataNominaIngresosReales)->firstWhere('codigoempleado', $objDetalleEmpleado['codigoempleado']);
            $objPercepciones = collect($dataPercepciones)->firstWhere('codigoempleado', $objDetalleEmpleado['codigoempleado']);
            $objExcedentes = collect($dataExcedente)->firstWhere('codigoempleado', $objDetalleEmpleado['codigoempleado']);
            $objProvisiones = collect($dataProvisiones)->firstWhere('codigoempleado', $objDetalleEmpleado['codigoempleado']);
            $objCargaSocial = collect($dataCargaSocial)->firstWhere('codigoempleado', $objDetalleEmpleado['codigoempleado']);

            $fila = [];

            // DetalleEmpleado
            foreach ($indicesDetalleEmpleado as $k) {
                $fila[] = $objDetalleEmpleado[$k] ?? null;
            }

            // NominaIngresosReales
            foreach ($indicesNominaIngresosReales as $nir) {
                $fila[] = $objNominaIngresosReales[$nir] ?? null;
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


        // Insertar filas nuevas sin sobrescribir
        $xlFilaInicioDatos = $config['fila_inicio_datos'];
        $xlFilaFinDatos = $xlFilaInicioDatos + count($xlMatriz) - 1;

        $sheet->insertNewRowBefore($config['fila_inicio_datos'], count($xlMatriz));

        // Insertar datos masivamente
        $sheet->fromArray($xlMatriz, null, $config['col_inicio']);

        if (!empty($datosTotales)) {
            $percepcionBruta = $datosTotales->percepcion_bruta;
            $costoSocial = $datosTotales->costo_social;
            $baseComision = $datosTotales->base_comision;
            $feePorcentaje = $datosTotales->fee_porcentaje;
            $fee = $datosTotales->fee;
            $subtotal = $datosTotales->subtotal;
            $iva = $datosTotales->iva;
            $total = $datosTotales->total;


            // fee porcentaje
            $colLetra = Coordinate::stringFromColumnIndex($xlTotalColumnas + 3);
            $fila = $xlFilaFinDatos + 7;
            $cell = $colLetra . $fila;
            $sheet->setCellValue($cell, $feePorcentaje);

            // totales
            $colLetraTotales = Coordinate::stringFromColumnIndex($xlTotalColumnas + 4);

            // percepcion bruta
            $fila = $xlFilaFinDatos + 4;
            $cell = $colLetraTotales . $fila;
            $sheet->setCellValue($cell, $percepcionBruta);

            // costo social
            $fila = $xlFilaFinDatos + 5;
            $cell = $colLetraTotales . $fila;
            $sheet->setCellValue($cell, $costoSocial);

            // base para comision
            $fila = $xlFilaFinDatos + 6;
            $cell = $colLetraTotales . $fila;
            $sheet->setCellValue($cell, $baseComision);

            // fee
            $fila = $xlFilaFinDatos + 7;
            $cell = $colLetraTotales . $fila;
            $sheet->setCellValue($cell, $fee);

            // subtotal
            $fila = $xlFilaFinDatos + 10;
            $cell = $colLetraTotales . $fila;
            $sheet->setCellValue($cell, $subtotal);

            // iva
            $fila = $xlFilaFinDatos + 11;
            $cell = $colLetraTotales . $fila;
            $sheet->setCellValue($cell, $iva);

            // total
            $fila = $xlFilaFinDatos + 12;
            $cell = $colLetraTotales . $fila;
            $sheet->setCellValue($cell, $total);
        }

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

        // Es importante activar el resumen a la derecha
        $sheet->setShowSummaryRight(true);

        // Fila de totales
        $start = Coordinate::columnIndexFromString("I");
        $end   = $xlColumnaFinCargaSocial;

        foreach (range($start, $end) as $i) {

            $colLetter = Coordinate::stringFromColumnIndex($i);

            // ❗ Si esta columna está en la lista de excluidas, saltar
            if (in_array($i, $xlColumnasExcluidas)) continue;

            $totalRow = $xlFilaFinDatos; // fila donde irá el total (lo moví +1 porque antes estaba pisando datos)
            $findRow = $xlFilaFinDatos - 1; // fila donde irá el total (lo moví +1 porque antes estaba pisando datos)

            // SUMA
            $sheet->setCellValue(
                "{$colLetter}{$totalRow}",
                "=SUM({$colLetter}12:{$colLetter}{$findRow})"
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

        // Ajustar AutoSize columnas A:H
        foreach (range('A', 'H') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
            $sheet->calculateColumnWidths();
        }

        $sheet->getRowDimension(11)->setRowHeight(-1);

        // Asignar formato a todo como monto
        $this->asignarFormatoMonto($sheet, "I:" . Coordinate::stringFromColumnIndex($xlColumnaFinCargaSocial - 1));

        // Asignar formato de cantidad
        $this->aplicarFormatoDinamico($sheet, 11, 12, $xlFilaFinDatos);

        // Estillos de las columnas de totales y netos
        $this->colorearColumnasPorEncabezado($sheet, 11);


        // Congeral la fila 11 y columna B++
        $sheet->freezePane($config['freeze_cell']);
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

    private function rangeInside($mergeRange, $parentRange)
    {
        [$mStart, $mEnd] = explode(':', $mergeRange);
        [$pStart, $pEnd] = explode(':', $parentRange);

        return (
            $mStart >= $pStart && $mEnd <= $pEnd
        );
    }

    public function prenominaNoFiscal(Request $request)
    {
        $validated = $request->validate([

            'fiscal' => 'required|boolean',
            'id_nomina_gape_empresa' => 'required',
            // Siempre obligatorios
            'empleado_inicial' => 'required',
            'empleado_final' => 'required',
        ]);

        // Formato excel
        $path = storage_path('app/public/plantillas/formato_prenomina_no_fiscal.xlsx');
        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getSheetByName('prenomina');

        // Información json
        //$jsonPathDetalleEmpleado = storage_path('app/public/plantillas/data/01detalleEmpleado.json');
        //$jsonPathNominaIngresosReales = storage_path('app/public/plantillas/data/02nominaIngresosReales.json');
        //$jsonPathPercepciones  = storage_path('app/public/plantillas/data/03percepciones.json');
        //$jsonPathExcedente  = storage_path('app/public/plantillas/data/04excedente.json');
        //$jsonPathProvisiones  = storage_path('app/public/plantillas/data/05provisiones.json');
        //$jsonPathCargaSocial = storage_path('app/public/plantillas/data/06cargaSocial.json');

        $dataDetalleEmpleado = collect($this->datosQueryNoFiscal1($request))
            ->map(fn($r) => (array)$r)
            ->toArray();

        $dataNominaIngresosReales = collect($this->datosQueryNoFiscal2($request))
            ->map(fn($r) => (array)$r)
            ->toArray();

        // Obtener data
        //$dataDetalleEmpleado = json_decode(file_get_contents($jsonPathDetalleEmpleado), true);
        //$dataNominaIngresosReales = json_decode(file_get_contents($jsonPathNominaIngresosReales), true);
        //$dataPercepciones = json_decode(file_get_contents($jsonPathPercepciones), true);
        //$dataExcedente = json_decode(file_get_contents($jsonPathExcedente), true);
        //$dataProvisiones = json_decode(file_get_contents($jsonPathProvisiones), true);
        //$dataCargaSocial = json_decode(file_get_contents($jsonPathCargaSocial), true);




        // Obtener índices de las columnas
        $indicesDetalleEmpleado = array_keys($dataDetalleEmpleado[0]);
        $indicesNominaIngresosReales = array_keys($dataNominaIngresosReales[0]);
        //$indicesProvisiones = array_keys($dataProvisiones[0]);
        //$indicesCargaSocial = array_keys($dataCargaSocial[0]);

        $omitEmp = ['codigoempleado'];
        $omit = ['codigoEmpleado'];

        $indicesNominaIngresosReales = array_diff($indicesNominaIngresosReales, $omitEmp);
        //$indicesProvisiones = array_diff($indicesProvisiones, $omit);
        //$indicesCargaSocial = array_diff($indicesCargaSocial, $omit);


        // Aquí quiero obtener las columna de Inicio y fin de las columnas correspondientes a cada sección para utilizarlos en agrupadores

        // Pongo fijo la columna inicio y fin de la sección  DetalleEmpleado por que está fija en el formato excel
        $xlColumnaInicioDetalleEmpleado = 1;
        $xlColumnaFinDetalleEmpleado = 8;

        // Aquí quiero tomar la última columna de sección anterior y le agrego una columna más de separación
        $xlColumnaInicioNominaIngresosReales = $xlColumnaFinDetalleEmpleado + 1;
        $xlColumnaFinNominaIngresosReales = count($indicesNominaIngresosReales) + $xlColumnaInicioNominaIngresosReales;

        //$xlColumnaInicioProvisiones = $xlColumnaFinNominaIngresosReales + 1;
        //$xlColumnaFinProvisiones = count($indicesProvisiones) + $xlColumnaInicioProvisiones;

        //$xlColumnaInicioCargaSocial = $xlColumnaFinProvisiones + 1;
        //$xlColumnaFinCargaSocial = count($indicesCargaSocial) + $xlColumnaInicioCargaSocial;


        // Cantidad de columnas totales que abarcaron todas las secciones
        $xlTotalColumnas = $xlColumnaFinNominaIngresosReales;

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

        // Guardar la columna de separación
        $xlColumnasExcluidas[] = $xlColumnaActual;

        // Me quedo con el útimo recorrido anterior y sumo otra columna más de separación
        $xlColumnaActual++;

        // Poner encabezados de la sección Provisiones
        /*
        foreach ($indicesProvisiones as $key) {
            $xlColumna = Coordinate::stringFromColumnIndex($xlColumnaActual);
            $sheet->setCellValue($xlColumna . $xlFilaEncabezados, $key);
            $xlColumnaActual++;
        }

        // Guardar la columna de separación
        $xlColumnasExcluidas[] = $xlColumnaActual;

        // Me quedo con el útimo recorrido anterior y sumo otra columna más de separación
        $xlColumnaActual++;

        // Poner encabezados de la sección CargaSocial
        foreach ($indicesCargaSocial as $key) {
            $xlColumna = Coordinate::stringFromColumnIndex($xlColumnaActual);
            $sheet->setCellValue($xlColumna . $xlFilaEncabezados, $key);
            $xlColumnaActual++;
        }
        */
        // Construir matriz con todas las secciones
        $xlMatriz = [];
        foreach ($dataDetalleEmpleado as $objDetalleEmpleado) {

            $objNominaIngresosReales = collect($dataNominaIngresosReales)->firstWhere('codigoempleado', $objDetalleEmpleado['codigoempleado']);
            //$objProvisiones = collect($dataProvisiones)->firstWhere('codigoEmpleado', $objDetalleEmpleado['nombre']);
            //$objCargaSocial = collect($dataCargaSocial)->firstWhere('codigoEmpleado', $objDetalleEmpleado['nombre']);

            $fila = [];

            // DetalleEmpleado
            foreach ($indicesDetalleEmpleado as $k) {
                $fila[] = $objDetalleEmpleado[$k] ?? null;
            }

            // NominaIngresosReales
            foreach ($indicesNominaIngresosReales as $nir) {
                $fila[] = $objNominaIngresosReales[$nir] ?? null;
            }

            $fila[] = null;
            /*
            // Provisiones
            foreach ($indicesProvisiones as $k) {
                $fila[] = $objProvisiones[$k] ?? null;
            }

            $fila[] = null;

            // CargaSocial
            foreach ($indicesCargaSocial as $k) {
                $fila[] = $objCargaSocial[$k] ?? null;
            }
*/
            $fila[] = null;

            $xlMatriz[] = $fila;
        }

        // Guardar la columna de separación
        $xlColumnasExcluidas[] = $xlColumnaActual;


        // Insertar filas nuevas sin sobrescribir
        $xlFilaInicioDatos = 13;
        $xlFilaFinDatos = $xlFilaInicioDatos + count($xlMatriz) - 1;



        $sheet->insertNewRowBefore($xlFilaInicioDatos, count($xlMatriz) - 1);

        // Insertar datos masivamente
        $sheet->fromArray($xlMatriz, null, "A12");
        // Aplicar formato dinámico (cantidad/monto)
        $this->aplicarFormatoDinamico($sheet, 11, $xlFilaInicioDatos, $xlFilaFinDatos);


        // Agrupar filas de detalle de filtros
        $this->rowRangeGroup($sheet, 1, 9);

        // Agrupar sección nominaIngresosReales, está empieza de la I
        $this->columnRangeGroup($sheet, 'G', Coordinate::stringFromColumnIndex($xlColumnaFinNominaIngresosReales - 1));

        // Agrupar sección Provisiones
        //$this->columnRangeGroup($sheet, Coordinate::stringFromColumnIndex($xlColumnaInicioProvisiones), Coordinate::stringFromColumnIndex($xlColumnaFinProvisiones  - 1));

        // Agrupar sección CargaSocial
        //$this->columnRangeGroup($sheet, Coordinate::stringFromColumnIndex($xlColumnaInicioCargaSocial), Coordinate::stringFromColumnIndex($xlColumnaFinCargaSocial  - 1));

        // Es importante activar el resumen a la derecha
        $sheet->setShowSummaryRight(true);

        // Fila de totales
        $start = Coordinate::columnIndexFromString("G");
        $end   = $xlColumnaFinNominaIngresosReales;

        foreach (range($start, $end) as $i) {

            $colLetter = Coordinate::stringFromColumnIndex($i);

            // ❗ Si esta columna está en la lista de excluidas, saltar
            if (in_array($i, $xlColumnasExcluidas)) continue;

            $totalRow = $xlFilaFinDatos; // fila donde irá el total (lo moví +1 porque antes estaba pisando datos)
            $findRow = $xlFilaFinDatos - 1; // fila donde irá el total (lo moví +1 porque antes estaba pisando datos)

            // SUMA
            $sheet->setCellValue(
                "{$colLetter}{$totalRow}",
                "=SUM({$colLetter}12:{$colLetter}{$findRow})"
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

        // Ajustar AutoSize columnas A:H
        foreach (range('A', 'F') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
            $sheet->calculateColumnWidths();
        }

        $sheet->getRowDimension(11)->setRowHeight(-1);

        // Asignar formato a todo como monto
        $this->asignarFormatoMonto($sheet, "G:" . Coordinate::stringFromColumnIndex($xlColumnaFinNominaIngresosReales - 1));

        // Asignar formato de cantidad
        $this->aplicarFormatoDinamico($sheet, 11, 12, $xlFilaFinDatos);

        // Estillos de las columnas de totales y netos
        $this->colorearColumnasPorEncabezado($sheet, 11);


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

    function moveShapes($sheet, $origenCol, $origenFila, $anchoCols, $altoRows, $dxCols, $dxRows)
    {
        $drawingCollection = $sheet->getDrawingCollection();

        $colIndex = $this->colToIndex($origenCol);

        foreach ($drawingCollection as $drawing) {

            $coord = $drawing->getCoordinates(); // ejemplo: "Q15"

            // Extraer la columna y fila reales
            preg_match('/([A-Z]+)([0-9]+)/', $coord, $m);

            $col = $m[1];
            $row = intval($m[2]);
            $colIdx = $this->colToIndex($col);

            // Verificar si el shape está dentro del rango a mover (el cuadro completo)
            if (
                $colIdx >= $colIndex &&
                $colIdx <= $colIndex + $anchoCols - 1 &&
                $row >= $origenFila &&
                $row <= $origenFila + $altoRows - 1
            ) {
                // Nuevo destino
                $newCol = $this->indexToCol($colIdx + $dxCols);
                $newRow = $row + $dxRows;

                $drawing->setCoordinates($newCol . $newRow);
            }
        }
    }

    function colToIndex($col)
    {
        return Coordinate::columnIndexFromString($col);
    }

    function indexToCol($i)
    {
        return Coordinate::stringFromColumnIndex($i);
    }

    public function asignarFormatoMonto($sheet, string $rango)
    {
        // Aplicar formato al rango completo (esto sí acepta A:A, A:D, etc.)
        $sheet->getStyle($rango)
            ->getNumberFormat()
            ->setFormatCode('_-$* #,##0.00_-;-$* #,##0.00_-;_-$* "-"??_-;_-@_-');

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
                str_contains($h, 'faltas') ||
                str_contains($h, 'incapacidad')
            ) {
                $sheet->getStyle($range)->getNumberFormat()
                    ->setFormatCode('#,##0');

                $sheet->getColumnDimension($col)->setAutoSize(false);
                $sheet->getColumnDimension($col)->setWidth(10);

                $sheet->getStyle($col)->getAlignment()->setHorizontal('center');
                continue;
            }
        }
    }

    public function colorearColumnasPorEncabezado($sheet, int $headerRow)
    {
        $highestCol = $sheet->getHighestColumn();
        $highestIndex = Coordinate::columnIndexFromString($highestCol);

        for ($i = 1; $i <= $highestIndex; $i++) {

            $col = Coordinate::stringFromColumnIndex($i);
            $header = (string) $sheet->getCell("{$col}{$headerRow}")->getValue();

            // Normalizar
            $h = strtolower(trim($header));
            $h = str_replace(['á', 'é', 'í', 'ó', 'ú'], ['a', 'e', 'i', 'o', 'u'], $h);

            // Validar si está vacío
            if ($h === '') continue;

            // --- TOTAL → VERDE ---
            if (str_contains($h, 'total')) {
                $sheet->getStyle("{$col}{$headerRow}")
                    ->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('92D050'); // verde claro
                continue;
            }

            // --- NETO → NEGRO ---
            if (str_contains($h, 'neto')) {
                $sheet->getStyle("{$col}{$headerRow}")
                    ->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('000000'); // negro
                $sheet->getStyle("{$col}{$headerRow}")
                    ->getFont()->getColor()->setARGB('FFFFFF'); // texto blanco
                continue;
            }
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
}
