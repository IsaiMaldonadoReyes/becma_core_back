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

use Illuminate\Support\Facades\DB;

use App\Http\Controllers\core\HelperController;

class PrenominaController extends Controller
{

    protected $helperController;

    public function __construct(helperController $helperController)
    {
        $this->helperController = $helperController;
    }


    public function datosQuery1($request = null)
    {
        try {
            $idNominaGapeEmpresa = $request->id_nomina_gape_empresa;
            $idTipoPeriodo = $request->id_tipo_periodo;
            $idPeriodo = $request->periodo_inicial;
            //$idDepartamentoInicio = $request->departamento_inicial;
            //$idDepartamentoFinal = $request->departamento_final;

            $idEmpleadoInicial = $request->empleado_inicial;
            $idEmpleadoFinal = $request->empleado_final;

            $conexion = $this->helperController->getConexionDatabaseNGE($idNominaGapeEmpresa, 'Nom');

            $this->helperController->setDatabaseConnection($conexion, $conexion->nombre_base);

            $sql = "
                DECLARE @idPeriodo INT;
                DECLARE @idTipoPeriodo INT;

                DECLARE @idEmpleadoInicial INT;
                DECLARE @idEmpleadoFinal INT;

                SET @idPeriodo = $idPeriodo;
                SET @idTipoPeriodo = $idTipoPeriodo;

                SET @idEmpleadoInicial = $idEmpleadoInicial;
                SET @idEmpleadoFinal = $idEmpleadoFinal;

                ;WITH Movimientos AS (
                    SELECT idempleado, idperiodo, his.idconcepto, valor, importetotal, con.descripcion
                    FROM Nom10007 AS his
					INNER JOIN nom10004 con
                        ON his.idconcepto = con.idconcepto
                    WHERE valor > 0
                        AND idperiodo = @idPeriodo
						AND con.tipoconcepto IN ('P')
                    UNION ALL
                    SELECT idempleado, idperiodo, actual.idconcepto, valor, importetotal, con.descripcion
                    FROM Nom10008 AS actual
					INNER JOIN nom10004 con
                        ON actual.idconcepto = con.idconcepto
                    WHERE valor > 0
                        AND idperiodo = @idPeriodo
						AND con.tipoconcepto IN ('P')
                )
                SELECT
                    emp.codigoempleado
                    , emp.nombrelargo AS nombre
                    , ISNULL(puesto.descripcion, '') AS puesto
                    , FORMAT(emp.fechaalta, 'dd-MM-yyyy') AS fechaAlta
                    , ISNULL(emp.campoextra1, '') AS fechaAltaGape
                    , emp.numerosegurosocial AS nss
                    , emp.rfc + SUBSTRING(CONVERT(char(10),emp.fechanacimiento , 126), 3,2) + SUBSTRING(CONVERT(char(10),emp.fechanacimiento , 126), 6,2) + SUBSTRING(CONVERT(char(10),emp.fechanacimiento, 126), 9,2) + homoclave AS rfc
                    , emp.curpi + SUBSTRING(CONVERT(char(10),emp.fechanacimiento , 126), 3,2) + SUBSTRING(CONVERT(char(10),emp.fechanacimiento , 126), 6,2) + SUBSTRING(CONVERT(char(10),emp.fechanacimiento, 126), 9,2) + emp.curpf AS curp
                    , ISNULL(emp.ccampoextranumerico1, 0) AS sueldoMensual
                    , ISNULL(emp.ccampoextranumerico2, 0) AS sueldoDiario
                    , periodo.diasdepago AS diasPeriodo
                    , SUM(CASE WHEN pdo.descripcion = 'Retroactivo' THEN pdo.valor ELSE 0 END) AS diasRetroactivos
                    , ISNULL(empPeriodo.cdiasincapacidades, 0) AS incapacidad
                    , ISNULL(empPeriodo.cdiasausencia, 0) AS faltas
                    , ISNULL(empPeriodo.cdiaspagados, 0) AS diasPagados
                    , ISNULL((emp.ccampoextranumerico2 * empPeriodo.cdiaspagados), 0) AS sueldo
                FROM nom10001 emp
                    INNER JOIN nom10034 AS empPeriodo
                        ON emp.idempleado = empPeriodo.idempleado
							AND empPeriodo.cidperiodo = @idPeriodo
                    INNER JOIN Movimientos pdo
                        ON empPeriodo.cidperiodo = pdo.idperiodo
							AND emp.idempleado = pdo.idempleado
                    INNER JOIN nom10002 AS periodo
                        ON empPeriodo.cidperiodo = periodo.idperiodo
                    LEFT JOIN nom10006 AS puesto
                        ON emp.idpuesto = puesto.idpuesto
                WHERE empPeriodo.idtipoperiodo = @idTipoPeriodo
                    AND empPeriodo.idempleado BETWEEN @idEmpleadoInicial AND @idEmpleadoFinal
                    AND empPeriodo.estadoempleado IN ('A', 'R')
                GROUP BY
                    codigoempleado
                    , nombrelargo
                    , puesto.descripcion
                    , emp.fechaalta
                    , emp.campoextra1
                    , emp.numerosegurosocial
                    , rfc
                    , emp.fechanacimiento
                    , homoclave
                    , emp.ccampoextranumerico1
                    , emp.ccampoextranumerico2
                    , periodo.diasdepago
                    , empPeriodo.cdiasincapacidades
                    , empPeriodo.cdiasausencia
                    , empPeriodo.cdiaspagados
                    , emp.curpi
                    , emp.curpf
                ORDER BY
                        emp.codigoempleado
        ";

            //return $sql;
            $result = DB::connection('sqlsrv_dynamic')->select($sql);


            return $result;
        } catch (\Exception $e) {
            return [];
        }
    }

    public function datosQuery2($request = null)
    {
        try {
            $idNominaGapeEmpresa = $request->id_nomina_gape_empresa;
            $idTipoPeriodo = $request->id_tipo_periodo;
            $idPeriodo = $request->periodo_inicial;

            $idEmpleadoInicial = $request->empleado_inicial;
            $idEmpleadoFinal = $request->empleado_final;

            $conexion = $this->helperController->getConexionDatabaseNGE($idNominaGapeEmpresa, 'Nom');

            $this->helperController->setDatabaseConnection($conexion, $conexion->nombre_base);

            $sql = "
                DECLARE @idPeriodo INT;
                DECLARE @idTipoPeriodo INT;

                DECLARE @idEmpleadoInicial INT;
                DECLARE @idEmpleadoFinal INT;

                SET @idPeriodo = $idPeriodo;
                SET @idTipoPeriodo = $idTipoPeriodo;

                SET @idEmpleadoInicial = $idEmpleadoInicial;
                SET @idEmpleadoFinal = $idEmpleadoFinal;

                DECLARE @cols NVARCHAR(MAX),
                        @query NVARCHAR(MAX);

                ;WITH Movimientos AS (
                    SELECT idempleado, idperiodo, his.idconcepto, valor, importetotal, con.descripcion, con.tipoconcepto
                    FROM Nom10007 AS his
					INNER JOIN nom10004 con
                        ON his.idconcepto = con.idconcepto
                    WHERE importetotal > 0
                        AND idperiodo = @idPeriodo
						AND con.tipoconcepto IN ('P','D')
						AND con.descripcion NOT IN ('Sueldo','Pensión Alimenticia', 'Pension Alimenticia')
                    UNION ALL
                    SELECT idempleado, idperiodo, actual.idconcepto, valor, importetotal, con.descripcion, con.tipoconcepto
                    FROM Nom10008 AS actual
					INNER JOIN nom10004 con
                        ON actual.idconcepto = con.idconcepto
                    WHERE importetotal > 0
                        AND idperiodo = @idPeriodo
						AND con.tipoconcepto IN ('P','D')
						AND con.descripcion NOT IN ('Sueldo','Pensión Alimenticia', 'Pension Alimenticia')
                ),
                MovimientosFiltrados AS (
                    SELECT
                        pdo.descripcion,
                        pdo.tipoconcepto,
                        CASE WHEN pdo.tipoconcepto = 'P' THEN 10
                            WHEN pdo.tipoconcepto = 'D' THEN 20 ELSE 0 END AS numeroconcepto,
                        CASE WHEN pdo.tipoconcepto = 'P' THEN 1
                            WHEN pdo.tipoconcepto = 'D' THEN 2 ELSE 3 END AS orden
                    FROM nom10001 emp
                        INNER JOIN nom10034 AS empPeriodo
                            ON emp.idempleado = empPeriodo.idempleado
                            AND empPeriodo.cidperiodo = @idPeriodo
                        INNER JOIN Movimientos pdo
                            ON empPeriodo.cidperiodo = pdo.idperiodo
                            AND emp.idempleado = pdo.idempleado
                    WHERE empPeriodo.idtipoperiodo = @idTipoPeriodo
						AND empPeriodo.idempleado BETWEEN @idEmpleadoInicial AND @idEmpleadoFinal
                        AND empPeriodo.estadoempleado IN ('A', 'R')
                    GROUP BY pdo.descripcion, pdo.tipoconcepto
                ),
                Incidencias AS (
					SELECT * FROM (VALUES
						('Comisiones', 'P', 500, 1),
						('Bono', 'P', 500, 1),
						('Horas Extra Doble cantidad', 'P', 500, 1),
						('Horas Extra Doble monto', 'P', 500, 1),
						('Horas Extra Triple cantidad', 'P', 500, 1),
						('Horas Extra Triple monto', 'P', 500, 1),
						('Pago adicional', 'P', 500, 1),
						('Premio puntualidad', 'P', 500, 1),
                        ('Pension Alimenticia', 'D', 500, 2)
					) AS X(descripcion, tipoconcepto, numeroconcepto, orden)
				),
                Encabezados AS (
                    SELECT * FROM (VALUES
                        ('TOTAL PERCEPCIONES', 'P', 1000, 1),
                        ('TOTAL DEDUCCIONES',  'D', 2000, 2),
                        ('NETO',               'N', 3000, 3)
                    ) AS X(descripcion, tipoconcepto, numeroconcepto, orden)
                ),
                titulos AS (
					SELECT descripcion AS descripcion, orden, numeroconcepto FROM Incidencias
					UNION ALL
                    SELECT descripcion + ' cantidad' AS descripcion, orden, numeroconcepto FROM MovimientosFiltrados
                    UNION ALL
                    SELECT descripcion + ' monto', orden, numeroconcepto FROM MovimientosFiltrados
                    UNION ALL
                    SELECT descripcion, orden, numeroconcepto FROM Encabezados
                )

                SELECT @cols = STUFF((
                    SELECT ', ' + QUOTENAME(descripcion)
                    FROM titulos
                    ORDER BY orden, numeroconcepto, descripcion
                    FOR XML PATH(''), TYPE
                ).value('.', 'NVARCHAR(MAX)'), 1, 2, '');

                SET @query = CAST('' AS NVARCHAR(MAX)) + '

                DECLARE @idPeriodo INT;
                DECLARE @idTipoPeriodo INT;

                DECLARE @idEmpleadoInicial INT;
                DECLARE @idEmpleadoFinal INT;
                DECLARE @idNominaGapeEmpresa INT;

                SET @idPeriodo = $idPeriodo;
                SET @idTipoPeriodo = $idTipoPeriodo;

                SET @idEmpleadoInicial = $idEmpleadoInicial;
                SET @idEmpleadoFinal = $idEmpleadoFinal;

                SET @idNominaGapeEmpresa = $idNominaGapeEmpresa;

                ;WITH Movimientos AS (
                    SELECT idempleado, idperiodo, his.idconcepto, valor, importetotal, con.descripcion, con.tipoconcepto
                    FROM Nom10007 AS his
					INNER JOIN nom10004 con
                        ON his.idconcepto = con.idconcepto
                    WHERE importetotal > 0
                        AND idperiodo = @idPeriodo
						AND con.tipoconcepto IN (''P'',''D'')
						AND con.descripcion NOT IN (''Sueldo'',''Pensión Alimenticia'', ''Pension Alimenticia'')
                    UNION ALL
                    SELECT idempleado, idperiodo, actual.idconcepto, valor, importetotal, con.descripcion, con.tipoconcepto
                    FROM Nom10008 AS actual
					INNER JOIN nom10004 con
                        ON actual.idconcepto = con.idconcepto
                    WHERE importetotal > 0
                        AND idperiodo = @idPeriodo
						AND con.tipoconcepto IN (''P'',''D'')
						AND con.descripcion NOT IN (''Sueldo'',''Pensión Alimenticia'', ''Pension Alimenticia'')
                ), ' + '
                MovimientosSuma AS (
                    SELECT
                        emp.codigoempleado,
                        (emp.ccampoextranumerico2 * empPeriodo.cdiaspagados) AS sueldo,
                        pdo.descripcion,
                        pdo.tipoconcepto AS tipoConcepto,
                        SUM(pdo.valor) AS cantidad,
                        SUM(pdo.importetotal) AS monto,
                        emp.ccampoextranumerico3 AS pension
                    FROM nom10001 emp
                    INNER JOIN nom10034 empPeriodo
                        ON emp.idempleado = empPeriodo.idempleado
							AND empPeriodo.cidperiodo = @idPeriodo
                    INNER JOIN Movimientos pdo
                        ON empPeriodo.cidperiodo = pdo.idperiodo
							AND emp.idempleado = pdo.idempleado
                    WHERE empPeriodo.idtipoperiodo = @idTipoPeriodo
                        AND empPeriodo.idempleado BETWEEN @idEmpleadoInicial AND @idEmpleadoFinal
                        AND empPeriodo.estadoempleado IN (''A'', ''R'')
                    GROUP BY
                        emp.codigoempleado, emp.ccampoextranumerico2, empPeriodo.cdiaspagados, pdo.descripcion, pdo.tipoconcepto, emp.ccampoextranumerico3
                ), ' + '
                IncidenciasNormalizadas AS (
					SELECT
						emp.codigoempleado as codigoempleado,
						x.descripcion AS descripcion,
						''P'' AS tipoConcepto,
						x.valor AS valor,
						emp.ccampoextranumerico3 AS pension
					FROM [becma-core2].dbo.nomina_gape_incidencia AS ngi
					INNER JOIN [becma-core2].dbo.nomina_gape_incidencia_detalle AS ngid
						ON ngi.id = ngid.id_nomina_gape_incidencia
					INNER JOIN nom10034 AS empP
						ON ngid.id_empleado = empP.idempleado
						AND empP.cidperiodo = @idPeriodo
						AND empP.idtipoperiodo = @idTipoPeriodo
						AND empP.idempleado BETWEEN @idEmpleadoInicial AND @idEmpleadoFinal
                        AND empP.estadoempleado IN (''A'', ''R'')
					INNER JOIN nom10001 AS emp
						ON empP.idempleado = emp.idempleado
					CROSS APPLY (VALUES
						(''Comisiones'',               ngid.comision),
						(''Bono'',                   ngid.bono),
						(''Horas Extra Doble cantidad'',   ngid.horas_extra_doble_cantidad),
						(''Horas Extra Doble monto'',      ngid.horas_extra_doble),
						(''Horas Extra Triple cantidad'',  ngid.horas_extra_triple_cantidad),
						(''Horas Extra Triple monto'',     ngid.horas_extra_triple),
						(''Pago adicional'',         ngid.pago_adicional),
						(''Premio puntualidad'',     ngid.premio_puntualidad)
					) AS x(descripcion, valor)
					WHERE ngi.id_tipo_periodo = @idTipoPeriodo
					  AND ngi.id_nomina_gape_empresa = @idNominaGapeEmpresa
					  AND ngid.id_empleado BETWEEN @idEmpleadoInicial AND @idEmpleadoFinal
				), ' + '
                TotalesPorEmpleado AS (
					SELECT
						codigoempleado,
						MAX(sueldo) AS sueldo,
						SUM(CASE
								WHEN tipoConcepto = ''P'' THEN monto
								ELSE 0
							END)
						+
						SUM(CASE
								WHEN tipoConcepto = ''P'' THEN valor
								ELSE 0
							END)
						AS total_percepciones_sin_sueldo,
						SUM(CASE
								WHEN tipoConcepto = ''D'' THEN monto
								ELSE 0
							END)
						AS total_deducciones,
						pension AS porcentajePension

					FROM (
						SELECT
							codigoempleado,
							sueldo,
							tipoConcepto,
							monto AS monto,
							0 AS valor,
							pension
						FROM MovimientosSuma

						UNION ALL
						SELECT
							codigoempleado,
							0 AS sueldo,
							''P'' AS tipoConcepto,
							0 AS monto,
							valor AS valor,
							pension
						FROM IncidenciasNormalizadas
					) AS x
					GROUP BY codigoempleado, pension
				), ' + '
                MovimientosPivot AS (
					SELECT
						codigoempleado,
						MAX(sueldo) OVER (PARTITION BY codigoempleado) AS sueldo,
						descripcion AS columna,
						valor
					FROM (
						SELECT
							codigoempleado,
							0 AS sueldo,
							descripcion,
							valor
						FROM IncidenciasNormalizadas
						UNION ALL
						SELECT
							codigoempleado,
							sueldo,
							descripcion + '' cantidad'',
							cantidad
						FROM MovimientosSuma
						UNION ALL
						SELECT
							codigoempleado,
							sueldo,
							descripcion + '' monto'',
							monto
						FROM MovimientosSuma
						UNION ALL
						SELECT
							codigoempleado,
							sueldo,
							''TOTAL PERCEPCIONES'',
							sueldo + total_percepciones_sin_sueldo
						FROM TotalesPorEmpleado
						UNION ALL
                        SELECT
							codigoempleado,
							sueldo,
							''Pension Alimenticia'',
							CASE WHEN porcentajePension > 0 THEN (sueldo + total_percepciones_sin_sueldo) * (porcentajePension / 100) ELSE 0 END
						FROM TotalesPorEmpleado
						UNION ALL
						SELECT
							codigoempleado,
							sueldo,
							''TOTAL DEDUCCIONES'',
							total_deducciones + (CASE WHEN porcentajePension > 0 THEN (sueldo + total_percepciones_sin_sueldo) * (porcentajePension / 100) ELSE 0 END)
						FROM TotalesPorEmpleado
						UNION ALL
						SELECT
							codigoempleado,
							sueldo,
							''NETO'',
							(sueldo + total_percepciones_sin_sueldo) - (total_deducciones + CASE WHEN porcentajePension > 0 THEN (sueldo + total_percepciones_sin_sueldo) * (porcentajePension / 100) ELSE 0 END)
						FROM TotalesPorEmpleado
					) AS X
				) ' + '
                SELECT codigoempleado, ' + @cols + '
                FROM MovimientosPivot
                PIVOT (
                    SUM(valor)
                    FOR columna IN (' + @cols + ')
                ) AS p
                ORDER BY codigoempleado;
                ';

                EXEC(@query);
             ";

            $result = DB::connection('sqlsrv_dynamic')->select($sql);

            //return $sql;
            return $result;
        } catch (\Exception $e) {
            return [];
        }
    }

    public function datosQuery3($request = null)
    {
        try {
            $idNominaGapeEmpresa = $request->id_nomina_gape_empresa;
            $idTipoPeriodo = $request->id_tipo_periodo;
            $idPeriodo = $request->periodo_inicial;

            $idEmpleadoInicial = $request->empleado_inicial;
            $idEmpleadoFinal = $request->empleado_final;

            $conexion = $this->helperController->getConexionDatabaseNGE($idNominaGapeEmpresa, 'Nom');

            $this->helperController->setDatabaseConnection($conexion, $conexion->nombre_base);

            $sql = "
                DECLARE @idPeriodo INT;
                DECLARE @idTipoPeriodo INT;
                DECLARE @idDepartamentoInicial INT;
                DECLARE @idDepartamentoFinal INT;

                DECLARE @idEmpleadoInicial INT;
                DECLARE @idEmpleadoFinal INT;

                SET @idPeriodo = $idPeriodo;
                SET @idTipoPeriodo = $idTipoPeriodo;

                SET @idEmpleadoInicial = $idEmpleadoInicial;
                SET @idEmpleadoFinal = $idEmpleadoFinal;


                DECLARE @cols NVARCHAR(MAX),
                        @query NVARCHAR(MAX);

                ;WITH Movimientos AS (
                    SELECT idempleado, idperiodo, his.idconcepto, valor, importetotal, con.descripcion, con.tipoconcepto
                    FROM Nom10007 AS his
					INNER JOIN nom10004 con
                        ON his.idconcepto = con.idconcepto
                    WHERE importetotal > 0
                        AND idperiodo = @idPeriodo
						AND con.tipoconcepto IN ('P','D')
						AND con.descripcion NOT IN ('Pensión Alimenticia', 'Pension Alimenticia')
                    UNION ALL
                    SELECT idempleado, idperiodo, actual.idconcepto, valor, importetotal, con.descripcion, con.tipoconcepto
                    FROM Nom10008 AS actual
					INNER JOIN nom10004 con
                        ON actual.idconcepto = con.idconcepto
                    WHERE importetotal > 0
                        AND idperiodo = @idPeriodo
						AND con.tipoconcepto IN ('P','D')
						AND con.descripcion NOT IN ('Pensión Alimenticia', 'Pension Alimenticia')
                ),
                MovimientosFiltrados AS (
                    SELECT
                        pdo.descripcion,
                        pdo.tipoconcepto,
                        CASE WHEN pdo.tipoconcepto = 'P' THEN 10
                            WHEN pdo.tipoconcepto = 'D' THEN 20 ELSE 0 END AS numeroconcepto,
                        CASE WHEN pdo.tipoconcepto = 'P' THEN 1
                            WHEN pdo.tipoconcepto = 'D' THEN 2 ELSE 3 END AS orden
                    FROM nom10001 emp
                        INNER JOIN nom10034 AS empPeriodo
                            ON emp.idempleado = empPeriodo.idempleado
                            AND empPeriodo.cidperiodo = @idPeriodo
                        INNER JOIN Movimientos pdo
                            ON empPeriodo.cidperiodo = pdo.idperiodo
                            AND emp.idempleado = pdo.idempleado
                    WHERE empPeriodo.idtipoperiodo = @idTipoPeriodo
						AND empPeriodo.idempleado BETWEEN @idEmpleadoInicial AND @idEmpleadoFinal
                        AND empPeriodo.estadoempleado IN ('A', 'R')
                    GROUP BY
                        pdo.descripcion, pdo.tipoconcepto
                ),
                Encabezados AS (
                    SELECT * FROM (VALUES
                        ('TOTAL PERCEPCIONES FISCAL', 'P', 1000, 1),
                        ('Pension Alimenticia', 'D', 20, 2),
                        ('TOTAL DEDUCCIONES FISCAL',  'D', 2000, 2),
                        ('NETO FISCAL',               'N', 3000, 3)
                    ) AS X(descripcion, tipoconcepto, numeroconcepto, orden)
                ),
                titulos AS (
                    SELECT descripcion AS descripcion, orden, numeroconcepto FROM MovimientosFiltrados
                    UNION ALL
                    SELECT descripcion, orden, numeroconcepto FROM Encabezados
                )

                SELECT @cols = STUFF((
                    SELECT ', ' + QUOTENAME(descripcion)
                    FROM titulos
                    ORDER BY orden, numeroconcepto, descripcion
                    FOR XML PATH(''), TYPE
                ).value('.', 'NVARCHAR(MAX)'), 1, 2, '');

                SET @query = CAST('' AS NVARCHAR(MAX)) + '
                    DECLARE @idPeriodo INT;
                    DECLARE @idTipoPeriodo INT;

                    DECLARE @idEmpleadoInicial INT;
                    DECLARE @idEmpleadoFinal INT;

                    SET @idPeriodo = $idPeriodo;
                    SET @idTipoPeriodo = $idTipoPeriodo;

                    SET @idEmpleadoInicial = $idEmpleadoInicial;
                    SET @idEmpleadoFinal = $idEmpleadoFinal;

                    ;WITH Movimientos AS (
						SELECT idempleado, idperiodo, his.idconcepto, valor, importetotal, con.descripcion, con.tipoconcepto
						FROM Nom10007 AS his
						INNER JOIN nom10004 con
							ON his.idconcepto = con.idconcepto
						WHERE importetotal > 0
							AND idperiodo = @idPeriodo
							AND con.tipoconcepto IN (''P'',''D'')
							AND con.descripcion NOT IN (''Pensión Alimenticia'', ''Pension Alimenticia'')
						UNION ALL
						SELECT idempleado, idperiodo, actual.idconcepto, valor, importetotal, con.descripcion, con.tipoconcepto
						FROM Nom10008 AS actual
						INNER JOIN nom10004 con
							ON actual.idconcepto = con.idconcepto
						WHERE importetotal > 0
							AND idperiodo = @idPeriodo
							AND con.tipoconcepto IN (''P'',''D'')
							AND con.descripcion NOT IN (''Pensión Alimenticia'', ''Pension Alimenticia'')
					), ' + '
                    MovimientosSuma AS (
                        SELECT
                            emp.codigoempleado,
                            empPeriodo.sueldodiario AS sd,
                            empPeriodo.sueldointegrado AS sdi,
                            pdo.descripcion,
							pdo.tipoconcepto AS tipoConcepto,
                            SUM(pdo.importetotal) AS monto,
							emp.ccampoextranumerico3 AS pension
                        FROM nom10001 emp
                        INNER JOIN nom10034 empPeriodo
                            ON emp.idempleado = empPeriodo.idempleado
                            AND empPeriodo.cidperiodo = @idPeriodo
                        INNER JOIN Movimientos pdo
                            ON empPeriodo.cidperiodo = pdo.idperiodo
                        AND emp.idempleado = pdo.idempleado
                        WHERE empPeriodo.idtipoperiodo = @idTipoPeriodo
							AND pdo.descripcion NOT IN (''Pensión Alimenticia'', ''Pension Alimenticia'')
                            AND empPeriodo.estadoempleado IN (''A'', ''R'')
							AND empPeriodo.idempleado BETWEEN @idEmpleadoInicial AND @idEmpleadoFinal
                        GROUP BY
							emp.codigoempleado, empPeriodo.sueldodiario, empPeriodo.sueldointegrado, pdo.descripcion, pdo.tipoconcepto, emp.ccampoextranumerico3
                    ), ' + '
                    TotalesPorEmpleado AS (
						SELECT
							codigoempleado,
							sd,
							sdi,

							SUM(CASE
									WHEN tipoConcepto = ''P'' THEN monto
									ELSE 0
								END)
							AS total_percepciones,
							SUM(CASE
									WHEN tipoConcepto = ''D'' THEN monto
									ELSE 0
								END)
							AS total_deducciones,
							pension AS porcentajePension

						FROM MovimientosSuma
						GROUP BY codigoempleado, pension, sdi, sd
					), ' + '
                    MovimientosPivot AS (
                        SELECT
                            ms.codigoempleado,
                            ms.sd,
                            ms.sdi,
                            ms.descripcion AS columna,
                            ms.monto AS valor
                        FROM MovimientosSuma ms
                        UNION ALL
                        SELECT
                            t.codigoempleado,
                            t.sd,
                            t.sdi,
                            ''TOTAL PERCEPCIONES FISCAL'' AS columna,
                            t.total_percepciones AS valor
                        FROM TotalesPorEmpleado t
                        UNION ALL
						SELECT
							t.codigoempleado,
							t.sd,
                            t.sdi,
							''Pension Alimenticia'' AS columna,
							CASE WHEN porcentajePension > 0 THEN (total_percepciones) * (porcentajePension / 100) ELSE 0 END
						FROM TotalesPorEmpleado t
						UNION ALL
                        SELECT
                            t.codigoempleado,
                            t.sd,
                            t.sdi,
                            ''TOTAL DEDUCCIONES FISCAL'' AS columna,
                            (t.total_deducciones + CASE WHEN porcentajePension > 0 THEN (total_percepciones) * (porcentajePension / 100) ELSE 0 END) AS valor
                        FROM TotalesPorEmpleado t
                        UNION ALL
                        SELECT
                            t.codigoempleado,
                            t.sd,
                            t.sdi,
                            ''NETO FISCAL'' AS columna,
                            (t.total_percepciones) - (t.total_deducciones + CASE WHEN porcentajePension > 0 THEN (total_percepciones) * (porcentajePension / 100) ELSE 0 END) AS valor
                        FROM TotalesPorEmpleado t
                    ) ' + '
                    SELECT codigoempleado, sd, sdi, ' + @cols + '
                    FROM MovimientosPivot
                    PIVOT (
                        SUM(valor)
                        FOR columna IN (' + @cols + ')
                    ) AS p
                    ORDER BY codigoempleado;
                    ';

                EXEC(@query);
            ";

            //return $sql;
            $result = DB::connection('sqlsrv_dynamic')->select($sql);

            return $result;
        } catch (\Exception $e) {
            return [];
        }
    }

    public function datosQuery4($request = null)
    {
        try {
            $idNominaGapeEmpresa = $request->id_nomina_gape_empresa;
            $idTipoPeriodo = $request->id_tipo_periodo;
            $idPeriodo = $request->periodo_inicial;

            $idEmpleadoInicial = $request->empleado_inicial;
            $idEmpleadoFinal = $request->empleado_final;

            $conexion = $this->helperController->getConexionDatabaseNGE($idNominaGapeEmpresa, 'Nom');

            $this->helperController->setDatabaseConnection($conexion, $conexion->nombre_base);

            $sql = "
                DECLARE @idPeriodo INT;
                DECLARE @idTipoPeriodo INT;

                DECLARE @idEmpleadoInicial INT;
                DECLARE @idEmpleadoFinal INT;

                DECLARE @idNominaGapeEmpresa INT;

                SET @idPeriodo = $idPeriodo;
                SET @idTipoPeriodo = $idTipoPeriodo;

                SET @idEmpleadoInicial = $idEmpleadoInicial;
                SET @idEmpleadoFinal = $idEmpleadoFinal;

                SET @idNominaGapeEmpresa = $idNominaGapeEmpresa;

                DECLARE @cols NVARCHAR(MAX),
                        @sql  NVARCHAR(MAX);
                ;WITH ParamConfig AS (
                    SELECT
                        sueldo_imss, sueldo_imss_tope, sueldo_imss_orden
                        , prev_social, prev_social_tope, prev_social_orden
                        , fondos_sind, fondos_sind_tope, fondos_sind_orden
                        , tarjeta_facil, tarjeta_facil_tope, tarjeta_facil_orden
                        , hon_asimilados, hon_asimilados_tope, hon_asimilados_orden
                        , gastos_compro, gastos_compro_tope, gastos_compro_orden
                    FROM [becma-core2].[dbo].[nomina_gape_concepto_pago_parametrizacion]
                    WHERE id_tipo_periodo = @idTipoPeriodo
                    AND id_nomina_gape_empresa = @idNominaGapeEmpresa
                ),
                ConceptosParametrizados AS (
                    SELECT 'sueldo_imss' AS concepto, sueldo_imss AS activo, sueldo_imss_tope AS tope, sueldo_imss_orden AS orden FROM ParamConfig
                    UNION ALL SELECT 'prev_social', prev_social, prev_social_tope, prev_social_orden FROM ParamConfig
                    UNION ALL SELECT 'fondos_sind', fondos_sind, fondos_sind_tope, fondos_sind_orden FROM ParamConfig
                    UNION ALL SELECT 'tarjeta_facil', tarjeta_facil, tarjeta_facil_tope, tarjeta_facil_orden FROM ParamConfig
                    UNION ALL SELECT 'hon_asimilados', hon_asimilados, hon_asimilados_tope, hon_asimilados_orden FROM ParamConfig
                    UNION ALL SELECT 'gastos_compro', gastos_compro, gastos_compro_tope, gastos_compro_orden FROM ParamConfig
                ),
                Movimientos AS (
                    SELECT idempleado, idperiodo, his.idconcepto, valor, importetotal, con.descripcion, con.tipoconcepto
                    FROM Nom10007 AS his
					INNER JOIN nom10004 con
                        ON his.idconcepto = con.idconcepto
                    WHERE importetotal > 0
                        AND idperiodo = @idPeriodo
						AND con.tipoconcepto IN ('D')
						AND con.descripcion NOT IN ('Pensión Alimenticia', 'Pension Alimenticia')
                    UNION ALL
                    SELECT idempleado, idperiodo, actual.idconcepto, valor, importetotal, con.descripcion, con.tipoconcepto
                    FROM Nom10008 AS actual
					INNER JOIN nom10004 con
                        ON actual.idconcepto = con.idconcepto
                    WHERE importetotal > 0
                        AND idperiodo = @idPeriodo
						AND con.tipoconcepto IN ('D')
						AND con.descripcion NOT IN ('Pensión Alimenticia', 'Pension Alimenticia')
                ),
                MovimientosFiltrados AS (
                    SELECT
                        pdo.descripcion,
                        pdo.tipoconcepto,
                        CASE WHEN pdo.tipoconcepto = 'P' THEN 10
                            WHEN pdo.tipoconcepto = 'D' THEN 200 ELSE 0 END AS numeroconcepto,
                        CASE WHEN pdo.tipoconcepto = 'P' THEN 1
                            WHEN pdo.tipoconcepto = 'D' THEN 200 ELSE 3 END AS orden
                    FROM nom10001 emp
                        INNER JOIN nom10034 AS empPeriodo
                            ON emp.idempleado = empPeriodo.idempleado
                            AND empPeriodo.cidperiodo = @idPeriodo
                        INNER JOIN Movimientos pdo
                            ON empPeriodo.cidperiodo = pdo.idperiodo
                            AND emp.idempleado = pdo.idempleado
                    WHERE empPeriodo.idtipoperiodo = @idTipoPeriodo
						AND empPeriodo.idempleado BETWEEN @idEmpleadoInicial AND @idEmpleadoFinal
						AND pdo.tipoconcepto = 'P'
                    GROUP BY pdo.descripcion, pdo.tipoconcepto
                ),
                Encabezados AS (
                    SELECT * FROM (VALUES
                        ('TOTAL PERCEPCION EXCEDENTE', 'P', 1000, 100),
                        ('Pension Excedente',  'D', 2000, 200),
                        ('TOTAL DEDUCCION EXCEDENTE',  'D', 2000, 299),
                        ('NETO EXCEDENTE', 'N', 3000, 300),
                        ('NETO TOTAL A PAGAR', 'N', 4000, 400)
                    ) AS X(descripcion, tipoconcepto, numeroconcepto, orden)
                ),
                ConceptosActivos AS (
                    SELECT concepto, tope, orden
                    FROM ConceptosParametrizados
                    WHERE activo = 1
                ),
                titulos AS (
                    SELECT descripcion AS descripcion, orden FROM MovimientosFiltrados
                    UNION ALL
                    SELECT descripcion AS descripcion, orden FROM Encabezados
                    UNION ALL
                    SELECT concepto AS descripcion, orden FROM ConceptosActivos
                )


                SELECT @cols = STUFF((
                    SELECT ', ' + QUOTENAME(descripcion)
                    FROM titulos
                    ORDER BY orden
                    FOR XML PATH(''), TYPE
                ).value('.', 'NVARCHAR(MAX)'), 1, 2, '');

                SET @sql = CAST('' AS NVARCHAR(MAX)) + '
                DECLARE @idPeriodo INT;
                DECLARE @idTipoPeriodo INT;

                DECLARE @idEmpleadoInicial INT;
                DECLARE @idEmpleadoFinal INT;

                DECLARE @idNominaGapeEmpresa INT;

                SET @idPeriodo = $idPeriodo;
                SET @idTipoPeriodo = $idTipoPeriodo;

                SET @idEmpleadoInicial = $idEmpleadoInicial;
                SET @idEmpleadoFinal = $idEmpleadoFinal;

                SET @idNominaGapeEmpresa = $idNominaGapeEmpresa;

                ;WITH
                Movimientos AS (
					SELECT idempleado, idperiodo, his.idconcepto, valor, importetotal, con.descripcion, con.tipoconcepto
					FROM Nom10007 AS his
					INNER JOIN nom10004 con
						ON his.idconcepto = con.idconcepto
					WHERE importetotal > 0
						AND idperiodo = @idPeriodo
						AND con.tipoconcepto IN (''P'',''D'')
					UNION ALL
					SELECT idempleado, idperiodo, actual.idconcepto, valor, importetotal, con.descripcion, con.tipoconcepto
					FROM Nom10008 AS actual
					INNER JOIN nom10004 con
						ON actual.idconcepto = con.idconcepto
					WHERE importetotal > 0
						AND idperiodo = @idPeriodo
						AND con.tipoconcepto IN (''P'',''D'')
				), ' + '
				MovimientosSumaQ2 AS (
                    SELECT
                        emp.codigoempleado,
                        (emp.ccampoextranumerico2 * empPeriodo.cdiaspagados) AS sueldo,
                        pdo.descripcion,
                        pdo.tipoconcepto AS tipoConcepto,
                        SUM(pdo.valor) AS cantidad,
                        SUM(pdo.importetotal) AS monto,
						emp.ccampoextranumerico3 AS pension
                    FROM nom10001 emp
                    INNER JOIN nom10034 empPeriodo
                        ON emp.idempleado = empPeriodo.idempleado
							AND empPeriodo.cidperiodo = @idPeriodo
                    INNER JOIN Movimientos pdo
                        ON empPeriodo.cidperiodo = pdo.idperiodo
						AND emp.idempleado = pdo.idempleado
                    WHERE empPeriodo.idtipoperiodo = @idTipoPeriodo
                        AND pdo.descripcion NOT IN(''Sueldo'',''Pensión Alimenticia'', ''Pension Alimenticia'')
						AND empPeriodo.estadoempleado IN (''A'', ''R'')
                        AND empPeriodo.idempleado BETWEEN @idEmpleadoInicial AND @idEmpleadoFinal
                    GROUP BY
                        emp.codigoempleado, emp.ccampoextranumerico2, empPeriodo.cdiaspagados, pdo.descripcion, pdo.tipoconcepto, emp.ccampoextranumerico3
                ), ' + '
				IncidenciasNormalizadasQ2 AS (
					SELECT
						emp.codigoempleado as codigoempleado,
						x.descripcion AS descripcion,
						''P'' AS tipoConcepto,
						x.valor AS valor,
						emp.ccampoextranumerico3 AS pension
					FROM [becma-core2].dbo.nomina_gape_incidencia AS ngi
					INNER JOIN [becma-core2].dbo.nomina_gape_incidencia_detalle AS ngid
						ON ngi.id = ngid.id_nomina_gape_incidencia
					INNER JOIN nom10034 AS empP
						ON ngid.id_empleado = empP.idempleado
						AND empP.cidperiodo = @idPeriodo
						AND empP.idtipoperiodo = @idTipoPeriodo
						AND empP.idempleado BETWEEN @idEmpleadoInicial AND @idEmpleadoFinal
						AND empP.estadoempleado IN (''A'', ''R'')
					INNER JOIN nom10001 AS emp
						ON empP.idempleado = emp.idempleado
					CROSS APPLY (VALUES
						(''Comisiones'',               ngid.comision),
						(''Bono'',                   ngid.bono),
						(''Horas Extra Doble cantidad'',   ngid.horas_extra_doble_cantidad),
						(''Horas Extra Doble monto'',      ngid.horas_extra_doble),
						(''Horas Extra Triple cantidad'',  ngid.horas_extra_triple_cantidad),
						(''Horas Extra Triple monto'',     ngid.horas_extra_triple),
						(''Pago adicional'',         ngid.pago_adicional),
						(''Premio puntualidad'',     ngid.premio_puntualidad)
					) AS x(descripcion, valor)
					WHERE ngi.id_tipo_periodo = @idTipoPeriodo
					  AND ngi.id_nomina_gape_empresa = @idNominaGapeEmpresa
					  AND ngid.id_empleado BETWEEN @idEmpleadoInicial AND @idEmpleadoFinal
				), ' + '
                TotalesPorEmpleadoGeneralQ2 AS (
					SELECT
						codigoempleado,
						MAX(sueldo) AS sueldo,
						SUM(CASE WHEN tipoConcepto = ''P'' THEN monto ELSE 0 END)
						+
						SUM(CASE WHEN tipoConcepto = ''P'' THEN valor ELSE 0 END) AS total_percepciones_sin_sueldo,
						SUM(CASE WHEN tipoConcepto = ''D'' THEN monto ELSE 0 END) AS total_deducciones,
						pension AS porcentajePension

					FROM (
						SELECT
							codigoempleado,
							sueldo,
							tipoConcepto,
							monto AS monto,
							0 AS valor,
							pension
						FROM MovimientosSumaQ2
						UNION ALL
						SELECT
							codigoempleado,
							0 AS sueldo,
							''P'' AS tipoConcepto,
							0 AS monto,
							valor AS valor,
							pension
						FROM IncidenciasNormalizadasQ2
					) AS x
					GROUP BY codigoempleado, pension
				),
				MovimientosSumaQ3 AS (
                        SELECT
                            emp.codigoempleado,
                            empPeriodo.sueldodiario AS sd,
                            empPeriodo.sueldointegrado AS sdi,
                            pdo.descripcion,
							pdo.tipoconcepto AS tipoConcepto,
                            SUM(pdo.importetotal) AS monto,
							emp.ccampoextranumerico3 AS pension
                        FROM nom10001 emp
                        INNER JOIN nom10034 empPeriodo
                            ON emp.idempleado = empPeriodo.idempleado
                            AND empPeriodo.cidperiodo = @idPeriodo
                        INNER JOIN Movimientos pdo
                            ON empPeriodo.cidperiodo = pdo.idperiodo
							AND emp.idempleado = pdo.idempleado
                        WHERE empPeriodo.idtipoperiodo = @idTipoPeriodo
							AND pdo.descripcion NOT IN (''Pensión Alimenticia'', ''Pension Alimenticia'')
							AND empPeriodo.estadoempleado IN (''A'', ''R'')
							AND empPeriodo.idempleado BETWEEN @idEmpleadoInicial AND @idEmpleadoFinal
                        GROUP BY
							emp.codigoempleado, empPeriodo.sueldodiario, empPeriodo.sueldointegrado, pdo.descripcion, pdo.tipoconcepto, emp.ccampoextranumerico3
                ), ' + '
				TotalesPorEmpleadoQ3 AS (
						SELECT
							codigoempleado,
							sd,
							sdi,
							SUM(CASE WHEN tipoConcepto = ''P'' THEN monto ELSE 0 END) AS total_percepciones,
							SUM(CASE WHEN tipoConcepto = ''D'' THEN monto ELSE 0 END) AS total_deducciones,
							pension AS porcentajePension
						FROM MovimientosSumaQ3
						GROUP BY codigoempleado, pension, sdi, sd
				), ' + ' -------- se omite esto por el momento porque no se sabe cuales son las deducciones excedentes
                MovimientosSumaExcedente AS (
                    SELECT
                        emp.codigoempleado,
                        pdo.descripcion,
                        SUM(pdo.importeTotal) AS monto
                    FROM nom10001 emp
                    INNER JOIN nom10034 empPeriodo
						ON emp.idempleado = empPeriodo.idempleado
						AND empPeriodo.cidperiodo = @idPeriodo
                    INNER JOIN Movimientos pdo
						ON empPeriodo.cidperiodo = pdo.idperiodo
						AND emp.idempleado = pdo.idempleado
                    WHERE empPeriodo.idtipoperiodo = @idTipoPeriodo
						AND pdo.tipoconcepto IN (''P'')
						AND pdo.descripcion NOT IN (''Pensión Alimenticia'', ''Pension Alimenticia'')
						AND empPeriodo.estadoempleado IN (''A'', ''R'')
						AND empPeriodo.idempleado BETWEEN @idEmpleadoInicial AND @idEmpleadoFinal
                    GROUP BY
						emp.codigoempleado, empPeriodo.sueldodiario, empPeriodo.sueldointegrado, pdo.descripcion
                ), ' + ' -------- se omite esto por el momento porque no se sabe cuales son las deducciones excedentes
                TotalesPorEmpleadoDeduccionesExcedentes AS (
                    SELECT
                        emp.codigoempleado,
                        (emp.ccampoextranumerico2 * empPeriodo.cdiaspagados) AS sueldo,
                        SUM(CASE WHEN pdo.tipoconcepto = ''D'' THEN pdo.importetotal ELSE 0 END) AS total_deducciones_exc
                    FROM nom10001 emp
                    INNER JOIN nom10034 empPeriodo
						ON emp.idempleado = empPeriodo.idempleado
						AND empPeriodo.cidperiodo = @idPeriodo
                    INNER JOIN Movimientos pdo
						ON empPeriodo.cidperiodo = pdo.idperiodo
						AND emp.idempleado = pdo.idempleado
                    WHERE empPeriodo.idtipoperiodo = @idTipoPeriodo
						AND pdo.tipoconcepto IN (''P'')
						AND pdo.descripcion NOT IN (''Pensión Alimenticia'', ''Pension Alimenticia'')
						AND empPeriodo.estadoempleado IN (''A'', ''R'')
						AND empPeriodo.idempleado BETWEEN @idEmpleadoInicial AND @idEmpleadoFinal
                    GROUP BY
						emp.codigoempleado, emp.ccampoextranumerico2, empPeriodo.cdiaspagados
                ), ' + '
                TotalesPorEmpleado AS ( -- totales generales Query1
                    SELECT
                        emp.codigoempleado,
                        (emp.ccampoextranumerico2 * empPeriodo.cdiaspagados) AS sueldo,
                        SUM(CASE WHEN pdo.tipoconcepto = ''P'' THEN pdo.importetotal ELSE 0 END) AS total_percepciones_sin_sueldo,
                        SUM(CASE WHEN pdo.tipoconcepto = ''D'' THEN pdo.importetotal ELSE 0 END) AS total_deducciones
                    FROM nom10001 emp
                    INNER JOIN nom10034 empPeriodo
						ON emp.idempleado = empPeriodo.idempleado
						AND empPeriodo.cidperiodo = @idPeriodo
                    INNER JOIN Movimientos pdo
						ON empPeriodo.cidperiodo = pdo.idperiodo
						AND emp.idempleado = pdo.idempleado
                    WHERE empPeriodo.idtipoperiodo = @idTipoPeriodo
						AND pdo.tipoconcepto IN (''P'',''D'')
						AND pdo.descripcion NOT IN (''Sueldo'')
						AND empPeriodo.idempleado BETWEEN @idEmpleadoInicial AND @idEmpleadoFinal
                    GROUP BY
						emp.codigoempleado, emp.ccampoextranumerico2, empPeriodo.cdiaspagados
                ), ' + '
                TotalesPorEmpleadoFis AS ( -- totales P D sin pension Query3
                    SELECT
                        emp.codigoempleado,
                        empPeriodo.sueldodiario AS sd,
                        empPeriodo.sueldointegrado AS sdi,
                        SUM(CASE WHEN pdo.tipoconcepto = ''P'' THEN pdo.importetotal ELSE 0 END) AS total_percepciones,
                        SUM(CASE WHEN pdo.tipoconcepto = ''D'' THEN pdo.importetotal ELSE 0 END) AS total_deducciones
                    FROM nom10001 emp
                    INNER JOIN nom10034 empPeriodo
						ON emp.idempleado = empPeriodo.idempleado
						AND empPeriodo.cidperiodo = @idPeriodo
                    INNER JOIN Movimientos pdo
						ON empPeriodo.cidperiodo = pdo.idperiodo
						AND emp.idempleado = pdo.idempleado
                    WHERE empPeriodo.idtipoperiodo = @idTipoPeriodo
						AND empPeriodo.idempleado BETWEEN @idEmpleadoInicial AND @idEmpleadoFinal
                    GROUP BY
						emp.codigoempleado, empPeriodo.sueldodiario, empPeriodo.sueldointegrado
                ), ' + '
                preNetoTotalAPagar AS ( -- totales P - D Sin pension Query3
                    SELECT
                        tpf.codigoempleado,
                        (tpf.total_percepciones - tpf.total_deducciones) AS netoTotalAPagar
                    FROM TotalesPorEmpleadoFis tpf
                ), ' + '
                preTotalesProrrateo AS (
                    SELECT
                        q2.codigoempleado,
						((q2.sueldo + q2.total_percepciones_sin_sueldo) - (q2.total_deducciones + CASE WHEN q2.porcentajePension > 0 THEN (q2.sueldo + q2.total_percepciones_sin_sueldo) * (q2.porcentajePension / 100) ELSE 0 END))
						-
						((q3.total_percepciones) - (q3.total_deducciones + CASE WHEN q3.porcentajePension > 0 THEN (q3.total_percepciones) * (q3.porcentajePension / 100) ELSE 0 END))
						AS netoAPagar
                    FROM TotalesPorEmpleadoGeneralQ2 q2
                    INNER JOIN TotalesPorEmpleadoQ3 q3
                        ON q2.codigoempleado = q3.codigoempleado
                ), ' + '
                ParamConfig AS (
                    SELECT
                        sueldo_imss, sueldo_imss_tope, sueldo_imss_orden
                        , prev_social, prev_social_tope, prev_social_orden
                        , fondos_sind, fondos_sind_tope, fondos_sind_orden
                        , tarjeta_facil, tarjeta_facil_tope, tarjeta_facil_orden
                        , hon_asimilados, hon_asimilados_tope, hon_asimilados_orden
                        , gastos_compro, gastos_compro_tope, gastos_compro_orden
                    FROM [becma-core2].[dbo].[nomina_gape_concepto_pago_parametrizacion]
                    WHERE id_tipo_periodo = @idTipoPeriodo
                    AND id_nomina_gape_empresa =   @idNominaGapeEmpresa
                ), ' + '
                ConceptosParametrizados AS (
                    SELECT ''sueldo_imss'' AS concepto, sueldo_imss AS activo, sueldo_imss_tope AS tope, sueldo_imss_orden AS orden FROM ParamConfig
                    UNION ALL SELECT ''prev_social'',   prev_social,   prev_social_tope,   prev_social_orden   FROM ParamConfig
                    UNION ALL SELECT ''fondos_sind'',   fondos_sind,   fondos_sind_tope,   fondos_sind_orden   FROM ParamConfig
                    UNION ALL SELECT ''tarjeta_facil'', tarjeta_facil, tarjeta_facil_tope, tarjeta_facil_orden FROM ParamConfig
                    UNION ALL SELECT ''hon_asimilados'',hon_asimilados, hon_asimilados_tope, hon_asimilados_orden FROM ParamConfig
                    UNION ALL SELECT ''gastos_compro'', gastos_compro, gastos_compro_tope, gastos_compro_orden FROM ParamConfig
                ), ' + '
                ConceptosActivos AS (
                    SELECT concepto, CAST(tope AS DECIMAL(18,2)) AS tope, orden
                    FROM ConceptosParametrizados
                    WHERE activo = 1
                ), ' + '
                BaseProrrateo AS (
                    SELECT
                        p.codigoempleado,
                        CAST(p.netoAPagar AS DECIMAL(18,2)) AS netoAPagar,
                        c.concepto,
                        c.tope,
                        c.orden,
                        ROW_NUMBER() OVER (PARTITION BY p.codigoempleado ORDER BY c.orden) AS rn
                    FROM preTotalesProrrateo p
                    CROSS JOIN ConceptosActivos c
                ), ' + '
                ProrrateoRecursivo AS (
                    -- ANCLA
                    SELECT
                        b.codigoempleado,
                        b.concepto,
                        b.orden,
                        b.tope,
                        b.rn,
                        CAST(b.netoAPagar AS DECIMAL(18,2)) AS saldo_antes,
                        CAST(
                            CASE
                                WHEN b.netoAPagar <= 0 THEN 0
                                WHEN b.tope IS NULL OR b.netoAPagar >= b.tope THEN ISNULL(b.tope, b.netoAPagar)
                                ELSE b.netoAPagar
                            END
                        AS DECIMAL(18,2)) AS monto_asignado,
                        CAST(
                            b.netoAPagar -
                            CASE
                                WHEN b.netoAPagar <= 0 THEN 0
                                WHEN b.tope IS NULL OR b.netoAPagar >= b.tope THEN ISNULL(b.tope, b.netoAPagar)
                                ELSE b.netoAPagar
                            END
                        AS DECIMAL(18,2)) AS saldo_despues
                    FROM BaseProrrateo b
                    WHERE b.rn = 1

                    UNION ALL

                    -- RECURSIVO
                    SELECT
                        b.codigoempleado,
                        b.concepto,
                        b.orden,
                        b.tope,
                        b.rn,
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
                ), ' + '
                PercepcionesExcedentes AS (
                    SELECT
                        codigoempleado,
                        SUM(monto_asignado) AS total_percepciones_excedente
                    FROM ProrrateoRecursivo
                    GROUP BY codigoempleado
                ), ' + '
                TotalesExcedentes AS (
                    SELECT
                        pe.codigoempleado,
                        pe.total_percepciones_excedente,
                        ISNULL(td.total_deducciones_exc, 0) AS total_deducciones_exc
                    FROM PercepcionesExcedentes pe
                    LEFT JOIN TotalesPorEmpleadoDeduccionesExcedentes td
                        ON pe.codigoempleado = td.codigoempleado
                ), ' + '
                NetoExcedentes AS (
                    SELECT
                        te.codigoempleado,
                        te.total_percepciones_excedente,
                        te.total_deducciones_exc,
                        te.total_percepciones_excedente - te.total_deducciones_exc AS neto_excedente
                    FROM TotalesExcedentes te
                ),' + '
                NetoTotalAPagar AS (
                    SELECT
                        ne.codigoempleado,
                        ne.total_percepciones_excedente,
                        ne.total_deducciones_exc,
                        ne.neto_excedente,
                        (ISNULL(pn.netoTotalAPagar,0) + ne.neto_excedente) AS neto_total_pagar
                    FROM NetoExcedentes ne
                    LEFT JOIN preNetoTotalAPagar pn
                        ON ne.codigoempleado = pn.codigoempleado
                ), ' + '
                pensionExcedente AS (
                    SELECT
                        gen.codigoempleado AS codigoempleado,
                        ''Pension Excedente'' AS concepto,
                        CASE WHEN gen.porcentajePension > 0 THEN ((gen.sueldo + gen.total_percepciones_sin_sueldo) * (gen.porcentajePension / 100)) - ((fis.total_percepciones) * (fis.porcentajePension / 100)) ELSE 0 END AS monto
                    FROM TotalesPorEmpleadoGeneralQ2 AS gen
					INNER JOIN TotalesPorEmpleadoQ3 fis ON gen.codigoempleado = fis.codigoempleado
                ) , ' + '
                ProrrateoFinal AS (
                    -- conceptos del prorrateo (columnas dinámicas)
                    SELECT
                        codigoempleado,
                        concepto,
                        monto_asignado
                    FROM ProrrateoRecursivo

                    UNION ALL
                    SELECT
                        ms.codigoempleado,
                        ms.descripcion AS columna,
                        ms.monto AS valor
                    FROM MovimientosSumaExcedente ms

                    UNION ALL

                    -- TOTAL PERCEPCION EXCEDENTE
                    SELECT
                        codigoempleado,
                        ''TOTAL PERCEPCION EXCEDENTE'' AS concepto,
                        total_percepciones_excedente AS monto_asignado
                    FROM NetoTotalAPagar

                    UNION ALL

                    -- PENSION EXCEDENTE
                    SELECT
                        pe.codigoempleado AS codigoempleado,
                        pe.concepto AS concepto,
                        pe.monto AS monto
                    FROM pensionExcedente pe

                    UNION ALL

                    -- TOTAL DEDUCCION EXCEDENTE
                    SELECT
                        nta.codigoempleado,
                        ''TOTAL DEDUCCION EXCEDENTE'' AS concepto,
                        (nta.total_deducciones_exc + pe.monto )AS monto_asignado
                    FROM NetoTotalAPagar AS nta
                    INNER JOIN pensionExcedente AS pe ON nta.codigoempleado = pe.codigoempleado

                    UNION ALL

                    -- NETO EXCEDENTE
                    SELECT
                        nta.codigoempleado,
                        ''NETO EXCEDENTE'' AS concepto,
                        nta.neto_excedente - pe.monto  AS monto_asignado
                    FROM NetoTotalAPagar AS nta
                    INNER JOIN pensionExcedente AS pe ON nta.codigoempleado = pe.codigoempleado

                    UNION ALL

                   -- NETO TOTAL A PAGAR
                    SELECT
                        nta.codigoempleado,
                        ''NETO TOTAL A PAGAR'' AS concepto,
                        (nta.neto_total_pagar - pe.monto) - ((fis.total_percepciones) * (fis.porcentajePension / 100)) AS monto_asignado
                    FROM NetoTotalAPagar AS nta
                     INNER JOIN pensionExcedente AS pe ON nta.codigoempleado = pe.codigoempleado
                     INNER JOIN TotalesPorEmpleadoQ3 AS fis ON nta.codigoempleado = fis.codigoempleado
                ) ' + '
                SELECT codigoempleado, ' + @cols + '
                FROM ProrrateoFinal
                PIVOT (
                    SUM(monto_asignado)
                    FOR concepto IN (' + @cols + ')
                ) p
                ORDER BY codigoempleado;
                ';

                EXEC(@sql);
            ";

            $result = DB::connection('sqlsrv_dynamic')->select($sql);

            return $result;
        } catch (\Exception $e) {
            return [];
        }
    }

    public function datosQuery5($request = null)
    {
        try {
            $idNominaGapeEmpresa = $request->id_nomina_gape_empresa;
            $idTipoPeriodo = $request->id_tipo_periodo;
            $idPeriodo = $request->periodo_inicial;

            $idEmpleadoInicial = $request->empleado_inicial;
            $idEmpleadoFinal = $request->empleado_final;

            $conexion = $this->helperController->getConexionDatabaseNGE($idNominaGapeEmpresa, 'Nom');

            $this->helperController->setDatabaseConnection($conexion, $conexion->nombre_base);

            $sql = "
                DECLARE @cols NVARCHAR(MAX),
                        @sql  NVARCHAR(MAX);
                ;WITH
                titulos AS (
                    SELECT * FROM (VALUES
                        ('Aguinaldo', 'P', 10, 1),
                        ('Prima Vacacional', 'P', 10, 1),
                        ('TOTAL PROVISIONES', 'P', 1000, 100),
                        ('COMPENSACION',  'N', 2000, 299)
                    ) AS X(descripcion, tipoconcepto, numeroconcepto, orden)
                )

                SELECT @cols = STUFF((
                    SELECT ', ' + QUOTENAME(descripcion)
                    FROM titulos
                    ORDER BY orden
                    FOR XML PATH(''), TYPE
                ).value('.', 'NVARCHAR(MAX)'), 1, 2, '');

                SET @sql = CAST('' AS NVARCHAR(MAX)) + '
                DECLARE @idPeriodo INT;
                DECLARE @idTipoPeriodo INT;

                DECLARE @idEmpleadoInicial INT;
                DECLARE @idEmpleadoFinal INT;

                DECLARE @idNominaGapeEmpresa INT;

                SET @idPeriodo = $idPeriodo;
                SET @idTipoPeriodo = $idTipoPeriodo;

                SET @idEmpleadoInicial = $idEmpleadoInicial;
                SET @idEmpleadoFinal = $idEmpleadoFinal;

                SET @idNominaGapeEmpresa = $idNominaGapeEmpresa;

                ;WITH
                    Movimientos AS (
                        SELECT idempleado, idperiodo, his.idconcepto, valor, importetotal, con.descripcion, con.tipoconcepto
                        FROM Nom10007 AS his
                        INNER JOIN nom10004 con
                            ON his.idconcepto = con.idconcepto
                        WHERE importetotal > 0
                            AND idperiodo = @idPeriodo
                            AND con.tipoconcepto IN (''P'',''D'')
                        UNION ALL
                        SELECT idempleado, idperiodo, actual.idconcepto, valor, importetotal, con.descripcion, con.tipoconcepto
                        FROM Nom10008 AS actual
                        INNER JOIN nom10004 con
                            ON actual.idconcepto = con.idconcepto
                        WHERE importetotal > 0
                            AND idperiodo = @idPeriodo
                            AND con.tipoconcepto IN (''P'',''D'')
                    ), ' + '
                    MovimientosSumaQ2 AS (
                        SELECT
                            emp.codigoempleado,
                            (emp.ccampoextranumerico2 * empPeriodo.cdiaspagados) AS sueldo,
                            pdo.descripcion,
                            pdo.tipoconcepto AS tipoConcepto,
                            SUM(pdo.valor) AS cantidad,
                            SUM(pdo.importetotal) AS monto,
                            emp.ccampoextranumerico3 AS pension
                        FROM nom10001 emp
                        INNER JOIN nom10034 empPeriodo
                            ON emp.idempleado = empPeriodo.idempleado
                                AND empPeriodo.cidperiodo = @idPeriodo
                        INNER JOIN Movimientos pdo
                            ON empPeriodo.cidperiodo = pdo.idperiodo
                            AND emp.idempleado = pdo.idempleado
                        WHERE empPeriodo.idtipoperiodo = @idTipoPeriodo
                            AND pdo.descripcion NOT IN(''Sueldo'',''Pensión Alimenticia'', ''Pension Alimenticia'')
                            AND empPeriodo.estadoempleado IN (''A'', ''R'')
                            AND empPeriodo.idempleado BETWEEN @idEmpleadoInicial AND @idEmpleadoFinal
                        GROUP BY
                            emp.codigoempleado, emp.ccampoextranumerico2, empPeriodo.cdiaspagados, pdo.descripcion, pdo.tipoconcepto, emp.ccampoextranumerico3
                    ), ' + '
                    IncidenciasNormalizadasQ2 AS (
                        SELECT
                            emp.codigoempleado as codigoempleado,
                            x.descripcion AS descripcion,
                            ''P'' AS tipoConcepto,
                            x.valor AS valor,
                            emp.ccampoextranumerico3 AS pension
                        FROM [becma-core2].dbo.nomina_gape_incidencia AS ngi
                        INNER JOIN [becma-core2].dbo.nomina_gape_incidencia_detalle AS ngid
                            ON ngi.id = ngid.id_nomina_gape_incidencia
                        INNER JOIN nom10034 AS empP
                            ON ngid.id_empleado = empP.idempleado
                            AND empP.cidperiodo = @idPeriodo
                            AND empP.idtipoperiodo = @idTipoPeriodo
                            AND empP.idempleado BETWEEN @idEmpleadoInicial AND @idEmpleadoFinal
                            AND empP.estadoempleado IN (''A'', ''R'')
                        INNER JOIN nom10001 AS emp
                            ON empP.idempleado = emp.idempleado
                        CROSS APPLY (VALUES
                            (''Comisiones'',               ngid.comision),
                            (''Bono'',                   ngid.bono),
                            (''Horas Extra Doble cantidad'',   ngid.horas_extra_doble_cantidad),
                            (''Horas Extra Doble monto'',      ngid.horas_extra_doble),
                            (''Horas Extra Triple cantidad'',  ngid.horas_extra_triple_cantidad),
                            (''Horas Extra Triple monto'',     ngid.horas_extra_triple),
                            (''Pago adicional'',         ngid.pago_adicional),
                            (''Premio puntualidad'',     ngid.premio_puntualidad)
                        ) AS x(descripcion, valor)
                        WHERE ngi.id_tipo_periodo = @idTipoPeriodo
                            AND ngi.id_nomina_gape_empresa = @idNominaGapeEmpresa
                            AND ngid.id_empleado BETWEEN @idEmpleadoInicial AND @idEmpleadoFinal
                    ), ' + '
                    TotalesPorEmpleadoGeneralQ2 AS (
                        SELECT
                            codigoempleado,
                            MAX(sueldo) AS sueldo,
                            SUM(CASE WHEN tipoConcepto = ''P'' THEN monto ELSE 0 END)
                            +
                            SUM(CASE WHEN tipoConcepto = ''P'' THEN valor ELSE 0 END) AS total_percepciones_sin_sueldo,
                            SUM(CASE WHEN tipoConcepto = ''D'' THEN monto ELSE 0 END) AS total_deducciones,
                            pension AS porcentajePension

                        FROM (
                            SELECT
                                codigoempleado,
                                sueldo,
                                tipoConcepto,
                                monto AS monto,
                                0 AS valor,
                                pension
                            FROM MovimientosSumaQ2
                            UNION ALL
                            SELECT
                                codigoempleado,
                                0 AS sueldo,
                                ''P'' AS tipoConcepto,
                                0 AS monto,
                                valor AS valor,
                                pension
                            FROM IncidenciasNormalizadasQ2
                        ) AS x
                        GROUP BY codigoempleado, pension
                    ),
                    MovimientosSumaQ3 AS (
                            SELECT
                                emp.codigoempleado,
                                empPeriodo.sueldodiario AS sd,
                                empPeriodo.sueldointegrado AS sdi,
                                pdo.descripcion,
                                pdo.tipoconcepto AS tipoConcepto,
                                SUM(pdo.importetotal) AS monto,
                                emp.ccampoextranumerico3 AS pension
                            FROM nom10001 emp
                            INNER JOIN nom10034 empPeriodo
                                ON emp.idempleado = empPeriodo.idempleado
                                AND empPeriodo.cidperiodo = @idPeriodo
                            INNER JOIN Movimientos pdo
                                ON empPeriodo.cidperiodo = pdo.idperiodo
                                AND emp.idempleado = pdo.idempleado
                            WHERE empPeriodo.idtipoperiodo = @idTipoPeriodo
                                AND pdo.descripcion NOT IN (''Pensión Alimenticia'', ''Pension Alimenticia'')
                                AND empPeriodo.estadoempleado IN (''A'', ''R'')
                                AND empPeriodo.idempleado BETWEEN @idEmpleadoInicial AND @idEmpleadoFinal
                            GROUP BY
                                emp.codigoempleado, empPeriodo.sueldodiario, empPeriodo.sueldointegrado, pdo.descripcion, pdo.tipoconcepto, emp.ccampoextranumerico3
                    ), ' + '
                    TotalesPorEmpleadoQ3 AS (
                            SELECT
                                codigoempleado,
                                sd,
                                sdi,
                                SUM(CASE WHEN tipoConcepto = ''P'' THEN monto ELSE 0 END) AS total_percepciones,
                                SUM(CASE WHEN tipoConcepto = ''D'' THEN monto ELSE 0 END) AS total_deducciones,
                                pension AS porcentajePension
                            FROM MovimientosSumaQ3
                            GROUP BY codigoempleado, pension, sdi, sd
                    ), ' + ' -------- se omite esto por el momento porque no se sabe cuales son las deducciones excedentes
                    MovimientosSumaExcedente AS (
                        SELECT
                            emp.codigoempleado,
                            pdo.descripcion,
                            SUM(pdo.importeTotal) AS monto
                        FROM nom10001 emp
                        INNER JOIN nom10034 empPeriodo
                            ON emp.idempleado = empPeriodo.idempleado
                            AND empPeriodo.cidperiodo = @idPeriodo
                        INNER JOIN Movimientos pdo
                            ON empPeriodo.cidperiodo = pdo.idperiodo
                            AND emp.idempleado = pdo.idempleado
                        WHERE empPeriodo.idtipoperiodo = @idTipoPeriodo
                            AND pdo.tipoconcepto IN (''P'')
                            AND pdo.descripcion NOT IN (''Pensión Alimenticia'', ''Pension Alimenticia'')
                            AND empPeriodo.estadoempleado IN (''A'', ''R'')
                            AND empPeriodo.idempleado BETWEEN @idEmpleadoInicial AND @idEmpleadoFinal
                        GROUP BY
                            emp.codigoempleado, empPeriodo.sueldodiario, empPeriodo.sueldointegrado, pdo.descripcion
                    ), ' + ' -------- se omite esto por el momento porque no se sabe cuales son las deducciones excedentes
                    TotalesPorEmpleadoDeduccionesExcedentes AS (
                        SELECT
                            emp.codigoempleado,
                            (emp.ccampoextranumerico2 * empPeriodo.cdiaspagados) AS sueldo,
                            SUM(CASE WHEN pdo.tipoconcepto = ''D'' THEN pdo.importetotal ELSE 0 END) AS total_deducciones_exc
                        FROM nom10001 emp
                        INNER JOIN nom10034 empPeriodo
                            ON emp.idempleado = empPeriodo.idempleado
                            AND empPeriodo.cidperiodo = @idPeriodo
                        INNER JOIN Movimientos pdo
                            ON empPeriodo.cidperiodo = pdo.idperiodo
                            AND emp.idempleado = pdo.idempleado
                        WHERE empPeriodo.idtipoperiodo = @idTipoPeriodo
                            AND pdo.tipoconcepto IN (''P'')
                            AND pdo.descripcion NOT IN (''Pensión Alimenticia'', ''Pension Alimenticia'')
                            AND empPeriodo.estadoempleado IN (''A'', ''R'')
                            AND empPeriodo.idempleado BETWEEN @idEmpleadoInicial AND @idEmpleadoFinal
                        GROUP BY
                            emp.codigoempleado, emp.ccampoextranumerico2, empPeriodo.cdiaspagados
                    ), ' + '
                    TotalesPorEmpleado AS ( -- totales generales Query1
                        SELECT
                            emp.codigoempleado,
                            (emp.ccampoextranumerico2 * empPeriodo.cdiaspagados) AS sueldo,
                            SUM(CASE WHEN pdo.tipoconcepto = ''P'' THEN pdo.importetotal ELSE 0 END) AS total_percepciones_sin_sueldo,
                            SUM(CASE WHEN pdo.tipoconcepto = ''D'' THEN pdo.importetotal ELSE 0 END) AS total_deducciones
                        FROM nom10001 emp
                        INNER JOIN nom10034 empPeriodo
                            ON emp.idempleado = empPeriodo.idempleado
                            AND empPeriodo.cidperiodo = @idPeriodo
                        INNER JOIN Movimientos pdo
                            ON empPeriodo.cidperiodo = pdo.idperiodo
                            AND emp.idempleado = pdo.idempleado
                        WHERE empPeriodo.idtipoperiodo = @idTipoPeriodo
                            AND pdo.tipoconcepto IN (''P'',''D'')
                            AND pdo.descripcion NOT IN (''Sueldo'')
                            AND empPeriodo.idempleado BETWEEN @idEmpleadoInicial AND @idEmpleadoFinal
                        GROUP BY
                            emp.codigoempleado, emp.ccampoextranumerico2, empPeriodo.cdiaspagados
                    ), ' + '
                    TotalesPorEmpleadoFis AS ( -- totales P D sin pension Query3
                        SELECT
                            emp.codigoempleado,
                            empPeriodo.sueldodiario AS sd,
                            empPeriodo.sueldointegrado AS sdi,
                            SUM(CASE WHEN pdo.tipoconcepto = ''P'' THEN pdo.importetotal ELSE 0 END) AS total_percepciones,
                            SUM(CASE WHEN pdo.tipoconcepto = ''D'' THEN pdo.importetotal ELSE 0 END) AS total_deducciones
                        FROM nom10001 emp
                        INNER JOIN nom10034 empPeriodo
                            ON emp.idempleado = empPeriodo.idempleado
                            AND empPeriodo.cidperiodo = @idPeriodo
                        INNER JOIN Movimientos pdo
                            ON empPeriodo.cidperiodo = pdo.idperiodo
                            AND emp.idempleado = pdo.idempleado
                        WHERE empPeriodo.idtipoperiodo = @idTipoPeriodo
                            AND empPeriodo.idempleado BETWEEN @idEmpleadoInicial AND @idEmpleadoFinal
                        GROUP BY
                            emp.codigoempleado, empPeriodo.sueldodiario, empPeriodo.sueldointegrado
                    ), ' + '
                    preNetoTotalAPagar AS ( -- totales P - D Sin pension Query3
                        SELECT
                            tpf.codigoempleado,
                            (tpf.total_percepciones - tpf.total_deducciones) AS netoTotalAPagar
                        FROM TotalesPorEmpleadoFis tpf
                    ), ' + '
                    preTotalesProrrateo AS (
                        SELECT
                            q2.codigoempleado,
                            ((q2.sueldo + q2.total_percepciones_sin_sueldo) - (q2.total_deducciones + CASE WHEN q2.porcentajePension > 0 THEN (q2.sueldo + q2.total_percepciones_sin_sueldo) * (q2.porcentajePension / 100) ELSE 0 END))
                            -
                            ((q3.total_percepciones) - (q3.total_deducciones + CASE WHEN q3.porcentajePension > 0 THEN (q3.total_percepciones) * (q3.porcentajePension / 100) ELSE 0 END))
                            AS netoAPagar
                        FROM TotalesPorEmpleadoGeneralQ2 q2
                        INNER JOIN TotalesPorEmpleadoQ3 q3
                            ON q2.codigoempleado = q3.codigoempleado
                    ), ' + '
                    ParamConfig AS (
                        SELECT
                            sueldo_imss, sueldo_imss_tope, sueldo_imss_orden
                            , prev_social, prev_social_tope, prev_social_orden
                            , fondos_sind, fondos_sind_tope, fondos_sind_orden
                            , tarjeta_facil, tarjeta_facil_tope, tarjeta_facil_orden
                            , hon_asimilados, hon_asimilados_tope, hon_asimilados_orden
                            , gastos_compro, gastos_compro_tope, gastos_compro_orden
                        FROM [becma-core2].[dbo].[nomina_gape_concepto_pago_parametrizacion]
                        WHERE id_tipo_periodo = @idTipoPeriodo
                        AND id_nomina_gape_empresa =   @idNominaGapeEmpresa
                    ), ' + '
                    ConceptosParametrizados AS (
                        SELECT ''sueldo_imss'' AS concepto, sueldo_imss AS activo, sueldo_imss_tope AS tope, sueldo_imss_orden AS orden FROM ParamConfig
                        UNION ALL SELECT ''prev_social'',   prev_social,   prev_social_tope,   prev_social_orden   FROM ParamConfig
                        UNION ALL SELECT ''fondos_sind'',   fondos_sind,   fondos_sind_tope,   fondos_sind_orden   FROM ParamConfig
                        UNION ALL SELECT ''tarjeta_facil'', tarjeta_facil, tarjeta_facil_tope, tarjeta_facil_orden FROM ParamConfig
                        UNION ALL SELECT ''hon_asimilados'',hon_asimilados, hon_asimilados_tope, hon_asimilados_orden FROM ParamConfig
                        UNION ALL SELECT ''gastos_compro'', gastos_compro, gastos_compro_tope, gastos_compro_orden FROM ParamConfig
                    ), ' + '
                    ConceptosActivos AS (
                        SELECT concepto, CAST(tope AS DECIMAL(18,2)) AS tope, orden
                        FROM ConceptosParametrizados
                        WHERE activo = 1
                    ), ' + '
                    BaseProrrateo AS (
                        SELECT
                            p.codigoempleado,
                            CAST(p.netoAPagar AS DECIMAL(18,2)) AS netoAPagar,
                            c.concepto,
                            c.tope,
                            c.orden,
                            ROW_NUMBER() OVER (PARTITION BY p.codigoempleado ORDER BY c.orden) AS rn
                        FROM preTotalesProrrateo p
                        CROSS JOIN ConceptosActivos c
                    ), ' + '
                    ProrrateoRecursivo AS (
                        -- ANCLA
                        SELECT
                            b.codigoempleado,
                            b.concepto,
                            b.orden,
                            b.tope,
                            b.rn,
                            CAST(b.netoAPagar AS DECIMAL(18,2)) AS saldo_antes,
                            CAST(
                                CASE
                                    WHEN b.netoAPagar <= 0 THEN 0
                                    WHEN b.tope IS NULL OR b.netoAPagar >= b.tope THEN ISNULL(b.tope, b.netoAPagar)
                                    ELSE b.netoAPagar
                                END
                            AS DECIMAL(18,2)) AS monto_asignado,
                            CAST(
                                b.netoAPagar -
                                CASE
                                    WHEN b.netoAPagar <= 0 THEN 0
                                    WHEN b.tope IS NULL OR b.netoAPagar >= b.tope THEN ISNULL(b.tope, b.netoAPagar)
                                    ELSE b.netoAPagar
                                END
                            AS DECIMAL(18,2)) AS saldo_despues
                        FROM BaseProrrateo b
                        WHERE b.rn = 1

                        UNION ALL

                        -- RECURSIVO
                        SELECT
                            b.codigoempleado,
                            b.concepto,
                            b.orden,
                            b.tope,
                            b.rn,
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
                    ), ' + '
                    PercepcionesExcedentes AS (
                        SELECT
                            codigoempleado,
                            SUM(monto_asignado) AS total_percepciones_excedente
                        FROM ProrrateoRecursivo
                        GROUP BY codigoempleado
                    ), ' + '
                    TotalesExcedentes AS (
                        SELECT
                            pe.codigoempleado,
                            pe.total_percepciones_excedente,
                            ISNULL(td.total_deducciones_exc, 0) AS total_deducciones_exc
                        FROM PercepcionesExcedentes pe
                        LEFT JOIN TotalesPorEmpleadoDeduccionesExcedentes td
                            ON pe.codigoempleado = td.codigoempleado
                    ), ' + '
                    NetoExcedentes AS (
                        SELECT
                            te.codigoempleado,
                            te.total_percepciones_excedente,
                            te.total_deducciones_exc,
                            te.total_percepciones_excedente - te.total_deducciones_exc AS neto_excedente
                        FROM TotalesExcedentes te
                    ),' + '
                    NetoTotalAPagar AS (
                        SELECT
                            ne.codigoempleado,
                            ne.total_percepciones_excedente,
                            ne.total_deducciones_exc,
                            ne.neto_excedente,
                            (ISNULL(pn.netoTotalAPagar,0) + ne.neto_excedente) AS neto_total_pagar
                        FROM NetoExcedentes ne
                        LEFT JOIN preNetoTotalAPagar pn
                            ON ne.codigoempleado = pn.codigoempleado
                    ), ' + '
                    pensionExcedente AS (
                        SELECT
                            gen.codigoempleado AS codigoempleado,
                            ''Pension Excedente'' AS concepto,
                            CASE WHEN gen.porcentajePension > 0 THEN ((gen.sueldo + gen.total_percepciones_sin_sueldo) * (gen.porcentajePension / 100)) - ((fis.total_percepciones) * (fis.porcentajePension / 100)) ELSE 0 END AS monto
                        FROM TotalesPorEmpleadoGeneralQ2 AS gen
                        INNER JOIN TotalesPorEmpleadoQ3 fis ON gen.codigoempleado = fis.codigoempleado
                    ) , ' + '
                    provisiones AS (
                        SELECT
                            emp.codigoempleado AS codigoempleado,
                            (empPeriodo.sueldodiario * antig.DiasAguinaldo) / 365 * periodo.diasdepago AS provisionAguinaldo,
                            (empPeriodo.sueldodiario * antig.DiasVacaciones * (PorcentajePrima / 100.0)) / 365.0 * periodo.diasdepago AS provisionPrimaVacacional

                        FROM nom10001 emp
                        INNER JOIN nom10034 empPeriodo
                            ON emp.idempleado = empPeriodo.idempleado AND empPeriodo.cidperiodo = @idPeriodo
                        INNER JOIN nom10002 AS periodo
                            ON empPeriodo.cidperiodo = periodo.idperiodo AND periodo.idperiodo = @idPeriodo
                        INNER JOIN nom10050 AS tipoPres
                            ON empPeriodo.TipoPrestacion = tipoPres.IDTabla
                        CROSS APPLY (
                            SELECT TOP 1 *
                            FROM nom10051 antig
                            WHERE antig.IDTablaPrestacion = tipoPres.IDTabla
                                AND antig.Antiguedad =
                                    CASE
                                        WHEN FLOOR(DATEDIFF(day, empPeriodo.fechaalta, GETDATE()) / 365.25) = 0
                                            THEN 1
                                        ELSE FLOOR(DATEDIFF(day, empPeriodo.fechaalta, GETDATE()) / 365.25)
                                    END
                            ORDER BY antig.fechainicioVigencia DESC
                        ) AS antig
                        WHERE empPeriodo.cidperiodo = @idPeriodo
                        AND empPeriodo.estadoempleado IN (''A'', ''R'')
                        AND empPeriodo.idempleado BETWEEN @idEmpleadoInicial AND @idEmpleadoFinal
                    ), ' + '

                    nomGapeEmpresa AS (
                        SELECT
                            provisiones
                        FROM [becma-core2].dbo.nomina_gape_parametrizacion
                        WHERE id_nomina_gape_empresa = @idNominaGapeEmpresa
                            AND id_tipo_periodo = @idTipoPeriodo
                    ),
                    ProrrateoFinal AS (
                        SELECT
                            codigoempleado,
                            ''Aguinaldo'' AS concepto,
                            provisionAguinaldo AS monto
                        FROM provisiones
                        UNION ALL
                        SELECT
                            codigoempleado,
                            ''Prima Vacacional'' AS concepto,
                            provisionPrimaVacacional AS monto
                        FROM provisiones

                        UNION ALL

                        SELECT
                            codigoempleado,
                            ''TOTAL PROVISIONES'' AS concepto,
                            provisionPrimaVacacional + provisionAguinaldo AS monto
                        FROM provisiones

                        UNION ALL

                        SELECT
                        nta.codigoempleado,
                        ''COMPENSACION'' AS concepto,
                        ((nta.neto_total_pagar - pe.monto) - ((q3.total_percepciones) * (q3.porcentajePension / 100))
                        +
                        (q3.total_deducciones + CASE WHEN porcentajePension > 0 THEN (total_percepciones) * (porcentajePension / 100) ELSE 0 END)
                        +
                        (nta.total_deducciones_exc + pe.monto ))
                        +
                        CASE
                            WHEN nge.provisiones = ''si''
                            THEN (
                                SELECT
                                (prov.provisionPrimaVacacional + prov.provisionAguinaldo)
                                FROM provisiones AS prov
                                WHERE nta.codigoempleado = prov.codigoempleado
                            )
                            ELSE 0
                        END
                        AS monto_asignado
                        FROM NetoTotalAPagar AS nta
                        INNER JOIN pensionExcedente AS pe
                            ON nta.codigoempleado = pe.codigoempleado
                        INNER JOIN TotalesPorEmpleadoQ3 AS q3
                            ON nta.codigoempleado = q3.codigoempleado
                        CROSS JOIN nomGapeEmpresa nge
                    )
                    SELECT codigoempleado, ' + @cols + '
                    FROM ProrrateoFinal
                    PIVOT (
                        SUM(monto)
                        FOR concepto IN (' + @cols + ')
                    ) p
                    ORDER BY codigoempleado;
                    ';
                    EXEC(@sql);
            ";
            $result = DB::connection('sqlsrv_dynamic')->select($sql);

            return $result;
        } catch (\Exception $e) {
            return [];
        }
    }

    public function datosQuery6($request = null)
    {
        try {
            $idNominaGapeEmpresa = $request->id_nomina_gape_empresa;
            $idTipoPeriodo = $request->id_tipo_periodo;
            $idPeriodo = $request->periodo_inicial;

            $idEmpleadoInicial = $request->empleado_inicial;
            $idEmpleadoFinal = $request->empleado_final;

            $conexion = $this->helperController->getConexionDatabaseNGE($idNominaGapeEmpresa, 'Nom');

            $this->helperController->setDatabaseConnection($conexion, $conexion->nombre_base);

            $sql = "
                DECLARE @cols NVARCHAR(MAX),
                        @sql  NVARCHAR(MAX);
                ;WITH
                    titulos AS (
                        SELECT * FROM (VALUES
                            ('IMSS Patronal', 'O', 10, 1),
                            ('ISN', 'O', 10, 1),
                            ('Comision Servicios', 'N', 1000, 100),
                            ('Costo Total',  'N', 2000, 299)
                        ) AS X(descripcion, tipoconcepto, numeroconcepto, orden)
                    )

                    SELECT @cols = STUFF((
                        SELECT ', ' + QUOTENAME(descripcion)
                        FROM titulos
                        ORDER BY orden
                        FOR XML PATH(''), TYPE
                    ).value('.', 'NVARCHAR(MAX)'), 1, 2, '');

                SET @sql = CAST('' AS NVARCHAR(MAX)) + '
                DECLARE @idPeriodo INT;
                DECLARE @idTipoPeriodo INT;

                DECLARE @idEmpleadoInicial INT;
                DECLARE @idEmpleadoFinal INT;
                DECLARE @idNominaGapeEmpresa INT;

                SET @idPeriodo = $idPeriodo;
                SET @idTipoPeriodo = $idTipoPeriodo;

                SET @idEmpleadoInicial = $idEmpleadoInicial;
                SET @idEmpleadoFinal = $idEmpleadoFinal;
                SET @idNominaGapeEmpresa = $idNominaGapeEmpresa;

                ;WITH
					Movimientos AS (
						SELECT idempleado, idperiodo, his.idconcepto, valor, importetotal, con.descripcion, con.tipoconcepto
						FROM Nom10007 AS his
						INNER JOIN nom10004 con
							ON his.idconcepto = con.idconcepto
						WHERE importetotal > 0
							AND idperiodo = @idPeriodo
							AND con.tipoconcepto IN (''P'',''D'')
						UNION ALL
						SELECT idempleado, idperiodo, actual.idconcepto, valor, importetotal, con.descripcion, con.tipoconcepto
						FROM Nom10008 AS actual
						INNER JOIN nom10004 con
							ON actual.idconcepto = con.idconcepto
						WHERE importetotal > 0
							AND idperiodo = @idPeriodo
							AND con.tipoconcepto IN (''P'',''D'')
					), ' + '
					MovimientosSumaQ2 AS (
						SELECT
							emp.codigoempleado,
							(emp.ccampoextranumerico2 * empPeriodo.cdiaspagados) AS sueldo,
							pdo.descripcion,
							pdo.tipoconcepto AS tipoConcepto,
							SUM(pdo.valor) AS cantidad,
							SUM(pdo.importetotal) AS monto,
							emp.ccampoextranumerico3 AS pension
						FROM nom10001 emp
						INNER JOIN nom10034 empPeriodo
							ON emp.idempleado = empPeriodo.idempleado
								AND empPeriodo.cidperiodo = @idPeriodo
						INNER JOIN Movimientos pdo
							ON empPeriodo.cidperiodo = pdo.idperiodo
							AND emp.idempleado = pdo.idempleado
						WHERE empPeriodo.idtipoperiodo = @idTipoPeriodo
							AND pdo.descripcion NOT IN(''Sueldo'',''Pensión Alimenticia'', ''Pension Alimenticia'')
							AND empPeriodo.estadoempleado IN (''A'', ''R'')
							AND empPeriodo.idempleado BETWEEN @idEmpleadoInicial AND @idEmpleadoFinal
						GROUP BY
							emp.codigoempleado, emp.ccampoextranumerico2, empPeriodo.cdiaspagados, pdo.descripcion, pdo.tipoconcepto, emp.ccampoextranumerico3
					), ' + '
					IncidenciasNormalizadasQ2 AS (
						SELECT
							emp.codigoempleado as codigoempleado,
							x.descripcion AS descripcion,
							''P'' AS tipoConcepto,
							x.valor AS valor,
							emp.ccampoextranumerico3 AS pension
						FROM [becma-core2].dbo.nomina_gape_incidencia AS ngi
						INNER JOIN [becma-core2].dbo.nomina_gape_incidencia_detalle AS ngid
							ON ngi.id = ngid.id_nomina_gape_incidencia
						INNER JOIN nom10034 AS empP
							ON ngid.id_empleado = empP.idempleado
							AND empP.cidperiodo = @idPeriodo
							AND empP.idtipoperiodo = @idTipoPeriodo
							AND empP.idempleado BETWEEN @idEmpleadoInicial AND @idEmpleadoFinal
							AND empP.estadoempleado IN (''A'', ''R'')
						INNER JOIN nom10001 AS emp
							ON empP.idempleado = emp.idempleado
						CROSS APPLY (VALUES
							(''Comisiones'',               ngid.comision),
							(''Bono'',                   ngid.bono),
							(''Horas Extra Doble cantidad'',   ngid.horas_extra_doble_cantidad),
							(''Horas Extra Doble monto'',      ngid.horas_extra_doble),
							(''Horas Extra Triple cantidad'',  ngid.horas_extra_triple_cantidad),
							(''Horas Extra Triple monto'',     ngid.horas_extra_triple),
							(''Pago adicional'',         ngid.pago_adicional),
							(''Premio puntualidad'',     ngid.premio_puntualidad)
						) AS x(descripcion, valor)
						WHERE ngi.id_tipo_periodo = @idTipoPeriodo
							AND ngi.id_nomina_gape_empresa = @idNominaGapeEmpresa
							AND ngid.id_empleado BETWEEN @idEmpleadoInicial AND @idEmpleadoFinal
					), ' + '
					TotalesPorEmpleadoGeneralQ2 AS (
						SELECT
							codigoempleado,
							MAX(sueldo) AS sueldo,
							SUM(CASE WHEN tipoConcepto = ''P'' THEN monto ELSE 0 END)
							+
							SUM(CASE WHEN tipoConcepto = ''P'' THEN valor ELSE 0 END) AS total_percepciones_sin_sueldo,
							SUM(CASE WHEN tipoConcepto = ''D'' THEN monto ELSE 0 END) AS total_deducciones,
							pension AS porcentajePension

						FROM (
							SELECT
								codigoempleado,
								sueldo,
								tipoConcepto,
								monto AS monto,
								0 AS valor,
								pension
							FROM MovimientosSumaQ2
							UNION ALL
							SELECT
								codigoempleado,
								0 AS sueldo,
								''P'' AS tipoConcepto,
								0 AS monto,
								valor AS valor,
								pension
							FROM IncidenciasNormalizadasQ2
						) AS x
						GROUP BY codigoempleado, pension
					),
					MovimientosSumaQ3 AS (
							SELECT
								emp.codigoempleado,
								empPeriodo.sueldodiario AS sd,
								empPeriodo.sueldointegrado AS sdi,
								pdo.descripcion,
								pdo.tipoconcepto AS tipoConcepto,
								SUM(pdo.importetotal) AS monto,
								emp.ccampoextranumerico3 AS pension
							FROM nom10001 emp
							INNER JOIN nom10034 empPeriodo
								ON emp.idempleado = empPeriodo.idempleado
								AND empPeriodo.cidperiodo = @idPeriodo
							INNER JOIN Movimientos pdo
								ON empPeriodo.cidperiodo = pdo.idperiodo
								AND emp.idempleado = pdo.idempleado
							WHERE empPeriodo.idtipoperiodo = @idTipoPeriodo
								AND pdo.descripcion NOT IN (''Pensión Alimenticia'', ''Pension Alimenticia'')
								AND empPeriodo.estadoempleado IN (''A'', ''R'')
								AND empPeriodo.idempleado BETWEEN @idEmpleadoInicial AND @idEmpleadoFinal
							GROUP BY
								emp.codigoempleado, empPeriodo.sueldodiario, empPeriodo.sueldointegrado, pdo.descripcion, pdo.tipoconcepto, emp.ccampoextranumerico3
					), ' + '
					TotalesPorEmpleadoQ3 AS (
							SELECT
								codigoempleado,
								sd,
								sdi,
								SUM(CASE WHEN tipoConcepto = ''P'' THEN monto ELSE 0 END) AS total_percepciones,
								SUM(CASE WHEN tipoConcepto = ''D'' THEN monto ELSE 0 END) AS total_deducciones,
								pension AS porcentajePension
							FROM MovimientosSumaQ3
							GROUP BY codigoempleado, pension, sdi, sd
					), ' + ' -------- se omite esto por el momento porque no se sabe cuales son las deducciones excedentes
					MovimientosSumaExcedente AS (
						SELECT
							emp.codigoempleado,
							pdo.descripcion,
							SUM(pdo.importeTotal) AS monto
						FROM nom10001 emp
						INNER JOIN nom10034 empPeriodo
							ON emp.idempleado = empPeriodo.idempleado
							AND empPeriodo.cidperiodo = @idPeriodo
						INNER JOIN Movimientos pdo
							ON empPeriodo.cidperiodo = pdo.idperiodo
							AND emp.idempleado = pdo.idempleado
						WHERE empPeriodo.idtipoperiodo = @idTipoPeriodo
							AND pdo.tipoconcepto IN (''P'')
							AND pdo.descripcion NOT IN (''Pensión Alimenticia'', ''Pension Alimenticia'')
							AND empPeriodo.estadoempleado IN (''A'', ''R'')
							AND empPeriodo.idempleado BETWEEN @idEmpleadoInicial AND @idEmpleadoFinal
						GROUP BY
							emp.codigoempleado, empPeriodo.sueldodiario, empPeriodo.sueldointegrado, pdo.descripcion
					), ' + ' -------- se omite esto por el momento porque no se sabe cuales son las deducciones excedentes
					TotalesPorEmpleadoDeduccionesExcedentes AS (
						SELECT
							emp.codigoempleado,
							(emp.ccampoextranumerico2 * empPeriodo.cdiaspagados) AS sueldo,
							SUM(CASE WHEN pdo.tipoconcepto = ''D'' THEN pdo.importetotal ELSE 0 END) AS total_deducciones_exc
						FROM nom10001 emp
						INNER JOIN nom10034 empPeriodo
							ON emp.idempleado = empPeriodo.idempleado
							AND empPeriodo.cidperiodo = @idPeriodo
						INNER JOIN Movimientos pdo
							ON empPeriodo.cidperiodo = pdo.idperiodo
							AND emp.idempleado = pdo.idempleado
						WHERE empPeriodo.idtipoperiodo = @idTipoPeriodo
							AND pdo.tipoconcepto IN (''P'')
							AND pdo.descripcion NOT IN (''Pensión Alimenticia'', ''Pension Alimenticia'')
							AND empPeriodo.estadoempleado IN (''A'', ''R'')
							AND empPeriodo.idempleado BETWEEN @idEmpleadoInicial AND @idEmpleadoFinal
						GROUP BY
							emp.codigoempleado, emp.ccampoextranumerico2, empPeriodo.cdiaspagados
					), ' + '
					TotalesPorEmpleado AS ( -- totales generales Query1
						SELECT
							emp.codigoempleado,
							(emp.ccampoextranumerico2 * empPeriodo.cdiaspagados) AS sueldo,
							SUM(CASE WHEN pdo.tipoconcepto = ''P'' THEN pdo.importetotal ELSE 0 END) AS total_percepciones_sin_sueldo,
							SUM(CASE WHEN pdo.tipoconcepto = ''D'' THEN pdo.importetotal ELSE 0 END) AS total_deducciones
						FROM nom10001 emp
						INNER JOIN nom10034 empPeriodo
							ON emp.idempleado = empPeriodo.idempleado
							AND empPeriodo.cidperiodo = @idPeriodo
						INNER JOIN Movimientos pdo
							ON empPeriodo.cidperiodo = pdo.idperiodo
							AND emp.idempleado = pdo.idempleado
						WHERE empPeriodo.idtipoperiodo = @idTipoPeriodo
							AND pdo.tipoconcepto IN (''P'',''D'')
							AND pdo.descripcion NOT IN (''Sueldo'')
							AND empPeriodo.idempleado BETWEEN @idEmpleadoInicial AND @idEmpleadoFinal
						GROUP BY
							emp.codigoempleado, emp.ccampoextranumerico2, empPeriodo.cdiaspagados
					), ' + '
					TotalesPorEmpleadoFis AS ( -- totales P D sin pension Query3
						SELECT
							emp.codigoempleado,
							empPeriodo.sueldodiario AS sd,
							empPeriodo.sueldointegrado AS sdi,
							SUM(CASE WHEN pdo.tipoconcepto = ''P'' THEN pdo.importetotal ELSE 0 END) AS total_percepciones,
							SUM(CASE WHEN pdo.tipoconcepto = ''D'' THEN pdo.importetotal ELSE 0 END) AS total_deducciones
						FROM nom10001 emp
						INNER JOIN nom10034 empPeriodo
							ON emp.idempleado = empPeriodo.idempleado
							AND empPeriodo.cidperiodo = @idPeriodo
						INNER JOIN Movimientos pdo
							ON empPeriodo.cidperiodo = pdo.idperiodo
							AND emp.idempleado = pdo.idempleado
						WHERE empPeriodo.idtipoperiodo = @idTipoPeriodo
							AND empPeriodo.idempleado BETWEEN @idEmpleadoInicial AND @idEmpleadoFinal
						GROUP BY
							emp.codigoempleado, empPeriodo.sueldodiario, empPeriodo.sueldointegrado
					), ' + '
					preNetoTotalAPagar AS ( -- totales P - D Sin pension Query3
						SELECT
							tpf.codigoempleado,
							(tpf.total_percepciones - tpf.total_deducciones) AS netoTotalAPagar
						FROM TotalesPorEmpleadoFis tpf
					), ' + '
					preTotalesProrrateo AS (
						SELECT
							q2.codigoempleado,
							((q2.sueldo + q2.total_percepciones_sin_sueldo) - (q2.total_deducciones + CASE WHEN q2.porcentajePension > 0 THEN (q2.sueldo + q2.total_percepciones_sin_sueldo) * (q2.porcentajePension / 100) ELSE 0 END))
							-
							((q3.total_percepciones) - (q3.total_deducciones + CASE WHEN q3.porcentajePension > 0 THEN (q3.total_percepciones) * (q3.porcentajePension / 100) ELSE 0 END))
							AS netoAPagar
						FROM TotalesPorEmpleadoGeneralQ2 q2
						INNER JOIN TotalesPorEmpleadoQ3 q3
							ON q2.codigoempleado = q3.codigoempleado
					), ' + '
					ParamConfig AS (
						SELECT
							sueldo_imss, sueldo_imss_tope, sueldo_imss_orden
							, prev_social, prev_social_tope, prev_social_orden
							, fondos_sind, fondos_sind_tope, fondos_sind_orden
							, tarjeta_facil, tarjeta_facil_tope, tarjeta_facil_orden
							, hon_asimilados, hon_asimilados_tope, hon_asimilados_orden
							, gastos_compro, gastos_compro_tope, gastos_compro_orden
						FROM [becma-core2].[dbo].[nomina_gape_concepto_pago_parametrizacion]
						WHERE id_tipo_periodo = @idTipoPeriodo
						AND id_nomina_gape_empresa =   @idNominaGapeEmpresa
					), ' + '
					ConceptosParametrizados AS (
						SELECT ''sueldo_imss'' AS concepto, sueldo_imss AS activo, sueldo_imss_tope AS tope, sueldo_imss_orden AS orden FROM ParamConfig
						UNION ALL SELECT ''prev_social'',   prev_social,   prev_social_tope,   prev_social_orden   FROM ParamConfig
						UNION ALL SELECT ''fondos_sind'',   fondos_sind,   fondos_sind_tope,   fondos_sind_orden   FROM ParamConfig
						UNION ALL SELECT ''tarjeta_facil'', tarjeta_facil, tarjeta_facil_tope, tarjeta_facil_orden FROM ParamConfig
						UNION ALL SELECT ''hon_asimilados'',hon_asimilados, hon_asimilados_tope, hon_asimilados_orden FROM ParamConfig
						UNION ALL SELECT ''gastos_compro'', gastos_compro, gastos_compro_tope, gastos_compro_orden FROM ParamConfig
					), ' + '
					ConceptosActivos AS (
						SELECT concepto, CAST(tope AS DECIMAL(18,2)) AS tope, orden
						FROM ConceptosParametrizados
						WHERE activo = 1
					), ' + '
					BaseProrrateo AS (
						SELECT
							p.codigoempleado,
							CAST(p.netoAPagar AS DECIMAL(18,2)) AS netoAPagar,
							c.concepto,
							c.tope,
							c.orden,
							ROW_NUMBER() OVER (PARTITION BY p.codigoempleado ORDER BY c.orden) AS rn
						FROM preTotalesProrrateo p
						CROSS JOIN ConceptosActivos c
					), ' + '
					ProrrateoRecursivo AS (
						-- ANCLA
						SELECT
							b.codigoempleado,
							b.concepto,
							b.orden,
							b.tope,
							b.rn,
							CAST(b.netoAPagar AS DECIMAL(18,2)) AS saldo_antes,
							CAST(
								CASE
									WHEN b.netoAPagar <= 0 THEN 0
									WHEN b.tope IS NULL OR b.netoAPagar >= b.tope THEN ISNULL(b.tope, b.netoAPagar)
									ELSE b.netoAPagar
								END
							AS DECIMAL(18,2)) AS monto_asignado,
							CAST(
								b.netoAPagar -
								CASE
									WHEN b.netoAPagar <= 0 THEN 0
									WHEN b.tope IS NULL OR b.netoAPagar >= b.tope THEN ISNULL(b.tope, b.netoAPagar)
									ELSE b.netoAPagar
								END
							AS DECIMAL(18,2)) AS saldo_despues
						FROM BaseProrrateo b
						WHERE b.rn = 1

						UNION ALL

						-- RECURSIVO
						SELECT
							b.codigoempleado,
							b.concepto,
							b.orden,
							b.tope,
							b.rn,
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
					), ' + '
					PercepcionesExcedentes AS (
						SELECT
							codigoempleado,
							SUM(monto_asignado) AS total_percepciones_excedente
						FROM ProrrateoRecursivo
						GROUP BY codigoempleado
					), ' + '
					TotalesExcedentes AS (
						SELECT
							pe.codigoempleado,
							pe.total_percepciones_excedente,
							ISNULL(td.total_deducciones_exc, 0) AS total_deducciones_exc
						FROM PercepcionesExcedentes pe
						LEFT JOIN TotalesPorEmpleadoDeduccionesExcedentes td
							ON pe.codigoempleado = td.codigoempleado
					), ' + '
					NetoExcedentes AS (
						SELECT
							te.codigoempleado,
							te.total_percepciones_excedente,
							te.total_deducciones_exc,
							te.total_percepciones_excedente - te.total_deducciones_exc AS neto_excedente
						FROM TotalesExcedentes te
					),' + '
					NetoTotalAPagar AS (
						SELECT
							ne.codigoempleado,
							ne.total_percepciones_excedente,
							ne.total_deducciones_exc,
							ne.neto_excedente,
							(ISNULL(pn.netoTotalAPagar,0) + ne.neto_excedente) AS neto_total_pagar
						FROM NetoExcedentes ne
						LEFT JOIN preNetoTotalAPagar pn
							ON ne.codigoempleado = pn.codigoempleado
					), ' + '
					pensionExcedente AS (
						SELECT
							gen.codigoempleado AS codigoempleado,
							''Pension Excedente'' AS concepto,
							CASE WHEN gen.porcentajePension > 0 THEN ((gen.sueldo + gen.total_percepciones_sin_sueldo) * (gen.porcentajePension / 100)) - ((fis.total_percepciones) * (fis.porcentajePension / 100)) ELSE 0 END AS monto
						FROM TotalesPorEmpleadoGeneralQ2 AS gen
						INNER JOIN TotalesPorEmpleadoQ3 fis ON gen.codigoempleado = fis.codigoempleado
					) , ' + '
					provisiones AS (
						SELECT
							emp.codigoempleado AS codigoempleado,
							(empPeriodo.sueldodiario * antig.DiasAguinaldo) / 365 * periodo.diasdepago AS provisionAguinaldo,
							(empPeriodo.sueldodiario * antig.DiasVacaciones * (PorcentajePrima / 100.0)) / 365.0 * periodo.diasdepago AS provisionPrimaVacacional

						FROM nom10001 emp
						INNER JOIN nom10034 empPeriodo
							ON emp.idempleado = empPeriodo.idempleado AND empPeriodo.cidperiodo = @idPeriodo
						INNER JOIN nom10002 AS periodo
							ON empPeriodo.cidperiodo = periodo.idperiodo AND periodo.idperiodo = @idPeriodo
						INNER JOIN nom10050 AS tipoPres
							ON empPeriodo.TipoPrestacion = tipoPres.IDTabla
						CROSS APPLY (
							SELECT TOP 1 *
							FROM nom10051 antig
							WHERE antig.IDTablaPrestacion = tipoPres.IDTabla
								AND antig.Antiguedad =
									CASE
										WHEN FLOOR(DATEDIFF(day, empPeriodo.fechaalta, GETDATE()) / 365.25) = 0
											THEN 1
										ELSE FLOOR(DATEDIFF(day, empPeriodo.fechaalta, GETDATE()) / 365.25)
									END
							ORDER BY antig.fechainicioVigencia DESC
						) AS antig
						WHERE empPeriodo.cidperiodo = @idPeriodo
						AND empPeriodo.estadoempleado IN (''A'', ''R'')
						AND empPeriodo.idempleado BETWEEN @idEmpleadoInicial AND @idEmpleadoFinal
					), ' + '

                    nomGapeEmpresa AS (
                        SELECT
                            provisiones,
                            fee
                        FROM [becma-core2].dbo.nomina_gape_parametrizacion
                        WHERE id_nomina_gape_empresa = @idNominaGapeEmpresa
                            AND id_tipo_periodo = @idTipoPeriodo
                    ),
                    totalCompensacion AS (
                        SELECT
                            nta.codigoempleado,
                            nge.fee AS  fee,
                            ((nta.neto_total_pagar - pe.monto) - ((q3.total_percepciones) * (q3.porcentajePension / 100))
							+
							(q3.total_deducciones + CASE WHEN porcentajePension > 0 THEN (total_percepciones) * (porcentajePension / 100) ELSE 0 END)
							+
							(nta.total_deducciones_exc + pe.monto ))
							+
							CASE
								WHEN nge.provisiones = ''si''
								THEN (
									SELECT
									(prov.provisionPrimaVacacional + prov.provisionAguinaldo)
									FROM provisiones AS prov
									WHERE nta.codigoempleado = prov.codigoempleado
								)
								ELSE 0
							END
							AS monto_asignado
							FROM NetoTotalAPagar AS nta
							INNER JOIN pensionExcedente AS pe
								ON nta.codigoempleado = pe.codigoempleado
							INNER JOIN TotalesPorEmpleadoQ3 AS q3
								ON nta.codigoempleado = q3.codigoempleado
							CROSS JOIN nomGapeEmpresa nge
                    ), ' + '
                    MovimientosO AS (
                        SELECT idempleado, idperiodo, his.idconcepto, valor, importetotal, con.descripcion, con.tipoconcepto, con.numeroconcepto
                        FROM Nom10007 AS his
                        INNER JOIN nom10004 con
                            ON his.idconcepto = con.idconcepto
                        WHERE importetotal > 0
                            AND idperiodo = @idPeriodo
                            AND con.tipoconcepto IN (''O'')
							AND con.numeroconcepto IN (90,96)
                        UNION ALL
                        SELECT idempleado, idperiodo, actual.idconcepto, valor, importetotal, con.descripcion, con.tipoconcepto, con.numeroconcepto
                        FROM Nom10008 AS actual
                        INNER JOIN nom10004 con
                            ON actual.idconcepto = con.idconcepto
                        WHERE importetotal > 0
                            AND idperiodo = @idPeriodo
                            AND con.tipoconcepto IN (''O'')
							AND con.numeroconcepto IN (90,96)
                    ), ' + '
                    MovimientosObligaciones AS (
                        SELECT
                            emp.codigoempleado AS codigoempleado,
                            CASE
                                WHEN pdo.numeroconcepto = ''90'' THEN ''ISN''
                                WHEN pdo.numeroconcepto = ''96'' THEN ''IMSS Patronal''
                            END AS concepto,
                            pdo.tipoconcepto AS tipoConcepto,
                            SUM(pdo.importetotal) AS monto
                        FROM nom10001 emp
                        INNER JOIN nom10034 empPeriodo
                            ON emp.idempleado = empPeriodo.idempleado AND empPeriodo.cidperiodo = @idPeriodo
                        INNER JOIN MovimientosO pdo
                            ON empPeriodo.cidperiodo = pdo.idperiodo AND emp.idempleado = pdo.idempleado
                        WHERE empPeriodo.idtipoperiodo = @idTipoPeriodo
                            AND empPeriodo.idempleado BETWEEN @idEmpleadoInicial AND @idEmpleadoFinal
                        GROUP BY
                            emp.codigoempleado, pdo.descripcion, pdo.numeroconcepto, pdo.tipoconcepto
                    ),
                    TotalesPorEmpleadoObligacion AS (
                        SELECT
                            codigoempleado AS codigoempleado,
                            SUM(monto) AS monto
                        FROM MovimientosObligaciones
                        GROUP BY codigoempleado
                    ),
                    Comision AS (
                        SELECT
                            t.codigoempleado AS codigoempleado,
                            (com.monto_asignado + t.monto) * (com.fee / 100) AS total_comision
                        FROM TotalesPorEmpleadoObligacion AS t
                        INNER JOIN totalCompensacion com ON t.codigoempleado = com.codigoempleado
                    ),
                    pivotFinal AS (
                        SELECT
                            codigoempleado,
                            concepto,
                            monto
                        FROM MovimientosObligaciones
                        UNION ALL
                        SELECT
                            codigoempleado,
                            ''Comision Servicios'',
                            total_comision
                        FROM Comision
                        UNION ALL
                        SELECT
                            c.codigoempleado,
                            ''Costo Total'',
                            c.total_comision + mo.monto_asignado + tpo.monto AS monto
                        FROM Comision c
                        INNER JOIN totalCompensacion mo
                            ON c.codigoempleado = mo.codigoempleado
                        INNER JOIN TotalesPorEmpleadoObligacion AS tpo
                            ON mo.codigoempleado = tpo.codigoempleado
                    )
                    SELECT codigoempleado, ' + @cols + '
                    FROM pivotFinal
                    PIVOT (
                        SUM(monto)
                        FOR concepto IN (' + @cols + ')
                    ) p
                    ORDER BY codigoempleado;
                    ';

                    EXEC(@sql);
            ";
            $result = DB::connection('sqlsrv_dynamic')->select($sql);

            return $result;
        } catch (\Exception $e) {
            return [];
        }
    }

    public function datosTotales7($request = null)
    {
        try {
            $idNominaGapeEmpresa = $request->id_nomina_gape_empresa;
            $idTipoPeriodo = $request->id_tipo_periodo;
            $idPeriodo = $request->periodo_inicial;

            $idEmpleadoInicial = $request->empleado_inicial;
            $idEmpleadoFinal = $request->empleado_final;

            $conexion = $this->helperController->getConexionDatabaseNGE($idNominaGapeEmpresa, 'Nom');

            $this->helperController->setDatabaseConnection($conexion, $conexion->nombre_base);

            $sql = "
                DECLARE @sql  NVARCHAR(MAX);


                SET @sql = CAST('' AS NVARCHAR(MAX)) + '
                DECLARE @idPeriodo INT;
                DECLARE @idTipoPeriodo INT;

                DECLARE @idEmpleadoInicial INT;
                DECLARE @idEmpleadoFinal INT;
                DECLARE @idNominaGapeEmpresa INT;

                SET @idPeriodo = $idPeriodo;
                SET @idTipoPeriodo = $idTipoPeriodo;

                SET @idEmpleadoInicial = $idEmpleadoInicial;
                SET @idEmpleadoFinal = $idEmpleadoFinal;
                SET @idNominaGapeEmpresa = $idNominaGapeEmpresa;

                ;WITH
					Movimientos AS (
						SELECT idempleado, idperiodo, his.idconcepto, valor, importetotal, con.descripcion, con.tipoconcepto
						FROM Nom10007 AS his
						INNER JOIN nom10004 con
							ON his.idconcepto = con.idconcepto
						WHERE importetotal > 0
							AND idperiodo = @idPeriodo
							AND con.tipoconcepto IN (''P'',''D'')
						UNION ALL
						SELECT idempleado, idperiodo, actual.idconcepto, valor, importetotal, con.descripcion, con.tipoconcepto
						FROM Nom10008 AS actual
						INNER JOIN nom10004 con
							ON actual.idconcepto = con.idconcepto
						WHERE importetotal > 0
							AND idperiodo = @idPeriodo
							AND con.tipoconcepto IN (''P'',''D'')
					), ' + '
					MovimientosSumaQ2 AS (
						SELECT
							emp.codigoempleado,
							(emp.ccampoextranumerico2 * empPeriodo.cdiaspagados) AS sueldo,
							pdo.descripcion,
							pdo.tipoconcepto AS tipoConcepto,
							SUM(pdo.valor) AS cantidad,
							SUM(pdo.importetotal) AS monto,
							emp.ccampoextranumerico3 AS pension
						FROM nom10001 emp
						INNER JOIN nom10034 empPeriodo
							ON emp.idempleado = empPeriodo.idempleado
								AND empPeriodo.cidperiodo = @idPeriodo
						INNER JOIN Movimientos pdo
							ON empPeriodo.cidperiodo = pdo.idperiodo
							AND emp.idempleado = pdo.idempleado
						WHERE empPeriodo.idtipoperiodo = @idTipoPeriodo
							AND pdo.descripcion NOT IN(''Sueldo'',''Pensión Alimenticia'', ''Pension Alimenticia'')
							AND empPeriodo.estadoempleado IN (''A'', ''R'')
							AND empPeriodo.idempleado BETWEEN @idEmpleadoInicial AND @idEmpleadoFinal
						GROUP BY
							emp.codigoempleado, emp.ccampoextranumerico2, empPeriodo.cdiaspagados, pdo.descripcion, pdo.tipoconcepto, emp.ccampoextranumerico3
					), ' + '
					IncidenciasNormalizadasQ2 AS (
						SELECT
							emp.codigoempleado as codigoempleado,
							x.descripcion AS descripcion,
							''P'' AS tipoConcepto,
							x.valor AS valor,
							emp.ccampoextranumerico3 AS pension
						FROM [becma-core2].dbo.nomina_gape_incidencia AS ngi
						INNER JOIN [becma-core2].dbo.nomina_gape_incidencia_detalle AS ngid
							ON ngi.id = ngid.id_nomina_gape_incidencia
						INNER JOIN nom10034 AS empP
							ON ngid.id_empleado = empP.idempleado
							AND empP.cidperiodo = @idPeriodo
							AND empP.idtipoperiodo = @idTipoPeriodo
							AND empP.idempleado BETWEEN @idEmpleadoInicial AND @idEmpleadoFinal
							AND empP.estadoempleado IN (''A'', ''R'')
						INNER JOIN nom10001 AS emp
							ON empP.idempleado = emp.idempleado
						CROSS APPLY (VALUES
							(''Comisiones'',               ngid.comision),
							(''Bono'',                   ngid.bono),
							(''Horas Extra Doble cantidad'',   ngid.horas_extra_doble_cantidad),
							(''Horas Extra Doble monto'',      ngid.horas_extra_doble),
							(''Horas Extra Triple cantidad'',  ngid.horas_extra_triple_cantidad),
							(''Horas Extra Triple monto'',     ngid.horas_extra_triple),
							(''Pago adicional'',         ngid.pago_adicional),
							(''Premio puntualidad'',     ngid.premio_puntualidad)
						) AS x(descripcion, valor)
						WHERE ngi.id_tipo_periodo = @idTipoPeriodo
							AND ngi.id_nomina_gape_empresa = @idNominaGapeEmpresa
							AND ngid.id_empleado BETWEEN @idEmpleadoInicial AND @idEmpleadoFinal
					), ' + '
					TotalesPorEmpleadoGeneralQ2 AS (
						SELECT
							codigoempleado,
							MAX(sueldo) AS sueldo,
							SUM(CASE WHEN tipoConcepto = ''P'' THEN monto ELSE 0 END)
							+
							SUM(CASE WHEN tipoConcepto = ''P'' THEN valor ELSE 0 END) AS total_percepciones_sin_sueldo,
							SUM(CASE WHEN tipoConcepto = ''D'' THEN monto ELSE 0 END) AS total_deducciones,
							pension AS porcentajePension

						FROM (
							SELECT
								codigoempleado,
								sueldo,
								tipoConcepto,
								monto AS monto,
								0 AS valor,
								pension
							FROM MovimientosSumaQ2
							UNION ALL
							SELECT
								codigoempleado,
								0 AS sueldo,
								''P'' AS tipoConcepto,
								0 AS monto,
								valor AS valor,
								pension
							FROM IncidenciasNormalizadasQ2
						) AS x
						GROUP BY codigoempleado, pension
					),
					MovimientosSumaQ3 AS (
							SELECT
								emp.codigoempleado,
								empPeriodo.sueldodiario AS sd,
								empPeriodo.sueldointegrado AS sdi,
								pdo.descripcion,
								pdo.tipoconcepto AS tipoConcepto,
								SUM(pdo.importetotal) AS monto,
								emp.ccampoextranumerico3 AS pension
							FROM nom10001 emp
							INNER JOIN nom10034 empPeriodo
								ON emp.idempleado = empPeriodo.idempleado
								AND empPeriodo.cidperiodo = @idPeriodo
							INNER JOIN Movimientos pdo
								ON empPeriodo.cidperiodo = pdo.idperiodo
								AND emp.idempleado = pdo.idempleado
							WHERE empPeriodo.idtipoperiodo = @idTipoPeriodo
								AND pdo.descripcion NOT IN (''Pensión Alimenticia'', ''Pension Alimenticia'')
								AND empPeriodo.estadoempleado IN (''A'', ''R'')
								AND empPeriodo.idempleado BETWEEN @idEmpleadoInicial AND @idEmpleadoFinal
							GROUP BY
								emp.codigoempleado, empPeriodo.sueldodiario, empPeriodo.sueldointegrado, pdo.descripcion, pdo.tipoconcepto, emp.ccampoextranumerico3
					), ' + '
					TotalesPorEmpleadoQ3 AS (
							SELECT
								codigoempleado,
								sd,
								sdi,
								SUM(CASE WHEN tipoConcepto = ''P'' THEN monto ELSE 0 END) AS total_percepciones,
								SUM(CASE WHEN tipoConcepto = ''D'' THEN monto ELSE 0 END) AS total_deducciones,
								pension AS porcentajePension
							FROM MovimientosSumaQ3
							GROUP BY codigoempleado, pension, sdi, sd
					), ' + ' -------- se omite esto por el momento porque no se sabe cuales son las deducciones excedentes
					MovimientosSumaExcedente AS (
						SELECT
							emp.codigoempleado,
							pdo.descripcion,
							SUM(pdo.importeTotal) AS monto
						FROM nom10001 emp
						INNER JOIN nom10034 empPeriodo
							ON emp.idempleado = empPeriodo.idempleado
							AND empPeriodo.cidperiodo = @idPeriodo
						INNER JOIN Movimientos pdo
							ON empPeriodo.cidperiodo = pdo.idperiodo
							AND emp.idempleado = pdo.idempleado
						WHERE empPeriodo.idtipoperiodo = @idTipoPeriodo
							AND pdo.tipoconcepto IN (''P'')
							AND pdo.descripcion NOT IN (''Pensión Alimenticia'', ''Pension Alimenticia'')
							AND empPeriodo.estadoempleado IN (''A'', ''R'')
							AND empPeriodo.idempleado BETWEEN @idEmpleadoInicial AND @idEmpleadoFinal
						GROUP BY
							emp.codigoempleado, empPeriodo.sueldodiario, empPeriodo.sueldointegrado, pdo.descripcion
					), ' + ' -------- se omite esto por el momento porque no se sabe cuales son las deducciones excedentes
					TotalesPorEmpleadoDeduccionesExcedentes AS (
						SELECT
							emp.codigoempleado,
							(emp.ccampoextranumerico2 * empPeriodo.cdiaspagados) AS sueldo,
							SUM(CASE WHEN pdo.tipoconcepto = ''D'' THEN pdo.importetotal ELSE 0 END) AS total_deducciones_exc
						FROM nom10001 emp
						INNER JOIN nom10034 empPeriodo
							ON emp.idempleado = empPeriodo.idempleado
							AND empPeriodo.cidperiodo = @idPeriodo
						INNER JOIN Movimientos pdo
							ON empPeriodo.cidperiodo = pdo.idperiodo
							AND emp.idempleado = pdo.idempleado
						WHERE empPeriodo.idtipoperiodo = @idTipoPeriodo
							AND pdo.tipoconcepto IN (''P'')
							AND pdo.descripcion NOT IN (''Pensión Alimenticia'', ''Pension Alimenticia'')
							AND empPeriodo.estadoempleado IN (''A'', ''R'')
							AND empPeriodo.idempleado BETWEEN @idEmpleadoInicial AND @idEmpleadoFinal
						GROUP BY
							emp.codigoempleado, emp.ccampoextranumerico2, empPeriodo.cdiaspagados
					), ' + '
					TotalesPorEmpleado AS ( -- totales generales Query1
						SELECT
							emp.codigoempleado,
							(emp.ccampoextranumerico2 * empPeriodo.cdiaspagados) AS sueldo,
							SUM(CASE WHEN pdo.tipoconcepto = ''P'' THEN pdo.importetotal ELSE 0 END) AS total_percepciones_sin_sueldo,
							SUM(CASE WHEN pdo.tipoconcepto = ''D'' THEN pdo.importetotal ELSE 0 END) AS total_deducciones
						FROM nom10001 emp
						INNER JOIN nom10034 empPeriodo
							ON emp.idempleado = empPeriodo.idempleado
							AND empPeriodo.cidperiodo = @idPeriodo
						INNER JOIN Movimientos pdo
							ON empPeriodo.cidperiodo = pdo.idperiodo
							AND emp.idempleado = pdo.idempleado
						WHERE empPeriodo.idtipoperiodo = @idTipoPeriodo
							AND pdo.tipoconcepto IN (''P'',''D'')
							AND pdo.descripcion NOT IN (''Sueldo'')
							AND empPeriodo.idempleado BETWEEN @idEmpleadoInicial AND @idEmpleadoFinal
						GROUP BY
							emp.codigoempleado, emp.ccampoextranumerico2, empPeriodo.cdiaspagados
					), ' + '
					TotalesPorEmpleadoFis AS ( -- totales P D sin pension Query3
						SELECT
							emp.codigoempleado,
							empPeriodo.sueldodiario AS sd,
							empPeriodo.sueldointegrado AS sdi,
							SUM(CASE WHEN pdo.tipoconcepto = ''P'' THEN pdo.importetotal ELSE 0 END) AS total_percepciones,
							SUM(CASE WHEN pdo.tipoconcepto = ''D'' THEN pdo.importetotal ELSE 0 END) AS total_deducciones
						FROM nom10001 emp
						INNER JOIN nom10034 empPeriodo
							ON emp.idempleado = empPeriodo.idempleado
							AND empPeriodo.cidperiodo = @idPeriodo
						INNER JOIN Movimientos pdo
							ON empPeriodo.cidperiodo = pdo.idperiodo
							AND emp.idempleado = pdo.idempleado
						WHERE empPeriodo.idtipoperiodo = @idTipoPeriodo
							AND empPeriodo.idempleado BETWEEN @idEmpleadoInicial AND @idEmpleadoFinal
						GROUP BY
							emp.codigoempleado, empPeriodo.sueldodiario, empPeriodo.sueldointegrado
					), ' + '
					preNetoTotalAPagar AS ( -- totales P - D Sin pension Query3
						SELECT
							tpf.codigoempleado,
							(tpf.total_percepciones - tpf.total_deducciones) AS netoTotalAPagar
						FROM TotalesPorEmpleadoFis tpf
					), ' + '
					preTotalesProrrateo AS (
						SELECT
							q2.codigoempleado,
							((q2.sueldo + q2.total_percepciones_sin_sueldo) - (q2.total_deducciones + CASE WHEN q2.porcentajePension > 0 THEN (q2.sueldo + q2.total_percepciones_sin_sueldo) * (q2.porcentajePension / 100) ELSE 0 END))
							-
							((q3.total_percepciones) - (q3.total_deducciones + CASE WHEN q3.porcentajePension > 0 THEN (q3.total_percepciones) * (q3.porcentajePension / 100) ELSE 0 END))
							AS netoAPagar
						FROM TotalesPorEmpleadoGeneralQ2 q2
						INNER JOIN TotalesPorEmpleadoQ3 q3
							ON q2.codigoempleado = q3.codigoempleado
					), ' + '
					ParamConfig AS (
						SELECT
							sueldo_imss, sueldo_imss_tope, sueldo_imss_orden
							, prev_social, prev_social_tope, prev_social_orden
							, fondos_sind, fondos_sind_tope, fondos_sind_orden
							, tarjeta_facil, tarjeta_facil_tope, tarjeta_facil_orden
							, hon_asimilados, hon_asimilados_tope, hon_asimilados_orden
							, gastos_compro, gastos_compro_tope, gastos_compro_orden
						FROM [becma-core2].[dbo].[nomina_gape_concepto_pago_parametrizacion]
						WHERE id_tipo_periodo = @idTipoPeriodo
						AND id_nomina_gape_empresa =   @idNominaGapeEmpresa
					), ' + '
					ConceptosParametrizados AS (
						SELECT ''sueldo_imss'' AS concepto, sueldo_imss AS activo, sueldo_imss_tope AS tope, sueldo_imss_orden AS orden FROM ParamConfig
						UNION ALL SELECT ''prev_social'',   prev_social,   prev_social_tope,   prev_social_orden   FROM ParamConfig
						UNION ALL SELECT ''fondos_sind'',   fondos_sind,   fondos_sind_tope,   fondos_sind_orden   FROM ParamConfig
						UNION ALL SELECT ''tarjeta_facil'', tarjeta_facil, tarjeta_facil_tope, tarjeta_facil_orden FROM ParamConfig
						UNION ALL SELECT ''hon_asimilados'',hon_asimilados, hon_asimilados_tope, hon_asimilados_orden FROM ParamConfig
						UNION ALL SELECT ''gastos_compro'', gastos_compro, gastos_compro_tope, gastos_compro_orden FROM ParamConfig
					), ' + '
					ConceptosActivos AS (
						SELECT concepto, CAST(tope AS DECIMAL(18,2)) AS tope, orden
						FROM ConceptosParametrizados
						WHERE activo = 1
					), ' + '
					BaseProrrateo AS (
						SELECT
							p.codigoempleado,
							CAST(p.netoAPagar AS DECIMAL(18,2)) AS netoAPagar,
							c.concepto,
							c.tope,
							c.orden,
							ROW_NUMBER() OVER (PARTITION BY p.codigoempleado ORDER BY c.orden) AS rn
						FROM preTotalesProrrateo p
						CROSS JOIN ConceptosActivos c
					), ' + '
					ProrrateoRecursivo AS (
						-- ANCLA
						SELECT
							b.codigoempleado,
							b.concepto,
							b.orden,
							b.tope,
							b.rn,
							CAST(b.netoAPagar AS DECIMAL(18,2)) AS saldo_antes,
							CAST(
								CASE
									WHEN b.netoAPagar <= 0 THEN 0
									WHEN b.tope IS NULL OR b.netoAPagar >= b.tope THEN ISNULL(b.tope, b.netoAPagar)
									ELSE b.netoAPagar
								END
							AS DECIMAL(18,2)) AS monto_asignado,
							CAST(
								b.netoAPagar -
								CASE
									WHEN b.netoAPagar <= 0 THEN 0
									WHEN b.tope IS NULL OR b.netoAPagar >= b.tope THEN ISNULL(b.tope, b.netoAPagar)
									ELSE b.netoAPagar
								END
							AS DECIMAL(18,2)) AS saldo_despues
						FROM BaseProrrateo b
						WHERE b.rn = 1

						UNION ALL

						-- RECURSIVO
						SELECT
							b.codigoempleado,
							b.concepto,
							b.orden,
							b.tope,
							b.rn,
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
					), ' + '
					PercepcionesExcedentes AS (
						SELECT
							codigoempleado,
							SUM(monto_asignado) AS total_percepciones_excedente
						FROM ProrrateoRecursivo
						GROUP BY codigoempleado
					), ' + '
					TotalesExcedentes AS (
						SELECT
							pe.codigoempleado,
							pe.total_percepciones_excedente,
							ISNULL(td.total_deducciones_exc, 0) AS total_deducciones_exc
						FROM PercepcionesExcedentes pe
						LEFT JOIN TotalesPorEmpleadoDeduccionesExcedentes td
							ON pe.codigoempleado = td.codigoempleado
					), ' + '
					NetoExcedentes AS (
						SELECT
							te.codigoempleado,
							te.total_percepciones_excedente,
							te.total_deducciones_exc,
							te.total_percepciones_excedente - te.total_deducciones_exc AS neto_excedente
						FROM TotalesExcedentes te
					),' + '
					NetoTotalAPagar AS (
						SELECT
							ne.codigoempleado,
							ne.total_percepciones_excedente,
							ne.total_deducciones_exc,
							ne.neto_excedente,
							(ISNULL(pn.netoTotalAPagar,0) + ne.neto_excedente) AS neto_total_pagar
						FROM NetoExcedentes ne
						LEFT JOIN preNetoTotalAPagar pn
							ON ne.codigoempleado = pn.codigoempleado
					), ' + '
					pensionExcedente AS (
						SELECT
							gen.codigoempleado AS codigoempleado,
							''Pension Excedente'' AS concepto,
							CASE WHEN gen.porcentajePension > 0 THEN ((gen.sueldo + gen.total_percepciones_sin_sueldo) * (gen.porcentajePension / 100)) - ((fis.total_percepciones) * (fis.porcentajePension / 100)) ELSE 0 END AS monto
						FROM TotalesPorEmpleadoGeneralQ2 AS gen
						INNER JOIN TotalesPorEmpleadoQ3 fis ON gen.codigoempleado = fis.codigoempleado
					) , ' + '
					provisiones AS (
						SELECT
							emp.codigoempleado AS codigoempleado,
							(empPeriodo.sueldodiario * antig.DiasAguinaldo) / 365 * periodo.diasdepago AS provisionAguinaldo,
							(empPeriodo.sueldodiario * antig.DiasVacaciones * (PorcentajePrima / 100.0)) / 365.0 * periodo.diasdepago AS provisionPrimaVacacional

						FROM nom10001 emp
						INNER JOIN nom10034 empPeriodo
							ON emp.idempleado = empPeriodo.idempleado AND empPeriodo.cidperiodo = @idPeriodo
						INNER JOIN nom10002 AS periodo
							ON empPeriodo.cidperiodo = periodo.idperiodo AND periodo.idperiodo = @idPeriodo
						INNER JOIN nom10050 AS tipoPres
							ON empPeriodo.TipoPrestacion = tipoPres.IDTabla
						CROSS APPLY (
							SELECT TOP 1 *
							FROM nom10051 antig
							WHERE antig.IDTablaPrestacion = tipoPres.IDTabla
								AND antig.Antiguedad =
									CASE
										WHEN FLOOR(DATEDIFF(day, empPeriodo.fechaalta, GETDATE()) / 365.25) = 0
											THEN 1
										ELSE FLOOR(DATEDIFF(day, empPeriodo.fechaalta, GETDATE()) / 365.25)
									END
							ORDER BY antig.fechainicioVigencia DESC
						) AS antig
						WHERE empPeriodo.cidperiodo = @idPeriodo
						AND empPeriodo.estadoempleado IN (''A'', ''R'')
						AND empPeriodo.idempleado BETWEEN @idEmpleadoInicial AND @idEmpleadoFinal
					), ' + '

                    nomGapeEmpresa AS (
                        SELECT
                            provisiones,
                            fee
                        FROM [becma-core2].dbo.nomina_gape_parametrizacion
                        WHERE id_nomina_gape_empresa = @idNominaGapeEmpresa
                            AND id_tipo_periodo = @idTipoPeriodo
                    ),
                    totalCompensacion AS (
                        SELECT
                            nta.codigoempleado,
                            nge.fee AS  fee,
                            ((nta.neto_total_pagar - pe.monto) - ((q3.total_percepciones) * (q3.porcentajePension / 100))
							+
							(q3.total_deducciones + CASE WHEN porcentajePension > 0 THEN (total_percepciones) * (porcentajePension / 100) ELSE 0 END)
							+
							(nta.total_deducciones_exc + pe.monto ))
							+
							CASE
								WHEN nge.provisiones = ''si''
								THEN (
									SELECT
									(prov.provisionPrimaVacacional + prov.provisionAguinaldo)
									FROM provisiones AS prov
									WHERE nta.codigoempleado = prov.codigoempleado
								)
								ELSE 0
							END
							AS monto_asignado
							FROM NetoTotalAPagar AS nta
							INNER JOIN pensionExcedente AS pe
								ON nta.codigoempleado = pe.codigoempleado
							INNER JOIN TotalesPorEmpleadoQ3 AS q3
								ON nta.codigoempleado = q3.codigoempleado
							CROSS JOIN nomGapeEmpresa nge
                    ), ' + '
                    MovimientosO AS (
                        SELECT idempleado, idperiodo, his.idconcepto, valor, importetotal, con.descripcion, con.tipoconcepto, con.numeroconcepto
                        FROM Nom10007 AS his
                        INNER JOIN nom10004 con
                            ON his.idconcepto = con.idconcepto
                        WHERE importetotal > 0
                            AND idperiodo = @idPeriodo
                            AND con.tipoconcepto IN (''O'')
							AND con.numeroconcepto IN (90,96)
                        UNION ALL
                        SELECT idempleado, idperiodo, actual.idconcepto, valor, importetotal, con.descripcion, con.tipoconcepto, con.numeroconcepto
                        FROM Nom10008 AS actual
                        INNER JOIN nom10004 con
                            ON actual.idconcepto = con.idconcepto
                        WHERE importetotal > 0
                            AND idperiodo = @idPeriodo
                            AND con.tipoconcepto IN (''O'')
							AND con.numeroconcepto IN (90,96)
                    ), ' + '
                    MovimientosObligaciones AS (
                        SELECT
                            emp.codigoempleado AS codigoempleado,
                            CASE
                                WHEN pdo.numeroconcepto = ''90'' THEN ''ISN''
                                WHEN pdo.numeroconcepto = ''96'' THEN ''IMSS Patronal''
                            END AS concepto,
                            pdo.tipoconcepto AS tipoConcepto,
                            SUM(pdo.importetotal) AS monto
                        FROM nom10001 emp
                        INNER JOIN nom10034 empPeriodo
                            ON emp.idempleado = empPeriodo.idempleado AND empPeriodo.cidperiodo = @idPeriodo
                        INNER JOIN MovimientosO pdo
                            ON empPeriodo.cidperiodo = pdo.idperiodo
								AND emp.idempleado = pdo.idempleado
								AND pdo.idempleado IN (SELECT DISTINCT idempleado FROM Movimientos pd)
                        WHERE empPeriodo.idtipoperiodo = @idTipoPeriodo
                            AND empPeriodo.idempleado BETWEEN @idEmpleadoInicial AND @idEmpleadoFinal
                            AND empPeriodo.estadoempleado IN (''A'', ''R'')
                        GROUP BY
                            emp.codigoempleado, pdo.descripcion, pdo.numeroconcepto, pdo.tipoconcepto
                    ),
                    TotalesPorEmpleadoObligacion AS (
                        SELECT
                            codigoempleado AS codigoempleado,
                            SUM(monto) AS monto
                        FROM MovimientosObligaciones
                        GROUP BY codigoempleado
                    ),
                    Comision AS (
                        SELECT
                            t.codigoempleado AS codigoempleado,
                            (com.monto_asignado + t.monto) * (com.fee / 100) AS total_comision
                        FROM TotalesPorEmpleadoObligacion AS t
                        INNER JOIN totalCompensacion com ON t.codigoempleado = com.codigoempleado
                    ),
                    pivotFinal AS (
                        SELECT
                            codigoempleado,
                            ''Compensacion'' as concepto,
                            monto_asignado AS monto
                        FROM totalCompensacion
                        UNION ALL
                        SELECT
                            codigoempleado,
                            ''Total cs'' as concepto,
                            monto AS monto
                        FROM TotalesPorEmpleadoObligacion
                        UNION ALL
                        SELECT
                            codigoempleado,
                            concepto,
                            monto
                        FROM MovimientosObligaciones
                        UNION ALL
                        SELECT
                            codigoempleado,
                            ''Comision Servicios'',
                            total_comision AS monto
                        FROM Comision
                        UNION ALL
                        SELECT
                            c.codigoempleado,
                            ''Costo Total'',
                            c.total_comision + mo.monto_asignado + tpo.monto AS monto
                        FROM Comision c
                        INNER JOIN totalCompensacion mo
                            ON c.codigoempleado = mo.codigoempleado
                        INNER JOIN TotalesPorEmpleadoObligacion AS tpo
                            ON mo.codigoempleado = tpo.codigoempleado
                    ) , ' + '
                    ResumenFinal AS (
                        SELECT
                            SUM(CASE WHEN concepto = ''Compensacion'' THEN monto ELSE 0 END) AS percepcion_bruta,
                            SUM(CASE WHEN concepto = ''Total cs'' THEN monto ELSE 0 END) AS costo_social
                        FROM pivotFinal
                    ), ' + '
                    TotalesCalculados AS (
                        SELECT
                            percepcion_bruta,
                            costo_social,
                            (percepcion_bruta + costo_social) AS base_comision,
							(SELECT (fee/100) FROM nomGapeEmpresa) AS fee_porcentaje,
                            (percepcion_bruta + costo_social) * (SELECT (fee/100) FROM nomGapeEmpresa) AS fee,
                            (percepcion_bruta + costo_social) + ((percepcion_bruta + costo_social) * (SELECT (fee/100) FROM nomGapeEmpresa)) AS subtotal,
                            ((percepcion_bruta + costo_social) + ((percepcion_bruta + costo_social) * (SELECT (fee/100) FROM nomGapeEmpresa))) * 0.16 AS iva,
                            ((percepcion_bruta + costo_social) + ((percepcion_bruta + costo_social) * (SELECT (fee/100) FROM nomGapeEmpresa))) * 1.16 AS total
                        FROM ResumenFinal
                    ) ' + '
                    SELECT *
                    FROM TotalesCalculados;
                    ';

                    EXEC(@sql);
            ";
            //return $sql;
            $result = collect(DB::connection('sqlsrv_dynamic')->select($sql))->first();

            return $result;
        } catch (\Exception $e) {
            return [];
        }
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

    public function prenominaFiscal(Request $request)
    {
        // ⚠ OPTIMIZACIÓN GLOBAL PARA EXCEL
        ini_set('max_execution_time', 0);
        ini_set('memory_limit', '2048M');


        $validated = $request->validate([

            'fiscal' => 'required|boolean',
            'id_nomina_gape_empresa' => 'required',

            // Si fiscal = true
            'id_tipo_periodo' => 'required_if:fiscal,true',
            'periodo_inicial' => 'required_if:fiscal,true',
            //'departamento_inicial' => 'required_if:fiscal,true',
            //'departamento_final' => 'required_if:fiscal,true',

            // Siempre obligatorios
            'empleado_inicial' => 'required',
            'empleado_final' => 'required',
        ]);

        // Formato excel
        $path = storage_path('app/public/plantillas/formato_prenomina.xlsx');
        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getSheetByName('prenomina');


        // Información json
        /*
        $jsonPathDetalleEmpleado = storage_path('app/public/plantillas/data/01detalleEmpleado.json');
        $jsonPathNominaIngresosReales = storage_path('app/public/plantillas/data/02nominaIngresosReales.json');
        $jsonPathPercepciones  = storage_path('app/public/plantillas/data/03percepciones.json');
        $jsonPathExcedente  = storage_path('app/public/plantillas/data/04excedente.json');
        $jsonPathProvisiones  = storage_path('app/public/plantillas/data/05provisiones.json');
        $jsonPathCargaSocial = storage_path('app/public/plantillas/data/06cargaSocial.json');
        */

        //return $this->datosQuery3($request);

        // Obtener data
        $dataDetalleEmpleado = collect($this->datosQuery1($request))
            ->map(fn($r) => (array)$r)
            ->toArray();

        $dataNominaIngresosReales = collect($this->datosQuery2($request))
            ->map(fn($r) => (array)$r)
            ->toArray();

        $dataPercepciones = collect($this->datosQuery3($request))
            ->map(fn($r) => (array)$r)
            ->toArray();

        $dataExcedente = collect($this->datosQuery4($request))
            ->map(fn($r) => (array)$r)
            ->toArray();

        $dataProvisiones = collect($this->datosQuery5($request))
            ->map(fn($r) => (array)$r)
            ->toArray();

        $dataCargaSocial = collect($this->datosQuery6($request))
            ->map(fn($r) => (array)$r)
            ->toArray();

        $datosTotales = $this->datosTotales7($request);
        /*
        $dataDetalleEmpleado = json_decode(file_get_contents($jsonPathDetalleEmpleado), true);
        $dataNominaIngresosReales = json_decode(file_get_contents($jsonPathNominaIngresosReales), true);
        $dataPercepciones = json_decode(file_get_contents($jsonPathPercepciones), true);
        $dataExcedente = json_decode(file_get_contents($jsonPathExcedente), true);
        $dataProvisiones = json_decode(file_get_contents($jsonPathProvisiones), true);
        $dataCargaSocial = json_decode(file_get_contents($jsonPathCargaSocial), true);
        */


        // Obtener índices de las columnas
        $indicesDetalleEmpleado = array_keys($dataDetalleEmpleado[0]);
        $indicesNominaIngresosReales = array_keys($dataNominaIngresosReales[0]);
        $indicesPercepciones = array_keys($dataPercepciones[0]);
        $indicesExcedentes = array_keys($dataExcedente[0]);
        $indicesProvisiones = array_keys($dataProvisiones[0]);
        $indicesCargaSocial = array_keys($dataCargaSocial[0]);

        $omitEmp = ['codigoempleado'];
        $omit = ['codigoEmpleado'];

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
        $sheet->insertNewColumnBefore("Q", $xlTotalColumnasDinamicas);



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
        $xlFilaInicioDatos = 13;
        $xlFilaFinDatos = $xlFilaInicioDatos + count($xlMatriz) - 1;

        $sheet->insertNewRowBefore(13, count($xlMatriz));

        // Insertar datos masivamente
        $sheet->fromArray($xlMatriz, null, "A12");

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
