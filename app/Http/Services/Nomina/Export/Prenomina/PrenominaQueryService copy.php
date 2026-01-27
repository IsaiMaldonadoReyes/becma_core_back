<?php

namespace App\Http\Services\Nomina\Export\Prenomina;

use Illuminate\Http\Request;

use App\Http\Services\Core\HelperService;

use Illuminate\Support\Facades\DB;

class PrenominaQueryService
{

    protected $helper;

    /**
     * Inyección automática del HelperService (antes era un Controller)
     */
    public function __construct(HelperService $helper)
    {
        $this->helper = $helper;
    }
    /**
     * Llama dinámicamente a la función de consulta según configuración.
     */
    public function getData(string $queryName, Request $request)
    {
        if (!method_exists($this, $queryName)) {
            throw new \Exception("La consulta '$queryName' no está definida en IncidenciasQueryService.");
        }

        return $this->{$queryName}($request);
    }

    /**
     * Consulta para formato SUELDO_IMSS
     */
    private function datosSueldoImss_1(Request $request)
    {
        try {
            $idNominaGapeEmpresa = $request->id_nomina_gape_empresa;
            $idTipoPeriodo = $request->id_tipo_periodo;
            $idPeriodo = $request->periodo_inicial;

            $idEmpleadoInicial = $request->empleado_inicial;
            $idEmpleadoFinal = $request->empleado_final;

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

                ;WITH MovimientosRetro AS (
                    SELECT idempleado, idperiodo, his.idconcepto, valor, importetotal, con.descripcion, con.tipoconcepto
                    FROM Nom10007 AS his
                        INNER JOIN nom10004 con
                            ON his.idconcepto = con.idconcepto
                    WHERE
                        valor > 0
                        AND idperiodo = @idPeriodo
                        AND con.tipoconcepto IN ('P')
                        AND con.descripcion LIKE '%retroactivo%'

                    UNION ALL

                    SELECT idempleado, idperiodo, actual.idconcepto, valor, importetotal, con.descripcion, con.tipoconcepto
                    FROM Nom10008 AS actual
                    INNER JOIN nom10004 con
                        ON actual.idconcepto = con.idconcepto
                    WHERE
                        valor > 0
                        AND idperiodo = @idPeriodo
                        AND con.tipoconcepto IN ('P')
                        AND con.descripcion LIKE '%retroactivo%'
                ),
                tarjetaControl AS (
                    SELECT idempleado, idperiodo, valor, idtarjetaincapacidad, ti.mnemonico AS mnemonico
                    FROM nom10009 AS fi
                    INNER JOIN nom10022 ti
                        ON fi.idtipoincidencia = ti.idtipoincidencia
                    WHERE
                        ti.mnemonico IN ('INC', 'FINJ')
                        AND idperiodo = @idPeriodo

                    UNION ALL

                    SELECT idempleado, idperiodo, valor, idtarjetaincapacidad, ti.mnemonico AS mnemonico
                    FROM NOM10010 AS fi
                    INNER JOIN nom10022 ti
                        ON fi.idtipoincidencia = ti.idtipoincidencia
                    WHERE
                        ti.mnemonico IN ('INC', 'FINJ')
                        AND idperiodo = @idPeriodo
                ),
                TarjetaControlAgrupado AS (
                    SELECT
                        idempleado,
                        SUM(CASE WHEN mnemonico = 'INC' THEN valor ELSE 0 END) AS incapacidad,
                        SUM(CASE WHEN mnemonico = 'FINJ' THEN valor ELSE 0 END) AS faltas
                    FROM tarjetaControl
                    GROUP BY
                        idempleado
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
                    , CASE
						WHEN empPeriodo.fechaalta < periodo.fechainicio
							THEN (periodo.diasdepago)
						WHEN empPeriodo.fechaalta >= periodo.fechainicio AND empPeriodo.fechaalta <= periodo.fechafin
							THEN (DATEDIFF(DAY, empPeriodo.fechaalta, periodo.fechafin) + 1)
						ELSE 0
					  END AS diasPeriodo

                    , ISNULL(SUM(movP.valor), 0) AS diasRetroactivos
                    , ISNULL(tc.incapacidad, 0) AS incapacidad
                    , ISNULL(tc.faltas, 0) AS faltas
                    , CASE
                        WHEN empPeriodo.fechaalta < periodo.fechainicio THEN
                            periodo.diasdepago
                            + ISNULL(SUM(movP.valor), 0)
                            - ISNULL(tc.incapacidad, 0)
                            - ISNULL(tc.faltas, 0)

                        WHEN empPeriodo.fechaalta BETWEEN periodo.fechainicio AND periodo.fechafin THEN
                            DATEDIFF(DAY, empPeriodo.fechaalta, periodo.fechafin) + 1
                            + ISNULL(SUM(movP.valor), 0)
                            - ISNULL(tc.incapacidad, 0)
                            - ISNULL(tc.faltas, 0)
                        ELSE 0
                        END AS diasPagados

                    , ISNULL(
                            ROUND(
                                emp.ccampoextranumerico2 *
                                CASE
                                    WHEN empPeriodo.fechaalta < periodo.fechainicio THEN
                                        periodo.diasdepago
                                        + ISNULL(SUM(movP.valor), 0)
                                        - ISNULL(tc.incapacidad, 0)
                                        - ISNULL(tc.faltas, 0)

                                    WHEN empPeriodo.fechaalta BETWEEN periodo.fechainicio AND periodo.fechafin THEN
                                        DATEDIFF(DAY, empPeriodo.fechaalta, periodo.fechafin) + 1
                                        + ISNULL(SUM(movP.valor), 0)
                                        - ISNULL(tc.incapacidad, 0)
                                        - ISNULL(tc.faltas, 0)

                                    ELSE 0
                                END,
                            2),
                        0) AS sueldo
                FROM nom10001 emp
                    INNER JOIN nom10034 AS empPeriodo
                        ON emp.idempleado = empPeriodo.idempleado
                            AND empPeriodo.cidperiodo = @idPeriodo
                    INNER JOIN nom10002 AS periodo
                        ON empPeriodo.cidperiodo = periodo.idperiodo
                    LEFT JOIN nom10006 AS puesto
                        ON emp.idpuesto = puesto.idpuesto
                    LEFT JOIN MovimientosRetro AS movP
                        ON empPeriodo.idempleado = movP.idempleado
                    LEFT JOIN TarjetaControlAgrupado AS tc
                        ON empPeriodo.idempleado = tc.idempleado

                WHERE
                    empPeriodo.idtipoperiodo = @idTipoPeriodo
                    AND empPeriodo.idempleado BETWEEN @idEmpleadoInicial AND @idEmpleadoFinal
                    AND empPeriodo.estadoempleado IN ('A', 'R')
                    AND emp.TipoRegimen IN ('02', '03', '04')
                GROUP BY
                    emp.idempleado,
                    emp.codigoempleado,
                    emp.nombrelargo,
                    puesto.descripcion,
                    emp.fechaalta,
                    emp.campoextra1,
                    emp.numerosegurosocial,
                    emp.rfc,
                    emp.fechanacimiento,
                    emp.homoclave,
                    emp.ccampoextranumerico1,
                    emp.ccampoextranumerico2,
                    periodo.diasdepago,
                    empPeriodo.cdiasincapacidades,
                    empPeriodo.cdiasausencia,
                    empPeriodo.cdiaspagados,
                    emp.curpi,
                    emp.curpf,
                    empPeriodo.fechaalta,
                    periodo.fechainicio,
                    periodo.fechafin,
                    tc.faltas,
                    tc.incapacidad
                ORDER BY
                    emp.codigoempleado
            ";

            /*
            dd([
                'row' => $sql,
            ]);
            */
            //return $sql;
            $result = DB::connection('sqlsrv_dynamic')->select($sql);


            return $result;
        } catch (\Exception $e) {
            return [];
        }
    }

    private function datosSueldoImss_2(Request $request)
    {
        try {
            $idNominaGapeEmpresa = $request->id_nomina_gape_empresa;
            $idTipoPeriodo = $request->id_tipo_periodo;
            $idPeriodo = $request->periodo_inicial;

            $idEmpleadoInicial = $request->empleado_inicial;
            $idEmpleadoFinal = $request->empleado_final;

            $idNominaGapeIncidencia = $request->id_nomina_gape_incidencia ?? 0;

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
                    WHERE
                        importetotal > 0
                        AND idperiodo = @idPeriodo
						AND con.tipoconcepto IN ('P','D')
						AND (
                                con.descripcion LIKE '%ahorro%' -- caja de ahorro
                                OR con.descripcion LIKE '%infonavit%'
                                OR con.descripcion LIKE '%fonacot%'
                                OR con.numeroconcepto IN (20) -- prima vacacional
							)

                    UNION ALL

                    SELECT idempleado, idperiodo, actual.idconcepto, valor, importetotal, con.descripcion, con.tipoconcepto
                    FROM Nom10008 AS actual
					INNER JOIN nom10004 con
                        ON actual.idconcepto = con.idconcepto
                    WHERE
                        importetotal > 0
                        AND idperiodo = @idPeriodo
						AND con.tipoconcepto IN ('P','D')
						AND (
                                con.descripcion LIKE '%ahorro%' -- caja de ahorro
                                OR con.descripcion LIKE '%infonavit%'
                                OR con.descripcion LIKE '%fonacot%'
                                OR con.numeroconcepto IN (20) -- prima vacacional
							)
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
                    WHERE
                        empPeriodo.idtipoperiodo = @idTipoPeriodo
						AND empPeriodo.idempleado BETWEEN @idEmpleadoInicial AND @idEmpleadoFinal
                        AND empPeriodo.estadoempleado IN ('A', 'R')
                        AND emp.TipoRegimen IN ('02', '03', '04')
                    GROUP BY
                        pdo.descripcion,
                        pdo.tipoconcepto
                ),
                Incidencias AS (
					SELECT * FROM (VALUES
						('Prima Dominical cantidad', 'P', 500, 1),
						('Prima Dominical monto', 'P', 500, 1),
						('Dia Festivo cantidad', 'P', 500, 1),
						('Dia Festivo monto', 'P', 500, 1),
						('Comisiones', 'P', 500, 1),
						('Bono', 'P', 500, 1),
						('Horas Extra Doble cantidad', 'P', 500, 1),
						('Horas Extra Doble monto', 'P', 500, 1),
						('Horas Extra Triple cantidad', 'P', 500, 1),
						('Horas Extra Triple monto', 'P', 500, 1),
						('Pago adicional', 'P', 500, 1),
						('Premio puntualidad', 'P', 500, 1),
                        ('Descuentos', 'D', 500, 2),
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

                DECLARE @idNominaGapeIncidencia INT;

                SET @idPeriodo = $idPeriodo;
                SET @idTipoPeriodo = $idTipoPeriodo;

                SET @idEmpleadoInicial = $idEmpleadoInicial;
                SET @idEmpleadoFinal = $idEmpleadoFinal;

                SET @idNominaGapeEmpresa = $idNominaGapeEmpresa;

                SET @idNominaGapeIncidencia = $idNominaGapeIncidencia;

                ' + '

                ;WITH MovimientosRetro AS (
                    SELECT idempleado, idperiodo, his.idconcepto, valor, importetotal, con.descripcion, con.tipoconcepto
                    FROM Nom10007 AS his
                    INNER JOIN nom10004 con
                        ON his.idconcepto = con.idconcepto
                    WHERE
                        valor > 0
                        AND idperiodo = @idPeriodo
                        AND con.tipoconcepto IN (''P'')
                        AND con.descripcion LIKE ''%retroactivo%''

                    UNION ALL

                    SELECT idempleado, idperiodo, actual.idconcepto, valor, importetotal, con.descripcion, con.tipoconcepto
                    FROM Nom10008 AS actual
                    INNER JOIN nom10004 con
                        ON actual.idconcepto = con.idconcepto
                    WHERE
                        valor > 0
                        AND idperiodo = @idPeriodo
                        AND con.tipoconcepto IN (''P'')
                        AND con.descripcion LIKE ''%retroactivo%''
                ), ' + '
                tarjetaControl AS (
                    SELECT idempleado, idperiodo, valor, idtarjetaincapacidad, ti.mnemonico AS mnemonico
                    FROM nom10009 AS fi
                    INNER JOIN nom10022 ti
                        ON fi.idtipoincidencia = ti.idtipoincidencia
                    WHERE
                        ti.mnemonico IN (''INC'', ''FINJ'')
                        AND idperiodo = @idPeriodo
                    UNION ALL
                    SELECT idempleado, idperiodo, valor, idtarjetaincapacidad, ti.mnemonico AS mnemonico
                    FROM NOM10010 AS fi
                    INNER JOIN nom10022 ti
                        ON fi.idtipoincidencia = ti.idtipoincidencia
                    WHERE
                        ti.mnemonico IN (''INC'', ''FINJ'')
                        AND idperiodo = @idPeriodo
                ), ' + '
                TarjetaControlAgrupado AS (
                    SELECT
                        idempleado,
                        SUM(CASE WHEN mnemonico = ''INC'' THEN valor ELSE 0 END) AS incapacidad,
                        SUM(CASE WHEN mnemonico = ''FINJ'' THEN valor ELSE 0 END) AS faltas
                    FROM tarjetaControl
                    GROUP BY
                        idempleado
                ), ' + '
                Movimientos AS (
                    SELECT idempleado, idperiodo, his.idconcepto, valor, importetotal, con.descripcion, con.tipoconcepto
                    FROM Nom10007 AS his
					INNER JOIN nom10004 con
                        ON his.idconcepto = con.idconcepto
                    WHERE
                        importetotal > 0
                        AND idperiodo = @idPeriodo
						AND con.tipoconcepto IN (''P'',''D'')
						AND (
							con.descripcion LIKE ''%ahorro%'' -- caja de ahorro
							OR con.descripcion LIKE ''%infonavit%''
							OR con.descripcion LIKE ''%fonacot%''
							OR con.numeroconcepto IN (20) -- prima vacacional
							)
                    UNION ALL
                    SELECT idempleado, idperiodo, actual.idconcepto, valor, importetotal, con.descripcion, con.tipoconcepto
                    FROM Nom10008 AS actual
					INNER JOIN nom10004 con
                        ON actual.idconcepto = con.idconcepto
                    WHERE
                        importetotal > 0
                        AND idperiodo = @idPeriodo
						AND con.tipoconcepto IN (''P'',''D'')
						AND (
							con.descripcion LIKE ''%ahorro%'' -- caja de ahorro
							OR con.descripcion LIKE ''%infonavit%''
							OR con.descripcion LIKE ''%fonacot%''
							OR con.numeroconcepto IN (20) -- prima vacacional
							)
                ), ' + '
                MovimientosSuma AS (
                    SELECT
                        emp.codigoempleado,
                        ISNULL(
                            ROUND(
                                emp.ccampoextranumerico2 *
                                CASE
                                    WHEN empPeriodo.fechaalta < periodo.fechainicio THEN
                                        periodo.diasdepago
                                        + ISNULL(SUM(movP.valor), 0)
                                        - ISNULL(tc.incapacidad, 0)
                                        - ISNULL(tc.faltas, 0)

                                    WHEN empPeriodo.fechaalta BETWEEN periodo.fechainicio AND periodo.fechafin THEN
                                        DATEDIFF(DAY, empPeriodo.fechaalta, periodo.fechafin) + 1
                                        + ISNULL(SUM(movP.valor), 0)
                                        - ISNULL(tc.incapacidad, 0)
                                        - ISNULL(tc.faltas, 0)

                                    ELSE 0
                                END,
                            2),
                        0) AS sueldo,
                        pdo.descripcion,
                        pdo.tipoconcepto AS tipoConcepto,
                        SUM(pdo.valor) AS cantidad,
                        SUM(pdo.importetotal) AS monto,
                        emp.ccampoextranumerico3 AS pension
                    FROM nom10001 emp
                    LEFT JOIN nom10034 empPeriodo
                        ON emp.idempleado = empPeriodo.idempleado
							AND empPeriodo.cidperiodo = @idPeriodo
					LEFT JOIN nom10002 AS periodo
						ON empPeriodo.cidperiodo = periodo.idperiodo
                    LEFT JOIN Movimientos pdo
                        ON empPeriodo.cidperiodo = pdo.idperiodo
							AND emp.idempleado = pdo.idempleado
					LEFT JOIN MovimientosRetro AS movP
                        ON empPeriodo.idempleado = movP.idempleado
                    LEFT JOIN TarjetaControlAgrupado AS tc
                        ON empPeriodo.idempleado = tc.idempleado
                    WHERE
                        empPeriodo.idtipoperiodo = @idTipoPeriodo
                        AND empPeriodo.idempleado BETWEEN @idEmpleadoInicial AND @idEmpleadoFinal
                        AND empPeriodo.estadoempleado IN (''A'', ''R'')
                        AND emp.TipoRegimen IN (''02'', ''03'', ''04'')
                    GROUP BY
                        emp.codigoempleado,
                        emp.ccampoextranumerico2,
                        empPeriodo.cdiaspagados,
                        pdo.descripcion,
                        pdo.tipoconcepto,
                        emp.ccampoextranumerico3,
                        periodo.diasdepago,
                        empPeriodo.fechaalta,
                        periodo.fechainicio,
                        periodo.fechafin,
                        tc.faltas,
                        tc.incapacidad
                ), ' + '
                IncidenciasNormalizadas AS (
					SELECT
						emp.codigoempleado as codigoempleado,
						x.descripcion AS descripcion,
						''P'' AS tipoConcepto,
						x.valor AS valor,
						emp.ccampoextranumerico2 AS pagoPorDia,
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
                            AND emp.TipoRegimen IN (''02'', ''03'', ''04'')
					CROSS APPLY (VALUES
						(''Bono'',                      ngid.bono),
						(''Comisiones'',                ngid.comision),
						(''Dia Festivo cantidad'',      ngid.cantidad_dias_festivos),
						(''Dia Festivo monto'',         ISNULL(ngid.cantidad_dias_festivos, 0) * emp.ccampoextranumerico2 * 2),
						(''Horas Extra Doble cantidad'', ngid.horas_extra_doble_cantidad),
						(''Horas Extra Doble monto'',    ISNULL(ngid.horas_extra_doble_cantidad, 0) * (emp.ccampoextranumerico2 / 8.0) * 2),
						(''Horas Extra Triple cantidad'', ngid.horas_extra_triple_cantidad),
						(''Horas Extra Triple monto'',    ISNULL(ngid.horas_extra_triple_cantidad, 0) * (emp.ccampoextranumerico2 / 8.0) * 3),
						(''Pago adicional'',             ngid.pago_adicional),
						(''Premio puntualidad'',         ngid.premio_puntualidad),
						(''Prima Dominical cantidad'',   ngid.cantidad_prima_dominical),
						(''Prima Dominical monto'',      ISNULL(ngid.cantidad_prima_dominical, 0) * emp.ccampoextranumerico2 * 0.25)
					) AS x(descripcion, valor)
					WHERE
                        (@idNominaGapeIncidencia IS NULL OR ngi.id = @idNominaGapeIncidencia)
					    AND ngi.id_nomina_gape_empresa = @idNominaGapeEmpresa
					    AND ngid.id_empleado BETWEEN @idEmpleadoInicial AND @idEmpleadoFinal
				), ' + '
                TotalesPorEmpleado AS (
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
                        WHERE descripcion NOT LIKE ''%cantidad%''
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

            /*
            dd([
                'row' => $sql,
            ]);
            */


            $result = DB::connection('sqlsrv_dynamic')->select($sql);

            return $result;
        } catch (\Exception $e) {
            return [];
        }
    }

    private function datosSueldoImss_3(Request $request)
    {
        try {
            $idNominaGapeCliente = $request->id_nomina_gape_cliente;
            $idNominaGapeEmpresa = $request->id_nomina_gape_empresa;

            $idNominaGapeEsquema = $request->id_nomina_gape_esquema;
            $idEsquemaCombinacion = $request->id_esquema;

            $idTipoPeriodo = $request->id_tipo_periodo;
            $idPeriodo = $request->periodo_inicial;

            $idEmpleadoInicial = $request->empleado_inicial;
            $idEmpleadoFinal = $request->empleado_final;

            $sql = "
                DECLARE @idNominaGapeCliente INT;
                DECLARE @idNominaGapeEmpresa INT;

                DECLARE @idNominaGapeEsquema INT;
                DECLARE @idEsquemaCombinacion INT;

                DECLARE @idPeriodo INT;
                DECLARE @idTipoPeriodo INT;

                DECLARE @idEmpleadoInicial INT;
                DECLARE @idEmpleadoFinal INT;

                SET @idNominaGapeCliente = $idNominaGapeCliente;
                SET @idNominaGapeEmpresa = $idNominaGapeEmpresa;

                SET @idNominaGapeEsquema = $idNominaGapeEsquema;
                SET @idEsquemaCombinacion = $idEsquemaCombinacion;

                SET @idPeriodo = $idPeriodo;
                SET @idTipoPeriodo = $idTipoPeriodo;

                SET @idEmpleadoInicial = $idEmpleadoInicial;
                SET @idEmpleadoFinal = $idEmpleadoFinal;


                DECLARE @cols NVARCHAR(MAX),
                        @query NVARCHAR(MAX);

                ;WITH Previsiones AS (
                    SELECT
                        ngcp.id_concepto AS idconcepto
                    FROM [becma-core2].dbo.nomina_gape_empresa_periodo_combinacion_parametrizacion AS ngepcp
                    INNER JOIN [becma-core2].dbo.nomina_gape_combinacion_prevision AS ngcp
                        ON ngepcp.id = ngcp.nomina_gape_empresa_periodo_combinacion_parametrizacion
                    WHERE
                        ngepcp.id_nomina_gape_cliente = @idNominaGapeCliente
                        AND ngepcp.id_nomina_gape_empresa = @idNominaGapeEmpresa
                        AND ngepcp.id_nomina_gape_cliente_esquema_combinacion = @idEsquemaCombinacion
                        AND (
                                ngepcp.id_nomina_gape_tipo_periodo = @idTipoPeriodo
                                OR ngepcp.idtipoperiodo = @idTipoPeriodo
                            )
                ),
                Movimientos AS (
                    SELECT idempleado, idperiodo, his.idconcepto, valor, importetotal, con.descripcion, con.tipoconcepto
                    FROM Nom10007 AS his
                    INNER JOIN nom10004 con
                        ON his.idconcepto = con.idconcepto
                    LEFT JOIN Previsiones p
		                ON con.idconcepto = p.idconcepto
                    WHERE
                        importetotal > 0
                        AND idperiodo = @idPeriodo
                        AND (
                        -- (Sueldo, Vacaciones, Prima vacacional, Retroactivos)
                        (con.tipoconcepto = 'P'
                            AND con.numeroconcepto IN (1, 19, 20, 16)
                            OR p.idconcepto IS NOT NULL
                        )
                        OR
                        (con.tipoconcepto = 'D' AND (
                                con.descripcion LIKE '%ahorro%' -- caja de ahorro
                                OR con.descripcion LIKE '%infonavit%'
                                OR con.descripcion LIKE '%fonacot%'
                                OR con.descripcion LIKE '%alimenticia%'
                                OR con.descripcion LIKE '%sindical%'
                                OR con.numeroconcepto IN (52,35) -- Imss, isr
                                ))
                        )

                    UNION ALL

                    SELECT idempleado, idperiodo, actual.idconcepto, valor, importetotal, con.descripcion, con.tipoconcepto
                    FROM Nom10008 AS actual
                    INNER JOIN nom10004 con
                        ON actual.idconcepto = con.idconcepto
                    LEFT JOIN Previsiones p
		                ON con.idconcepto = p.idconcepto
                    WHERE
                        importetotal > 0
                        AND idperiodo = @idPeriodo
                        AND (
                        -- (Sueldo, Vacaciones, Prima vacacional, Retroactivos)
                        (con.tipoconcepto = 'P'
                            AND con.numeroconcepto IN (1, 19, 20, 16)
                            OR p.idconcepto IS NOT NULL
                        )
                        OR
                        (con.tipoconcepto = 'D' AND (
                                con.descripcion LIKE '%ahorro%' -- caja de ahorro
                                OR con.descripcion LIKE '%infonavit%'
                                OR con.descripcion LIKE '%fonacot%'
                                OR con.descripcion LIKE '%alimenticia%'
                                OR con.descripcion LIKE '%sindical%'
                                OR con.numeroconcepto IN (52,35) -- Imss, isr
                                ))
                        )
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
                    WHERE
                        empPeriodo.idtipoperiodo = @idTipoPeriodo
                        AND empPeriodo.idempleado BETWEEN @idEmpleadoInicial AND @idEmpleadoFinal
                        AND empPeriodo.estadoempleado IN ('A', 'R')
                        AND emp.TipoRegimen IN ('02', '03', '04')
                    GROUP BY
                        pdo.descripcion,
                        pdo.tipoconcepto
                ),
                Encabezados AS (
                    SELECT * FROM (VALUES
                        ('TOTAL PERCEPCIONES FISCAL', 'P', 1000, 1),
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
                    DECLARE @idNominaGapeCliente INT;
                    DECLARE @idNominaGapeEmpresa INT;

                    DECLARE @idNominaGapeEsquema INT;
                    DECLARE @idEsquemaCombinacion INT;

                    DECLARE @idPeriodo INT;
                    DECLARE @idTipoPeriodo INT;

                    DECLARE @idEmpleadoInicial INT;
                    DECLARE @idEmpleadoFinal INT;

                    SET @idNominaGapeCliente = $idNominaGapeCliente;
                    SET @idNominaGapeEmpresa = $idNominaGapeEmpresa;

                    SET @idNominaGapeEsquema = $idNominaGapeEsquema;
                    SET @idEsquemaCombinacion = $idEsquemaCombinacion;

                    SET @idPeriodo = $idPeriodo;
                    SET @idTipoPeriodo = $idTipoPeriodo;

                    SET @idEmpleadoInicial = $idEmpleadoInicial;
                    SET @idEmpleadoFinal = $idEmpleadoFinal;

                    ' + '

                    ;WITH Previsiones AS (
                        SELECT
                            ngcp.id_concepto AS idconcepto
                        FROM [becma-core2].dbo.nomina_gape_empresa_periodo_combinacion_parametrizacion AS ngepcp
                        INNER JOIN [becma-core2].dbo.nomina_gape_combinacion_prevision AS ngcp
                            ON ngepcp.id = ngcp.nomina_gape_empresa_periodo_combinacion_parametrizacion
                        WHERE
                            ngepcp.id_nomina_gape_cliente = @idNominaGapeCliente
                            AND ngepcp.id_nomina_gape_empresa = @idNominaGapeEmpresa
                            AND ngepcp.id_nomina_gape_cliente_esquema_combinacion = @idEsquemaCombinacion
                            AND (
                                    ngepcp.id_nomina_gape_tipo_periodo = @idTipoPeriodo
                                    OR ngepcp.idtipoperiodo = @idTipoPeriodo
                                )
                    ), ' + '
                    Movimientos AS (
                        SELECT idempleado, idperiodo, his.idconcepto, valor, importetotal, con.descripcion, con.tipoconcepto
                        FROM Nom10007 AS his
                        INNER JOIN nom10004 con
                            ON his.idconcepto = con.idconcepto
                        LEFT JOIN Previsiones p
		                    ON con.idconcepto = p.idconcepto
                        WHERE
                            importetotal > 0
                            AND idperiodo = @idPeriodo
                            AND (
                            (con.tipoconcepto = ''P''
                                AND con.numeroconcepto IN (1, 19, 20, 16)
                                OR p.idconcepto IS NOT NULL
                            )
                            OR
                            (con.tipoconcepto = ''D'' AND (
                                    con.descripcion LIKE ''%ahorro%''
                                    OR con.descripcion LIKE ''%infonavit%''
                                    OR con.descripcion LIKE ''%fonacot%''
                                    OR con.descripcion LIKE ''%alimenticia%''
                                    OR con.descripcion LIKE ''%sindical%''
                                    OR con.numeroconcepto IN (52,35) -- Imss, isr
                                    ))
                            )
                        UNION ALL
                        SELECT idempleado, idperiodo, actual.idconcepto, valor, importetotal, con.descripcion, con.tipoconcepto
                        FROM Nom10008 AS actual
                        INNER JOIN nom10004 con
                            ON actual.idconcepto = con.idconcepto
                        LEFT JOIN Previsiones p
		                    ON con.idconcepto = p.idconcepto
                        WHERE
                            importetotal > 0
                            AND idperiodo = @idPeriodo
                            AND (
                            (con.tipoconcepto = ''P''
                                AND con.numeroconcepto IN (1, 19, 20, 16)
                                OR p.idconcepto IS NOT NULL
                            )
                            OR
                            (con.tipoconcepto = ''D'' AND (
                                    con.descripcion LIKE ''%ahorro%''
                                    OR con.descripcion LIKE ''%infonavit%''
                                    OR con.descripcion LIKE ''%fonacot%''
                                    OR con.descripcion LIKE ''%alimenticia%''
                                    OR con.descripcion LIKE ''%sindical%''
                                    OR con.numeroconcepto IN (52,35) -- Imss, isr
                                    ))
                            )
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
                        LEFT JOIN Movimientos pdo
                            ON empPeriodo.cidperiodo = pdo.idperiodo
                                AND emp.idempleado = pdo.idempleado
                        WHERE empPeriodo.idtipoperiodo = @idTipoPeriodo
                            AND empPeriodo.estadoempleado IN (''A'', ''R'')
                            AND emp.TipoRegimen IN (''02'', ''03'', ''04'')
                            AND empPeriodo.idempleado BETWEEN @idEmpleadoInicial AND @idEmpleadoFinal
                        GROUP BY
                            emp.codigoempleado,
                            empPeriodo.sueldodiario,
                            empPeriodo.sueldointegrado,
                            pdo.descripcion,
                            pdo.tipoconcepto,
                            emp.ccampoextranumerico3
                    ), ' + '
                    TotalesPorEmpleado AS (
                        SELECT
                            codigoempleado,
                            sd,
                            sdi,
                            SUM(CASE WHEN tipoConcepto = ''P'' THEN monto ELSE 0 END) AS total_percepciones,
                            SUM(CASE WHEN tipoConcepto = ''D'' THEN monto ELSE 0 END) AS total_deducciones,
                            pension AS porcentajePension
                        FROM MovimientosSuma
                        GROUP BY
                            codigoempleado,
                            pension,
                            sdi,
                            sd
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
                            ''TOTAL DEDUCCIONES FISCAL'' AS columna,
                            t.total_deducciones AS valor
                        FROM TotalesPorEmpleado t
                        UNION ALL
                        SELECT
                            t.codigoempleado,
                            t.sd,
                            t.sdi,
                            ''NETO FISCAL'' AS columna,
                            (t.total_percepciones) - (t.total_deducciones) AS valor
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


            /*
            dd([
                'row' => $sql,
            ]);
            */


            //return $sql;
            $result = DB::connection('sqlsrv_dynamic')->select($sql);

            return $result;
        } catch (\Exception $e) {
            return [];
        }
    }

    private function datosSueldoImss_4(Request $request)
    {
        try {
            $idNominaGapeCliente = $request->id_nomina_gape_cliente;
            $idNominaGapeEmpresa = $request->id_nomina_gape_empresa;

            $idNominaGapeEsquema = $request->id_nomina_gape_esquema;
            $idEsquemaCombinacion = $request->id_esquema;

            $idTipoPeriodo = $request->id_tipo_periodo;
            $idPeriodo = $request->periodo_inicial;

            $idEmpleadoInicial = $request->empleado_inicial;
            $idEmpleadoFinal = $request->empleado_final;

            $idNominaGapeIncidencia = $request->id_nomina_gape_incidencia ?? 0;

            $sql = "
                DECLARE @idNominaGapeCliente INT;
                DECLARE @idNominaGapeEmpresa INT;

                DECLARE @idNominaGapeEsquema INT;
                DECLARE @idEsquemaCombinacion INT;

                DECLARE @idPeriodo INT;
                DECLARE @idTipoPeriodo INT;

                DECLARE @idEmpleadoInicial INT;
                DECLARE @idEmpleadoFinal INT;

				DECLARE @idNominaGapeIncidencia INT;

                SET @idNominaGapeCliente = $idNominaGapeCliente;
                SET @idNominaGapeEmpresa = $idNominaGapeEmpresa;

                SET @idNominaGapeEsquema = $idNominaGapeEsquema;
                SET @idEsquemaCombinacion = $idEsquemaCombinacion;

                SET @idPeriodo = $idPeriodo;
                SET @idTipoPeriodo = $idTipoPeriodo;

                SET @idEmpleadoInicial = $idEmpleadoInicial;
                SET @idEmpleadoFinal = $idEmpleadoFinal;

				SET @idNominaGapeIncidencia = $idNominaGapeIncidencia;
                DECLARE @cols NVARCHAR(MAX),
                        @sql  NVARCHAR(MAX);
                ;WITH ParamConfig AS (
                    SELECT
                        nge.esquema AS descripcion
                        , ngce.tope
                        , ngce.orden
                    FROM [becma-core2].[dbo].nomina_gape_cliente_esquema_combinacion AS ngce
                    INNER JOIN [becma-core2].[dbo].nomina_gape_esquema AS nge
                        ON ngce.id_nomina_gape_esquema = nge.id
                    WHERE
                        ngce.id_nomina_gape_cliente = @idNominaGapeCliente
                        AND ngce.id_nomina_gape_empresa = @idNominaGapeEmpresa
                        AND ngce.combinacion = @idEsquemaCombinacion
                        AND ngce.orden > 1
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
                titulos AS (
                    SELECT descripcion AS descripcion, orden FROM Encabezados
                    UNION ALL
                    SELECT descripcion, orden FROM ParamConfig
                )
                SELECT @cols = STUFF((
                    SELECT ', ' + QUOTENAME(descripcion)
                    FROM titulos
                    ORDER BY orden
                    FOR XML PATH(''), TYPE
                ).value('.', 'NVARCHAR(MAX)'), 1, 2, '');

                SET @sql = CAST('' AS NVARCHAR(MAX)) + '
                DECLARE @idNominaGapeCliente INT;
                DECLARE @idNominaGapeEmpresa INT;

                DECLARE @idNominaGapeEsquema INT;
                DECLARE @idEsquemaCombinacion INT;

                DECLARE @idPeriodo INT;
                DECLARE @idTipoPeriodo INT;

                DECLARE @idEmpleadoInicial INT;
                DECLARE @idEmpleadoFinal INT;

				DECLARE @idNominaGapeIncidencia INT;

                SET @idNominaGapeCliente = $idNominaGapeCliente;
                SET @idNominaGapeEmpresa = $idNominaGapeEmpresa;

                SET @idNominaGapeEsquema = $idNominaGapeEsquema;
                SET @idEsquemaCombinacion = $idEsquemaCombinacion;

                SET @idPeriodo = $idPeriodo;
                SET @idTipoPeriodo = $idTipoPeriodo;

                SET @idEmpleadoInicial = $idEmpleadoInicial;
                SET @idEmpleadoFinal = $idEmpleadoFinal;

				SET @idNominaGapeIncidencia = $idNominaGapeIncidencia;

                ' + '

                ;WITH MovimientosRetro AS (
                        SELECT idempleado, idperiodo, his.idconcepto, valor, importetotal, con.descripcion, con.tipoconcepto
                        FROM Nom10007 AS his
                            INNER JOIN nom10004 con
                                ON his.idconcepto = con.idconcepto
                        WHERE
                            valor > 0
                            AND idperiodo = @idPeriodo
                            AND con.tipoconcepto IN (''P'')
                            AND con.descripcion LIKE ''%retroactivo%''
                        UNION ALL
                        SELECT idempleado, idperiodo, actual.idconcepto, valor, importetotal, con.descripcion, con.tipoconcepto
                        FROM Nom10008 AS actual
                        INNER JOIN nom10004 con
                            ON actual.idconcepto = con.idconcepto
                        WHERE
                            valor > 0
                            AND idperiodo = @idPeriodo
                            AND con.tipoconcepto IN (''P'')
                            AND con.descripcion LIKE ''%retroactivo%''
                    ), ' + '
                    tarjetaControl AS (
                        SELECT idempleado, idperiodo, valor, idtarjetaincapacidad, ti.mnemonico AS mnemonico
                        FROM nom10009 AS fi
                        INNER JOIN nom10022 ti
                            ON fi.idtipoincidencia = ti.idtipoincidencia
                        WHERE
                            ti.mnemonico IN (''INC'', ''FINJ'')
                            AND idperiodo = @idPeriodo
                        UNION ALL
                        SELECT idempleado, idperiodo, valor, idtarjetaincapacidad, ti.mnemonico AS mnemonico
                        FROM NOM10010 AS fi
                        INNER JOIN nom10022 ti
                            ON fi.idtipoincidencia = ti.idtipoincidencia
                        WHERE
                            ti.mnemonico IN (''INC'', ''FINJ'')
                            AND idperiodo = @idPeriodo
                    ), ' + '
                    TarjetaControlAgrupado AS (
                        SELECT
                            idempleado,
                            SUM(CASE WHEN mnemonico = ''INC'' THEN valor ELSE 0 END) AS incapacidad,
                            SUM(CASE WHEN mnemonico = ''FINJ'' THEN valor ELSE 0 END) AS faltas
                        FROM tarjetaControl
                        GROUP BY
                            idempleado
                    ), ' + '
                    Movimientos AS (
                        SELECT idempleado, idperiodo, his.idconcepto, valor, importetotal, con.descripcion, con.tipoconcepto, con.numeroconcepto
                        FROM Nom10007 AS his
                        INNER JOIN nom10004 con
                            ON his.idconcepto = con.idconcepto
                        WHERE
                            importetotal > 0
                            AND idperiodo = @idPeriodo
                            AND con.tipoconcepto IN (''P'',''D'')
                        UNION ALL
                        SELECT idempleado, idperiodo, actual.idconcepto, valor, importetotal, con.descripcion, con.tipoconcepto, con.numeroconcepto
                        FROM Nom10008 AS actual
                        INNER JOIN nom10004 con
                            ON actual.idconcepto = con.idconcepto
                        WHERE
                            importetotal > 0
                            AND idperiodo = @idPeriodo
                            AND con.tipoconcepto IN (''P'',''D'')
                    ), ' + '
                    MovimientosSumaQ2 AS (
                        SELECT
                            emp.codigoempleado,
                            ISNULL(
                                ROUND(
                                    emp.ccampoextranumerico2 *
                                    CASE
                                        WHEN empPeriodo.fechaalta < periodo.fechainicio THEN
                                            periodo.diasdepago
                                            + ISNULL(SUM(movP.valor), 0)
                                            - ISNULL(tc.incapacidad, 0)
                                            - ISNULL(tc.faltas, 0)

                                        WHEN empPeriodo.fechaalta BETWEEN periodo.fechainicio AND periodo.fechafin THEN
                                            DATEDIFF(DAY, empPeriodo.fechaalta, periodo.fechafin) + 1
                                            + ISNULL(SUM(movP.valor), 0)
                                            - ISNULL(tc.incapacidad, 0)
                                            - ISNULL(tc.faltas, 0)

                                        ELSE 0
                                    END,
                                2),
                            0) AS sueldo,
                            pdo.descripcion,
                            pdo.tipoconcepto AS tipoConcepto,
                            SUM(pdo.valor) AS cantidad,
                            SUM(pdo.importetotal) AS monto,
                            emp.ccampoextranumerico3 AS pension
                        FROM nom10001 emp
                        INNER JOIN nom10034 empPeriodo
                            ON emp.idempleado = empPeriodo.idempleado
                                AND empPeriodo.cidperiodo = @idPeriodo
                        INNER JOIN nom10002 AS periodo
                            ON empPeriodo.cidperiodo = periodo.idperiodo
                        LEFT JOIN Movimientos pdo
                            ON empPeriodo.cidperiodo = pdo.idperiodo
                                AND emp.idempleado = pdo.idempleado
                                AND (
                                    pdo.descripcion LIKE ''%ahorro%'' -- caja de ahorro
                                    OR pdo.descripcion LIKE ''%infonavit%''
                                    OR pdo.descripcion LIKE ''%fonacot%''
                                    OR pdo.numeroconcepto IN (20) -- prima vacacional
                                )
                        LEFT JOIN MovimientosRetro AS movP
                            ON empPeriodo.idempleado = movP.idempleado
                        LEFT JOIN TarjetaControlAgrupado AS tc
                            ON empPeriodo.idempleado = tc.idempleado
                        WHERE
                            empPeriodo.idtipoperiodo = @idTipoPeriodo
                            AND empPeriodo.estadoempleado IN (''A'', ''R'')
                            AND emp.TipoRegimen IN (''02'', ''03'', ''04'')
                            AND empPeriodo.idempleado BETWEEN @idEmpleadoInicial AND @idEmpleadoFinal
                        GROUP BY
                            emp.codigoempleado,
                            emp.ccampoextranumerico2,
                            empPeriodo.cdiaspagados,
                            pdo.descripcion,
                            pdo.tipoconcepto,
                            emp.ccampoextranumerico3,
                            periodo.diasdepago,
                            empPeriodo.fechaalta,
                            periodo.fechainicio,
                            periodo.fechafin,
                            tc.faltas,
                            tc.incapacidad
                    ), ' + '
                    IncidenciasNormalizadasQ2 AS (
                        SELECT
                            emp.codigoempleado as codigoempleado,
                            x.descripcion AS descripcion,
                            ''P'' AS tipoConcepto,
                            x.valor AS valor,
                            emp.ccampoextranumerico2 AS pagoPorDia,
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
                            (''Bono'',                      ngid.bono),
                            (''Comisiones'',                ngid.comision),
                            (''Dia Festivo cantidad'',      ngid.cantidad_dias_festivos),
                            (''Dia Festivo monto'',         ISNULL(ngid.cantidad_dias_festivos, 0) * emp.ccampoextranumerico2 * 2),
                            (''Horas Extra Doble cantidad'', ngid.horas_extra_doble_cantidad),
                            (''Horas Extra Doble monto'',    ISNULL(ngid.horas_extra_doble_cantidad, 0) * (emp.ccampoextranumerico2 / 8.0) * 2),
                            (''Horas Extra Triple cantidad'', ngid.horas_extra_triple_cantidad),
                            (''Horas Extra Triple monto'',    ISNULL(ngid.horas_extra_triple_cantidad, 0) * (emp.ccampoextranumerico2 / 8.0) * 3),
                            (''Pago adicional'',             ngid.pago_adicional),
                            (''Premio puntualidad'',         ngid.premio_puntualidad),
                            (''Prima Dominical cantidad'',   ngid.cantidad_prima_dominical),
                            (''Prima Dominical monto'',      ISNULL(ngid.cantidad_prima_dominical, 0) * emp.ccampoextranumerico2 * 0.25)
                        ) AS x(descripcion, valor)
                        WHERE
                            (@idNominaGapeIncidencia IS NULL OR ngi.id = @idNominaGapeIncidencia)
                            AND ngi.id_nomina_gape_empresa = @idNominaGapeEmpresa
                            AND ngid.id_empleado BETWEEN @idEmpleadoInicial AND @idEmpleadoFinal
                            AND emp.TipoRegimen IN (''02'', ''03'', ''04'')
                    ), ' + '
                    PreTotalesPorEmpleadoGeneralQ2 AS (
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
                            WHERE
                                descripcion NOT LIKE ''%cantidad%''
                        ) AS x
                        GROUP BY
                            codigoempleado,
                            pension
                    ), ' + '
                    PensionGeneralQ2 AS (
                        SELECT
                            codigoempleado,
                            (CASE WHEN porcentajePension > 0 THEN (sueldo + total_percepciones_sin_sueldo) * (porcentajePension / 100) ELSE 0 END) pension_q2
                        FROM PreTotalesPorEmpleadoGeneralQ2
                        WHERE porcentajePension > 0
                    ), ' + '
                    TotalesPorEmpleadoGeneralQ2 AS (
                        SELECT
                            codigoempleado,
                            sueldo,
                            sueldo + total_percepciones_sin_sueldo AS total_percepciones,
                            total_deducciones + (CASE WHEN porcentajePension > 0 THEN (sueldo + total_percepciones_sin_sueldo) * (porcentajePension / 100) ELSE 0 END) total_deducciones,
                            (sueldo + total_percepciones_sin_sueldo) - (total_deducciones + CASE WHEN porcentajePension > 0 THEN (sueldo + total_percepciones_sin_sueldo) * (porcentajePension / 100) ELSE 0 END) AS neto_a_pagar
                        FROM PreTotalesPorEmpleadoGeneralQ2
                    ), ' + '
                    Previsiones AS (
                        SELECT
                            ngcp.id_concepto AS idconcepto
                        FROM [becma-core2].dbo.nomina_gape_empresa_periodo_combinacion_parametrizacion AS ngepcp
                        INNER JOIN [becma-core2].dbo.nomina_gape_combinacion_prevision AS ngcp
                            ON ngepcp.id = ngcp.nomina_gape_empresa_periodo_combinacion_parametrizacion
                        WHERE
                            ngepcp.id_nomina_gape_cliente = @idNominaGapeCliente
                            AND ngepcp.id_nomina_gape_empresa = @idNominaGapeEmpresa
                            AND ngepcp.id_nomina_gape_cliente_esquema_combinacion = @idEsquemaCombinacion
                            AND (
                                    ngepcp.id_nomina_gape_tipo_periodo = @idTipoPeriodo
                                    OR ngepcp.idtipoperiodo = @idTipoPeriodo
                                )
                    ), ' + '
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
                            LEFT JOIN Movimientos pdo
                                ON empPeriodo.cidperiodo = pdo.idperiodo
                                    AND emp.idempleado = pdo.idempleado
                                    AND (
                                            -- (Sueldo, Vacaciones, Prima vacacional, Retroactivos)
                                            (pdo.tipoconcepto = ''P''
                                                AND pdo.numeroconcepto IN (1, 19, 20, 16)
                                                OR EXISTS (
                                                    SELECT 1
                                                    FROM Previsiones p
                                                    WHERE p.idconcepto = pdo.idconcepto
                                                )
                                            )
                                            OR ' + '
                                            (
                                                pdo.tipoconcepto = ''D''
                                                AND (
                                                    pdo.descripcion LIKE ''%ahorro%'' -- caja de ahorro
                                                    OR pdo.descripcion LIKE ''%infonavit%''
                                                    OR pdo.descripcion LIKE ''%fonacot%''
                                                    OR pdo.descripcion LIKE ''%alimenticia%''
                                                    OR pdo.descripcion LIKE ''%sindical%''
                                                    OR pdo.numeroconcepto IN (52,35) -- Imss, isr
                                                    )
                                            )
                                        )
                            WHERE
                                empPeriodo.idtipoperiodo = @idTipoPeriodo
                                AND empPeriodo.estadoempleado IN (''A'', ''R'')
                                AND emp.TipoRegimen IN (''02'', ''03'', ''04'')
                                AND empPeriodo.idempleado BETWEEN @idEmpleadoInicial AND @idEmpleadoFinal

                            GROUP BY
                                emp.codigoempleado,
                                empPeriodo.sueldodiario,
                                empPeriodo.sueldointegrado,
                                pdo.descripcion,
                                pdo.tipoconcepto,
                                emp.ccampoextranumerico3
                    ), ' + '
                    PensionGeneralQ3 AS (
                        SELECT
                            codigoempleado,
                            SUM(CASE WHEN tipoConcepto = ''D'' AND descripcion LIKE ''%alimenticia%'' THEN monto ELSE 0 END) AS pension_q3
                        FROM MovimientosSumaQ3
                        WHERE
                            pension > 0
                        GROUP BY
                            codigoempleado,
                            pension
                    ), ' + '
                    PensionResultado AS (
                        SELECT
                            q2.codigoempleado,
                            ISNULL(q2.pension_q2, 0) - ISNULL(q3.pension_q3, 0) AS pension_restante
                        FROM PensionGeneralQ2 q2
                        LEFT JOIN PensionGeneralQ3 q3
                            ON q2.codigoempleado = q3.codigoempleado
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
                            GROUP BY
                                codigoempleado,
                                pension,
                                sdi,
                                sd
                    ), ' + '
                    preTotalesProrrateo AS (
                        SELECT
                            q2.codigoempleado,
                            (q2.neto_a_pagar - (q3.total_percepciones -  q3.total_deducciones)) + ISNULL(pr.pension_restante, 0) AS excedente
                        FROM TotalesPorEmpleadoGeneralQ2 q2
                        LEFT JOIN TotalesPorEmpleadoQ3 q3
                            ON q2.codigoempleado = q3.codigoempleado
                        LEFT JOIN PensionResultado pr
                            ON q3.codigoempleado = pr.codigoempleado
                    ), ' + '
                    ParamConfig AS (
                        SELECT
                            nge.esquema AS concepto
                            , ngce.tope
                            , ngce.orden
                        FROM [becma-core2].[dbo].nomina_gape_cliente_esquema_combinacion AS ngce
                        INNER JOIN [becma-core2].[dbo].nomina_gape_esquema AS nge
                            ON ngce.id_nomina_gape_esquema = nge.id
                        WHERE
                            ngce.id_nomina_gape_cliente = @idNominaGapeCliente
                            AND ngce.id_nomina_gape_empresa = @idNominaGapeEmpresa
                            AND ngce.combinacion = @idEsquemaCombinacion
                            AND ngce.orden > 1
                    ), ' + '
                    BaseProrrateo AS (
                        SELECT
                            p.codigoempleado,
                            CAST(p.excedente AS DECIMAL(18,2)) AS netoAPagar,
                            c.concepto,
                            c.tope,
                            c.orden,
                            ROW_NUMBER() OVER (PARTITION BY p.codigoempleado ORDER BY c.orden) AS rn
                        FROM preTotalesProrrateo p
                        CROSS JOIN ParamConfig c
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
                        WHERE
                            b.rn = 1

                        UNION ALL ' + '

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
                        GROUP BY
                            codigoempleado
                    ), ' + '
                    PensionExcedentes AS (
                        SELECT
                            q3.codigoempleado,
                            ''Pension Excedente'' AS concepto,
                             ISNULL(pr.pension_restante, 0) AS pension_excedente
                        FROM TotalesPorEmpleadoQ3 q3
						LEFT JOIN PensionResultado pr
                            ON pr.codigoempleado = q3.codigoempleado
                    ), ' + '
                    DeduccionExcedentes AS (
                        SELECT
                            pe.codigoempleado,
                            ISNULL(SUM(pe.pension_excedente) , 0) AS total_deducciones_excedente
                        FROM TotalesPorEmpleadoQ3 q3
						LEFT JOIN PensionExcedentes pe
							ON pe.codigoempleado = q3.codigoempleado
						GROUP BY
                            pe.codigoempleado
                    ), ' + '
                    NetoExcedentes AS (
                        SELECT
                            pe.codigoempleado,
                            (pe.total_percepciones_excedente - de.total_deducciones_excedente) AS neto_excedente
                        FROM PercepcionesExcedentes pe
                        LEFT JOIN DeduccionExcedentes de
                            ON pe.codigoempleado = de.codigoempleado
                    ), ' + '
                    NetoTotalAPagar AS (
                        SELECT
                            ne.codigoempleado,
                            (q3.total_percepciones - q3.total_deducciones) + (ne.neto_excedente) AS neto_total_a_pagar
                        FROM NetoExcedentes ne
                        LEFT JOIN TotalesPorEmpleadoQ3 AS q3
                            ON ne.codigoempleado = q3.codigoempleado
                    ), ' + '
                    ProrrateoFinal AS (
                        -- conceptos del prorrateo (columnas dinámicas)
                        SELECT
                            codigoempleado,
                            concepto,
                            monto_asignado
                        FROM ProrrateoRecursivo
                        UNION ALL

                        -- TOTAL PERCEPCION EXCEDENTE
                        SELECT
                            codigoempleado,
                            ''TOTAL PERCEPCION EXCEDENTE'' AS concepto,
                            total_percepciones_excedente AS monto_asignado
                        FROM PercepcionesExcedentes

                        UNION ALL

                        -- PENSION EXCEDENTE
                        SELECT
                            pe.codigoempleado AS codigoempleado,
                            pe.concepto AS concepto,
                            pe.pension_excedente AS monto
                        FROM PensionExcedentes pe

                        UNION ALL

                        -- TOTAL DEDUCCION EXCEDENTE
                        SELECT
                            codigoempleado,
                            ''TOTAL DEDUCCION EXCEDENTE'' AS concepto,
                            total_deducciones_excedente AS monto_asignado
                        FROM DeduccionExcedentes

                        UNION ALL

                        -- NETO EXCEDENTE
                        SELECT
                            codigoempleado,
                            ''NETO EXCEDENTE'' AS concepto,
                            neto_excedente AS monto_asignado
                        FROM NetoExcedentes

                        UNION ALL

                        -- NETO TOTAL A PAGAR
                        SELECT
                            codigoempleado,
                            ''NETO TOTAL A PAGAR'' AS concepto,
                            neto_total_a_pagar AS monto_asignado
                        FROM NetoTotalAPagar
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


            dd([
                'row' => $sql,
            ]);


            //return $sql;
            $result = DB::connection('sqlsrv_dynamic')->select($sql);

            return $result;
        } catch (\Exception $e) {
            return [];
        }
    }

    private function datosSueldoImss_5(Request $request)
    {
        try {
            $idNominaGapeCliente = $request->id_nomina_gape_cliente;
            $idNominaGapeEmpresa = $request->id_nomina_gape_empresa;

            $idNominaGapeEsquema = $request->id_nomina_gape_esquema;
            $idEsquemaCombinacion = $request->id_esquema;

            $idTipoPeriodo = $request->id_tipo_periodo;
            $idPeriodo = $request->periodo_inicial;

            $idEmpleadoInicial = $request->empleado_inicial;
            $idEmpleadoFinal = $request->empleado_final;

            $idNominaGapeIncidencia = $request->id_nomina_gape_incidencia ?? 0;

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
                DECLARE @idNominaGapeCliente INT;
                DECLARE @idNominaGapeEmpresa INT;

                DECLARE @idNominaGapeEsquema INT;
                DECLARE @idEsquemaCombinacion INT;

                DECLARE @idPeriodo INT;
                DECLARE @idTipoPeriodo INT;

                DECLARE @idEmpleadoInicial INT;
                DECLARE @idEmpleadoFinal INT;

				DECLARE @idNominaGapeIncidencia INT;

                SET @idNominaGapeCliente = $idNominaGapeCliente;
                SET @idNominaGapeEmpresa = $idNominaGapeEmpresa;

                SET @idNominaGapeEsquema = $idNominaGapeEsquema;
                SET @idEsquemaCombinacion = $idEsquemaCombinacion;

                SET @idPeriodo = $idPeriodo;
                SET @idTipoPeriodo = $idTipoPeriodo;

                SET @idEmpleadoInicial = $idEmpleadoInicial;
                SET @idEmpleadoFinal = $idEmpleadoFinal;

				SET @idNominaGapeIncidencia = $idNominaGapeIncidencia;

                ;WITH MovimientosRetro AS (
                        SELECT idempleado, idperiodo, his.idconcepto, valor, importetotal, con.descripcion, con.tipoconcepto
                        FROM Nom10007 AS his
                            INNER JOIN nom10004 con
                                ON his.idconcepto = con.idconcepto
                        WHERE
                            valor > 0
                            AND idperiodo = @idPeriodo
                            AND con.tipoconcepto IN (''P'')
                            AND con.descripcion LIKE ''%retroactivo%''

                        UNION ALL

                        SELECT idempleado, idperiodo, actual.idconcepto, valor, importetotal, con.descripcion, con.tipoconcepto
                        FROM Nom10008 AS actual
                        INNER JOIN nom10004 con
                            ON actual.idconcepto = con.idconcepto
                        WHERE
                            valor > 0
                            AND idperiodo = @idPeriodo
                            AND con.tipoconcepto IN (''P'')
                            AND con.descripcion LIKE ''%retroactivo%''
                    ), ' + '
                    tarjetaControl AS (
                        SELECT idempleado, idperiodo, valor, idtarjetaincapacidad, ti.mnemonico AS mnemonico
                        FROM nom10009 AS fi
                        INNER JOIN nom10022 ti
                            ON fi.idtipoincidencia = ti.idtipoincidencia
                        WHERE
                            ti.mnemonico IN (''INC'', ''FINJ'')
                            AND idperiodo = @idPeriodo
                        UNION ALL
                        SELECT idempleado, idperiodo, valor, idtarjetaincapacidad, ti.mnemonico AS mnemonico
                        FROM NOM10010 AS fi
                        INNER JOIN nom10022 ti
                            ON fi.idtipoincidencia = ti.idtipoincidencia
                        WHERE
                            ti.mnemonico IN (''INC'', ''FINJ'')
                            AND idperiodo = @idPeriodo
                    ), ' + '
                    TarjetaControlAgrupado AS (
                        SELECT
                            idempleado,
                            SUM(CASE WHEN mnemonico = ''INC'' THEN valor ELSE 0 END) AS incapacidad,
                            SUM(CASE WHEN mnemonico = ''FINJ'' THEN valor ELSE 0 END) AS faltas
                        FROM tarjetaControl
                        GROUP BY
                            idempleado
                    ), ' + '
                    Movimientos AS (
                        SELECT idempleado, idperiodo, his.idconcepto, valor, importetotal, con.descripcion, con.tipoconcepto, con.numeroconcepto
                        FROM Nom10007 AS his
                        INNER JOIN nom10004 con
                            ON his.idconcepto = con.idconcepto
                        WHERE
                            importetotal > 0
                            AND idperiodo = @idPeriodo
                            AND con.tipoconcepto IN (''P'',''D'')
                        UNION ALL
                        SELECT idempleado, idperiodo, actual.idconcepto, valor, importetotal, con.descripcion, con.tipoconcepto, con.numeroconcepto
                        FROM Nom10008 AS actual
                        INNER JOIN nom10004 con
                            ON actual.idconcepto = con.idconcepto
                        WHERE
                            importetotal > 0
                            AND idperiodo = @idPeriodo
                            AND con.tipoconcepto IN (''P'',''D'')
                    ), ' + '
                    MovimientosSumaQ2 AS (
                        SELECT
                            emp.codigoempleado,
                            ISNULL(
                                ROUND(
                                    emp.ccampoextranumerico2 *
                                    CASE
                                        WHEN empPeriodo.fechaalta < periodo.fechainicio THEN
                                            periodo.diasdepago
                                            + ISNULL(SUM(movP.valor), 0)
                                            - ISNULL(tc.incapacidad, 0)
                                            - ISNULL(tc.faltas, 0)

                                        WHEN empPeriodo.fechaalta BETWEEN periodo.fechainicio AND periodo.fechafin THEN
                                            DATEDIFF(DAY, empPeriodo.fechaalta, periodo.fechafin) + 1
                                            + ISNULL(SUM(movP.valor), 0)
                                            - ISNULL(tc.incapacidad, 0)
                                            - ISNULL(tc.faltas, 0)

                                        ELSE 0
                                    END,
                                2),
                            0) AS sueldo,
                            pdo.descripcion,
                            pdo.tipoconcepto AS tipoConcepto,
                            SUM(pdo.valor) AS cantidad,
                            SUM(pdo.importetotal) AS monto,
                            emp.ccampoextranumerico3 AS pension
                        FROM nom10001 emp
                        INNER JOIN nom10034 empPeriodo
                            ON emp.idempleado = empPeriodo.idempleado
                                AND empPeriodo.cidperiodo = @idPeriodo
                        INNER JOIN nom10002 AS periodo
                            ON empPeriodo.cidperiodo = periodo.idperiodo
                        LEFT JOIN Movimientos pdo
                            ON empPeriodo.cidperiodo = pdo.idperiodo
                                AND emp.idempleado = pdo.idempleado
                                AND (
                                    pdo.descripcion LIKE ''%ahorro%'' -- caja de ahorro
                                    OR pdo.descripcion LIKE ''%infonavit%''
                                    OR pdo.descripcion LIKE ''%fonacot%''
                                    OR pdo.numeroconcepto IN (20) -- prima vacacional
                                )
                        LEFT JOIN MovimientosRetro AS movP
                            ON empPeriodo.idempleado = movP.idempleado
                        LEFT JOIN TarjetaControlAgrupado AS tc
                            ON empPeriodo.idempleado = tc.idempleado
                        WHERE
                            empPeriodo.idtipoperiodo = @idTipoPeriodo
                            AND empPeriodo.estadoempleado IN (''A'', ''R'')
                            AND emp.TipoRegimen IN (''02'', ''03'', ''04'')
                            AND empPeriodo.idempleado BETWEEN @idEmpleadoInicial AND @idEmpleadoFinal
                        GROUP BY
                            emp.codigoempleado,
                            emp.ccampoextranumerico2,
                            empPeriodo.cdiaspagados,
                            pdo.descripcion,
                            pdo.tipoconcepto,
                            emp.ccampoextranumerico3,
                            periodo.diasdepago,
                            empPeriodo.fechaalta,
                            periodo.fechainicio,
                            periodo.fechafin,
                            tc.faltas,
                            tc.incapacidad
                    ), ' + '
                    IncidenciasNormalizadasQ2 AS (
                        SELECT
                            emp.codigoempleado as codigoempleado,
                            x.descripcion AS descripcion,
                            ''P'' AS tipoConcepto,
                            x.valor AS valor,
                            emp.ccampoextranumerico2 AS pagoPorDia,
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
                            (''Bono'',                      ngid.bono),
                            (''Comisiones'',                ngid.comision),
                            (''Dia Festivo cantidad'',      ngid.cantidad_dias_festivos),
                            (''Dia Festivo monto'',         ISNULL(ngid.cantidad_dias_festivos, 0) * emp.ccampoextranumerico2 * 2),
                            (''Horas Extra Doble cantidad'', ngid.horas_extra_doble_cantidad),
                            (''Horas Extra Doble monto'',    ISNULL(ngid.horas_extra_doble_cantidad, 0) * (emp.ccampoextranumerico2 / 8.0) * 2),
                            (''Horas Extra Triple cantidad'', ngid.horas_extra_triple_cantidad),
                            (''Horas Extra Triple monto'',    ISNULL(ngid.horas_extra_triple_cantidad, 0) * (emp.ccampoextranumerico2 / 8.0) * 3),
                            (''Pago adicional'',             ngid.pago_adicional),
                            (''Premio puntualidad'',         ngid.premio_puntualidad),
                            (''Prima Dominical cantidad'',   ngid.cantidad_prima_dominical),
                            (''Prima Dominical monto'',      ISNULL(ngid.cantidad_prima_dominical, 0) * emp.ccampoextranumerico2 * 0.25)
                        ) AS x(descripcion, valor)
                        WHERE
                            (@idNominaGapeIncidencia IS NULL OR ngi.id = @idNominaGapeIncidencia)
                            AND ngi.id_nomina_gape_empresa = @idNominaGapeEmpresa
                            AND ngid.id_empleado BETWEEN @idEmpleadoInicial AND @idEmpleadoFinal
                            AND emp.TipoRegimen IN (''02'', ''03'', ''04'')
                    ), ' + '
                    PreTotalesPorEmpleadoGeneralQ2 AS (
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
                            WHERE descripcion NOT LIKE ''%cantidad%''
                        ) AS x
                        GROUP BY
                            codigoempleado,
                            pension
                    ), ' + '
                    PensionGeneralQ2 AS (
                        SELECT
                            codigoempleado,
                            (CASE WHEN porcentajePension > 0 THEN (sueldo + total_percepciones_sin_sueldo) * (porcentajePension / 100) ELSE 0 END) pension_q2
                        FROM PreTotalesPorEmpleadoGeneralQ2
                        WHERE
                            porcentajePension > 0
                    ), ' + '
                    TotalesPorEmpleadoGeneralQ2 AS (
                        SELECT
                            codigoempleado,
                            sueldo,
                            sueldo + total_percepciones_sin_sueldo AS total_percepciones,
                            total_deducciones + (CASE WHEN porcentajePension > 0 THEN (sueldo + total_percepciones_sin_sueldo) * (porcentajePension / 100) ELSE 0 END) total_deducciones,
                            (sueldo + total_percepciones_sin_sueldo) - (total_deducciones + CASE WHEN porcentajePension > 0 THEN (sueldo + total_percepciones_sin_sueldo) * (porcentajePension / 100) ELSE 0 END) AS neto_a_pagar
                        FROM PreTotalesPorEmpleadoGeneralQ2
                    ), ' + '
                    Previsiones AS (
                        SELECT
                            ngcp.id_concepto AS idconcepto
                        FROM [becma-core2].dbo.nomina_gape_empresa_periodo_combinacion_parametrizacion AS ngepcp
                        INNER JOIN [becma-core2].dbo.nomina_gape_combinacion_prevision AS ngcp
                            ON ngepcp.id = ngcp.nomina_gape_empresa_periodo_combinacion_parametrizacion
                        WHERE ngepcp.id_nomina_gape_cliente = @idNominaGapeCliente
                            AND ngepcp.id_nomina_gape_empresa = @idNominaGapeEmpresa
                            AND ngepcp.id_nomina_gape_cliente_esquema_combinacion = @idEsquemaCombinacion
                            AND (
                                    ngepcp.id_nomina_gape_tipo_periodo = @idTipoPeriodo
                                    OR ngepcp.idtipoperiodo = @idTipoPeriodo
                                )
                    ), ' + '
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
                            LEFT JOIN Movimientos pdo
                                ON empPeriodo.cidperiodo = pdo.idperiodo
                                AND emp.idempleado = pdo.idempleado
                                AND (
                                        -- (Sueldo, Vacaciones, Prima vacacional, Retroactivos)
                                        (pdo.tipoconcepto = ''P''
                                            AND pdo.numeroconcepto IN (1, 19, 20, 16)
                                            OR EXISTS (
                                                SELECT 1
                                                FROM Previsiones p
                                                WHERE p.idconcepto = pdo.idconcepto
                                            )
                                        )
                                        OR ' + '
                                        (
                                            pdo.tipoconcepto = ''D''
                                            AND (
                                                pdo.descripcion LIKE ''%ahorro%'' -- caja de ahorro
                                                OR pdo.descripcion LIKE ''%infonavit%''
                                                OR pdo.descripcion LIKE ''%fonacot%''
                                                OR pdo.descripcion LIKE ''%alimenticia%''
                                                OR pdo.descripcion LIKE ''%sindical%''
                                                OR pdo.numeroconcepto IN (52,35) -- Imss, isr
                                                )
                                        )
                                    )
                            WHERE
                                empPeriodo.idtipoperiodo = @idTipoPeriodo
                                AND empPeriodo.estadoempleado IN (''A'', ''R'')
                                AND emp.TipoRegimen IN (''02'', ''03'', ''04'')
                                AND empPeriodo.idempleado BETWEEN @idEmpleadoInicial AND @idEmpleadoFinal
                            GROUP BY
                                emp.codigoempleado,
                                empPeriodo.sueldodiario,
                                empPeriodo.sueldointegrado,
                                pdo.descripcion,
                                pdo.tipoconcepto,
                                emp.ccampoextranumerico3
                    ), ' + '
                    PensionGeneralQ3 AS (
                        SELECT
                            codigoempleado,
                            SUM(CASE WHEN tipoConcepto = ''D'' AND descripcion LIKE ''%alimenticia%'' THEN monto ELSE 0 END) AS pension_q3
                        FROM MovimientosSumaQ3
                        WHERE
                            pension > 0
                        GROUP BY
                            codigoempleado,
                            pension
                    ), ' + '
                    PensionResultado AS (
                        SELECT
                            q2.codigoempleado,
                            ISNULL(q2.pension_q2, 0) - ISNULL(q3.pension_q3, 0) AS pension_restante
                        FROM PensionGeneralQ2 q2
                        LEFT JOIN PensionGeneralQ3 q3
                            ON q2.codigoempleado = q3.codigoempleado
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
                            GROUP BY
                                codigoempleado,
                                pension,
                                sdi,
                                sd
                    ), ' + '
                    preTotalesProrrateo AS (
                        SELECT
                            q2.codigoempleado,
                            (q2.neto_a_pagar - (q3.total_percepciones -  q3.total_deducciones)) + ISNULL(pr.pension_restante, 0) AS excedente
                        FROM TotalesPorEmpleadoGeneralQ2 q2
                        LEFT JOIN TotalesPorEmpleadoQ3 q3
                            ON q2.codigoempleado = q3.codigoempleado
                        LEFT JOIN PensionResultado pr
                            ON q3.codigoempleado = pr.codigoempleado
                    ), ' + '
                    ParamConfig AS (
                        SELECT
                            nge.esquema AS concepto
                            , ngce.tope
                            , ngce.orden
                        FROM [becma-core2].[dbo].nomina_gape_cliente_esquema_combinacion AS ngce
                        INNER JOIN [becma-core2].[dbo].nomina_gape_esquema AS nge
                            ON ngce.id_nomina_gape_esquema = nge.id
                        WHERE
                            ngce.id_nomina_gape_cliente = @idNominaGapeCliente
                            AND ngce.id_nomina_gape_empresa = @idNominaGapeEmpresa
                            AND ngce.combinacion = @idEsquemaCombinacion
                            AND ngce.orden > 1
                    ), ' + '
                    BaseProrrateo AS (
                        SELECT
                            p.codigoempleado,
                            CAST(p.excedente AS DECIMAL(18,2)) AS netoAPagar,
                            c.concepto,
                            c.tope,
                            c.orden,
                            ROW_NUMBER() OVER (PARTITION BY p.codigoempleado ORDER BY c.orden) AS rn
                        FROM preTotalesProrrateo p
                        CROSS JOIN ParamConfig c
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

                        UNION ALL ' + '

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
                        GROUP BY
                            codigoempleado
                    ), ' + '
                    PensionExcedentes AS (
                        SELECT
                            q3.codigoempleado,
                            ''Pension Excedente'' AS concepto,
                                ISNULL(pr.pension_restante, 0) AS pension_excedente
                        FROM TotalesPorEmpleadoQ3 q3
                        LEFT JOIN PensionResultado pr
                            ON pr.codigoempleado = q3.codigoempleado
                    ), ' + '
                    DeduccionExcedentes AS (
                        SELECT
                            pe.codigoempleado,
                            ISNULL(SUM(pe.pension_excedente) , 0) AS total_deducciones_excedente
                        FROM TotalesPorEmpleadoQ3 q3
                        LEFT JOIN PensionExcedentes pe
                            ON pe.codigoempleado = q3.codigoempleado
                        GROUP BY
                            pe.codigoempleado
                    ), ' + '
                    NetoExcedentes AS (
                        SELECT
                            pe.codigoempleado,
                            (pe.total_percepciones_excedente - de.total_deducciones_excedente) AS neto_excedente
                        FROM PercepcionesExcedentes pe
                        LEFT JOIN DeduccionExcedentes de
                            ON pe.codigoempleado = de.codigoempleado
                    ), ' + '
                    NetoTotalAPagar AS (
                        SELECT
                            ne.codigoempleado,
                            (q3.total_percepciones - q3.total_deducciones) + (ne.neto_excedente) AS neto_total_a_pagar
                        FROM NetoExcedentes ne
                        LEFT JOIN TotalesPorEmpleadoQ3 AS q3
                            ON ne.codigoempleado = q3.codigoempleado
                    ), ' + '
                    provisiones AS (
                        SELECT
                            emp.codigoempleado AS codigoempleado,
                            (empPeriodo.sueldodiario * antig.DiasAguinaldo) / 365 * periodo.diasdepago AS provisionAguinaldo,
                            (empPeriodo.sueldodiario * antig.DiasVacaciones * (PorcentajePrima / 100.0)) / 365.0 * periodo.diasdepago AS provisionPrimaVacacional
                        FROM nom10001 emp
                        INNER JOIN nom10034 empPeriodo
                            ON emp.idempleado = empPeriodo.idempleado
                                AND empPeriodo.cidperiodo = @idPeriodo
                        INNER JOIN nom10002 AS periodo
                            ON empPeriodo.cidperiodo = periodo.idperiodo
                                AND periodo.idperiodo = @idPeriodo
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
                        WHERE
                            empPeriodo.cidperiodo = @idPeriodo
                            AND empPeriodo.estadoempleado IN (''A'', ''R'')
                            AND emp.TipoRegimen IN (''02'', ''03'', ''04'')
                            AND empPeriodo.idempleado BETWEEN @idEmpleadoInicial AND @idEmpleadoFinal
                    ), ' + '
                    nomGapeEmpresa AS (
                        SELECT
                            provisiones
                            , fee
                        FROM [becma-core2].dbo.nomina_gape_empresa_periodo_combinacion_parametrizacion
                        WHERE
                            id_nomina_gape_empresa = @idNominaGapeEmpresa
                            AND id_nomina_gape_cliente = @idNominaGapeCliente
                            AND id_nomina_gape_cliente_esquema_combinacion = @idEsquemaCombinacion
                            AND (
                                    id_nomina_gape_tipo_periodo = @idTipoPeriodo
                                    OR idtipoperiodo = @idTipoPeriodo
                                )
                    ), ' + '
                    compensacion AS (
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
						(nta.neto_total_a_pagar + q3.total_deducciones + q2.total_deducciones_excedente)
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
                        INNER JOIN TotalesPorEmpleadoQ3 AS q3
                            ON nta.codigoempleado = q3.codigoempleado
                        INNER JOIN DeduccionExcedentes AS q2
                            ON nta.codigoempleado = q2.codigoempleado
                        CROSS JOIN nomGapeEmpresa nge
                    )
                    SELECT codigoempleado, ' + @cols + '
                    FROM compensacion
                    PIVOT (
                        SUM(monto)
                        FOR concepto IN (' + @cols + ')
                    ) p
                    ORDER BY codigoempleado;
                    ';
                    EXEC(@sql);
            ";

            //return $sql;
            $result = DB::connection('sqlsrv_dynamic')->select($sql);

            return $result;
        } catch (\Exception $e) {
            return [];
        }
    }

    private function datosSueldoImss_6(Request $request)
    {
        try {
            $idNominaGapeCliente = $request->id_nomina_gape_cliente;
            $idNominaGapeEmpresa = $request->id_nomina_gape_empresa;

            $idNominaGapeEsquema = $request->id_nomina_gape_esquema;
            $idEsquemaCombinacion = $request->id_esquema;

            $idTipoPeriodo = $request->id_tipo_periodo;
            $idPeriodo = $request->periodo_inicial;

            $idEmpleadoInicial = $request->empleado_inicial;
            $idEmpleadoFinal = $request->empleado_final;

            $idNominaGapeIncidencia = $request->id_nomina_gape_incidencia ?? 0;

            $sql = "
                DECLARE @cols NVARCHAR(MAX),
                        @sql  NVARCHAR(MAX);
                ;WITH
                Obligaciones AS (
                    SELECT
                        descripcion,
                        numeroconcepto,
                        CASE numeroconcepto
                            WHEN 90 THEN 1
                            WHEN 89 THEN 2
                            WHEN 93 THEN 3
                            WHEN 96 THEN 4
                            WHEN 97 THEN 5
                            WHEN 98 THEN 6
                        END AS orden
                    FROM nom10004
                    WHERE numeroconcepto IN (90, 89, 93, 96, 97, 98)
                ),
                titulos AS (
                    SELECT * FROM (VALUES
                        ('Comision Servicios', 'N', 1000, 100),
                        ('Costo Total',  'N', 2000, 299)
                    ) AS X(descripcion, tipoconcepto, numeroconcepto, orden)
                ),
                TitulosFinales AS (
                    SELECT descripcion, orden
                    FROM titulos

                    UNION ALL

                    SELECT descripcion, orden
                    FROM Obligaciones
                )

                SELECT @cols = STUFF((
                    SELECT ', ' + QUOTENAME(descripcion)
                    FROM TitulosFinales
                    ORDER BY orden
                    FOR XML PATH(''), TYPE
                ).value('.', 'NVARCHAR(MAX)'), 1, 2, '');

                SET @sql = CAST('' AS NVARCHAR(MAX)) + '
                DECLARE @idNominaGapeCliente INT;
                DECLARE @idNominaGapeEmpresa INT;

                DECLARE @idNominaGapeEsquema INT;
                DECLARE @idEsquemaCombinacion INT;

                DECLARE @idPeriodo INT;
                DECLARE @idTipoPeriodo INT;

                DECLARE @idEmpleadoInicial INT;
                DECLARE @idEmpleadoFinal INT;

				DECLARE @idNominaGapeIncidencia INT;

                SET @idNominaGapeCliente = $idNominaGapeCliente;
                SET @idNominaGapeEmpresa = $idNominaGapeEmpresa;

                SET @idNominaGapeEsquema = $idNominaGapeEsquema;
                SET @idEsquemaCombinacion = $idEsquemaCombinacion;

                SET @idPeriodo = $idPeriodo;
                SET @idTipoPeriodo = $idTipoPeriodo;

                SET @idEmpleadoInicial = $idEmpleadoInicial;
                SET @idEmpleadoFinal = $idEmpleadoFinal;

				SET @idNominaGapeIncidencia = $idNominaGapeIncidencia;

                ;WITH MovimientosRetro AS (
                        SELECT idempleado, idperiodo, his.idconcepto, valor, importetotal, con.descripcion, con.tipoconcepto
                        FROM Nom10007 AS his
                        INNER JOIN nom10004 con
                            ON his.idconcepto = con.idconcepto
                        WHERE
                            valor > 0
                            AND idperiodo = @idPeriodo
                            AND con.tipoconcepto IN (''P'')
                            AND con.descripcion LIKE ''%retroactivo%''
                        UNION ALL
                        SELECT idempleado, idperiodo, actual.idconcepto, valor, importetotal, con.descripcion, con.tipoconcepto
                        FROM Nom10008 AS actual
                        INNER JOIN nom10004 con
                            ON actual.idconcepto = con.idconcepto
                        WHERE
                            valor > 0
                            AND idperiodo = @idPeriodo
                            AND con.tipoconcepto IN (''P'')
                            AND con.descripcion LIKE ''%retroactivo%''
                    ), ' + '
                    tarjetaControl AS (
                        SELECT idempleado, idperiodo, valor, idtarjetaincapacidad, ti.mnemonico AS mnemonico
                        FROM nom10009 AS fi
                        INNER JOIN nom10022 ti
                            ON fi.idtipoincidencia = ti.idtipoincidencia
                        WHERE
                            ti.mnemonico IN (''INC'', ''FINJ'')
                            AND idperiodo = @idPeriodo
                        UNION ALL
                        SELECT idempleado, idperiodo, valor, idtarjetaincapacidad, ti.mnemonico AS mnemonico
                        FROM NOM10010 AS fi
                        INNER JOIN nom10022 ti
                            ON fi.idtipoincidencia = ti.idtipoincidencia
                        WHERE
                            ti.mnemonico IN (''INC'', ''FINJ'')
                            AND idperiodo = @idPeriodo
                    ), ' + '
                    TarjetaControlAgrupado AS (
                        SELECT
                            idempleado,
                            SUM(CASE WHEN mnemonico = ''INC'' THEN valor ELSE 0 END) AS incapacidad,
                            SUM(CASE WHEN mnemonico = ''FINJ'' THEN valor ELSE 0 END) AS faltas
                        FROM tarjetaControl
                        GROUP BY
                            idempleado
                    ), ' + '
                    Movimientos AS (
                        SELECT idempleado, idperiodo, his.idconcepto, valor, importetotal, con.descripcion, con.tipoconcepto, con.numeroconcepto
                        FROM Nom10007 AS his
                        INNER JOIN nom10004 con
                            ON his.idconcepto = con.idconcepto
                        WHERE
                            importetotal > 0
                            AND idperiodo = @idPeriodo
                            AND con.tipoconcepto IN (''P'',''D'', ''O'')
                        UNION ALL
                        SELECT idempleado, idperiodo, actual.idconcepto, valor, importetotal, con.descripcion, con.tipoconcepto, con.numeroconcepto
                        FROM Nom10008 AS actual
                        INNER JOIN nom10004 con
                            ON actual.idconcepto = con.idconcepto
                        WHERE
                            importetotal > 0
                            AND idperiodo = @idPeriodo
                            AND con.tipoconcepto IN (''P'',''D'', ''O'')
                    ), ' + '
                    MovimientosSumaQ2 AS (
                        SELECT
                            emp.codigoempleado,
                            ISNULL(
                                ROUND(
                                    emp.ccampoextranumerico2 *
                                    CASE
                                        WHEN empPeriodo.fechaalta < periodo.fechainicio THEN
                                            periodo.diasdepago
                                            + ISNULL(SUM(movP.valor), 0)
                                            - ISNULL(tc.incapacidad, 0)
                                            - ISNULL(tc.faltas, 0)

                                        WHEN empPeriodo.fechaalta BETWEEN periodo.fechainicio AND periodo.fechafin THEN
                                            DATEDIFF(DAY, empPeriodo.fechaalta, periodo.fechafin) + 1
                                            + ISNULL(SUM(movP.valor), 0)
                                            - ISNULL(tc.incapacidad, 0)
                                            - ISNULL(tc.faltas, 0)

                                        ELSE 0
                                    END,
                                2),
                            0) AS sueldo,
                            pdo.descripcion,
                            pdo.tipoconcepto AS tipoConcepto,
                            SUM(pdo.valor) AS cantidad,
                            SUM(pdo.importetotal) AS monto,
                            emp.ccampoextranumerico3 AS pension
                        FROM nom10001 emp
                        INNER JOIN nom10034 empPeriodo
                            ON emp.idempleado = empPeriodo.idempleado
                                AND empPeriodo.cidperiodo = @idPeriodo
                        INNER JOIN nom10002 AS periodo
                            ON empPeriodo.cidperiodo = periodo.idperiodo
                        LEFT JOIN Movimientos pdo
                            ON empPeriodo.cidperiodo = pdo.idperiodo
                                AND emp.idempleado = pdo.idempleado
                                AND (
                                    pdo.descripcion LIKE ''%ahorro%'' -- caja de ahorro
                                    OR pdo.descripcion LIKE ''%infonavit%''
                                    OR pdo.descripcion LIKE ''%fonacot%''
                                    OR pdo.numeroconcepto IN (20) -- prima vacacional
                                )
                        LEFT JOIN MovimientosRetro AS movP
                            ON empPeriodo.idempleado = movP.idempleado
                        LEFT JOIN TarjetaControlAgrupado AS tc
                            ON empPeriodo.idempleado = tc.idempleado
                        WHERE
                            empPeriodo.idtipoperiodo = @idTipoPeriodo
                            AND empPeriodo.estadoempleado IN (''A'', ''R'')
                            AND emp.TipoRegimen IN (''02'', ''03'', ''04'')
                            AND empPeriodo.idempleado BETWEEN @idEmpleadoInicial AND @idEmpleadoFinal
                        GROUP BY
                            emp.codigoempleado,
                            emp.ccampoextranumerico2,
                            empPeriodo.cdiaspagados,
                            pdo.descripcion,
                            pdo.tipoconcepto,
                            emp.ccampoextranumerico3,
                            periodo.diasdepago,
                            empPeriodo.fechaalta,
                            periodo.fechainicio,
                            periodo.fechafin,
                            tc.faltas,
                            tc.incapacidad
                    ), ' + '
                    IncidenciasNormalizadasQ2 AS (
                        SELECT
                            emp.codigoempleado as codigoempleado,
                            x.descripcion AS descripcion,
                            ''P'' AS tipoConcepto,
                            x.valor AS valor,
                            emp.ccampoextranumerico2 AS pagoPorDia,
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
                            (''Bono'',                      ngid.bono),
                            (''Comisiones'',                ngid.comision),
                            (''Dia Festivo cantidad'',      ngid.cantidad_dias_festivos),
                            (''Dia Festivo monto'',         ISNULL(ngid.cantidad_dias_festivos, 0) * emp.ccampoextranumerico2 * 2),
                            (''Horas Extra Doble cantidad'', ngid.horas_extra_doble_cantidad),
                            (''Horas Extra Doble monto'',    ISNULL(ngid.horas_extra_doble_cantidad, 0) * (emp.ccampoextranumerico2 / 8.0) * 2),
                            (''Horas Extra Triple cantidad'', ngid.horas_extra_triple_cantidad),
                            (''Horas Extra Triple monto'',    ISNULL(ngid.horas_extra_triple_cantidad, 0) * (emp.ccampoextranumerico2 / 8.0) * 3),
                            (''Pago adicional'',             ngid.pago_adicional),
                            (''Premio puntualidad'',         ngid.premio_puntualidad),
                            (''Prima Dominical cantidad'',   ngid.cantidad_prima_dominical),
                            (''Prima Dominical monto'',      ISNULL(ngid.cantidad_prima_dominical, 0) * emp.ccampoextranumerico2 * 0.25)
                        ) AS x(descripcion, valor)
                        WHERE
                            (@idNominaGapeIncidencia IS NULL OR ngi.id = @idNominaGapeIncidencia)
                            AND ngi.id_nomina_gape_empresa = @idNominaGapeEmpresa
                            AND ngid.id_empleado BETWEEN @idEmpleadoInicial AND @idEmpleadoFinal
                            AND emp.TipoRegimen IN (''02'', ''03'', ''04'')
                    ), ' + '
                    PreTotalesPorEmpleadoGeneralQ2 AS (
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
                            WHERE descripcion NOT LIKE ''%cantidad%''
                        ) AS x
                        GROUP BY
                            codigoempleado,
                            pension
                    ), ' + '
					PensionGeneralQ2 AS (
                        SELECT
                            codigoempleado,
                            (CASE WHEN porcentajePension > 0 THEN (sueldo + total_percepciones_sin_sueldo) * (porcentajePension / 100) ELSE 0 END) pension_q2
                        FROM PreTotalesPorEmpleadoGeneralQ2
                        WHERE
                            porcentajePension > 0
                    ), ' + '
                    TotalesPorEmpleadoGeneralQ2 AS (
                        SELECT
                            codigoempleado,
                            sueldo,
                            sueldo + total_percepciones_sin_sueldo AS total_percepciones,
                            total_deducciones + (CASE WHEN porcentajePension > 0 THEN (sueldo + total_percepciones_sin_sueldo) * (porcentajePension / 100) ELSE 0 END) total_deducciones,
                            (sueldo + total_percepciones_sin_sueldo) - (total_deducciones + CASE WHEN porcentajePension > 0 THEN (sueldo + total_percepciones_sin_sueldo) * (porcentajePension / 100) ELSE 0 END) AS neto_a_pagar
                        FROM PreTotalesPorEmpleadoGeneralQ2
                    ), ' + '
                    Previsiones AS (
                        SELECT
                            ngcp.id_concepto AS idconcepto
                        FROM [becma-core2].dbo.nomina_gape_empresa_periodo_combinacion_parametrizacion AS ngepcp
                        INNER JOIN [becma-core2].dbo.nomina_gape_combinacion_prevision AS ngcp
                            ON ngepcp.id = ngcp.nomina_gape_empresa_periodo_combinacion_parametrizacion
                        WHERE
                            ngepcp.id_nomina_gape_cliente = @idNominaGapeCliente
                            AND ngepcp.id_nomina_gape_empresa = @idNominaGapeEmpresa
                            AND ngepcp.id_nomina_gape_cliente_esquema_combinacion = @idEsquemaCombinacion
                            AND (
                                    ngepcp.id_nomina_gape_tipo_periodo = @idTipoPeriodo
                                    OR ngepcp.idtipoperiodo = @idTipoPeriodo
                                )
                    ), ' + '
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
                            LEFT JOIN Movimientos pdo
                                ON empPeriodo.cidperiodo = pdo.idperiodo
                                    AND emp.idempleado = pdo.idempleado
                                    AND (
                                            -- (Sueldo, Vacaciones, Prima vacacional, Retroactivos)
                                            (pdo.tipoconcepto = ''P''
                                                AND pdo.numeroconcepto IN (1, 19, 20, 16)
                                                OR EXISTS (
                                                    SELECT 1
                                                    FROM Previsiones p
                                                    WHERE p.idconcepto = pdo.idconcepto
                                                )
                                            )
                                            OR ' + '
                                            (
                                                pdo.tipoconcepto = ''D''
                                                AND (
                                                    pdo.descripcion LIKE ''%ahorro%'' -- caja de ahorro
                                                    OR pdo.descripcion LIKE ''%infonavit%''
                                                    OR pdo.descripcion LIKE ''%fonacot%''
                                                    OR pdo.descripcion LIKE ''%alimenticia%''
                                                    OR pdo.descripcion LIKE ''%sindical%''
                                                    OR pdo.numeroconcepto IN (52,35) -- Imss, isr
                                                    )
                                            )
                                        )
                            WHERE
                                empPeriodo.idtipoperiodo = @idTipoPeriodo
                                AND empPeriodo.estadoempleado IN (''A'', ''R'')
                                AND emp.TipoRegimen IN (''02'', ''03'', ''04'')
                                AND empPeriodo.idempleado BETWEEN @idEmpleadoInicial AND @idEmpleadoFinal
                            GROUP BY
                                emp.codigoempleado,
                                empPeriodo.sueldodiario,
                                empPeriodo.sueldointegrado,
                                pdo.descripcion,
                                pdo.tipoconcepto,
                                emp.ccampoextranumerico3
                    ), ' + '
					PensionGeneralQ3 AS (
                        SELECT
                            codigoempleado,
                            SUM(CASE WHEN tipoConcepto = ''D'' AND descripcion LIKE ''%alimenticia%'' THEN monto ELSE 0 END) AS pension_q3
                        FROM MovimientosSumaQ3
                        WHERE
                            pension > 0
                        GROUP BY
                            codigoempleado,
                            pension
                    ), ' + '
					PensionResultado AS (
                        SELECT
                            q2.codigoempleado,
                            ISNULL(q2.pension_q2, 0) - ISNULL(q3.pension_q3, 0) AS pension_restante
                        FROM PensionGeneralQ2 q2
                        LEFT JOIN PensionGeneralQ3 q3
                            ON q2.codigoempleado = q3.codigoempleado
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
                            GROUP BY
                                codigoempleado,
                                pension,
                                sdi,
                                sd
                    ), ' + '
                    preTotalesProrrateo AS (
                        SELECT
                            q2.codigoempleado,
                            (q2.neto_a_pagar - (q3.total_percepciones -  q3.total_deducciones)) + ISNULL(pr.pension_restante, 0) AS excedente
                        FROM TotalesPorEmpleadoGeneralQ2 q2
                        LEFT JOIN TotalesPorEmpleadoQ3 q3
                            ON q2.codigoempleado = q3.codigoempleado
                        LEFT JOIN PensionResultado pr
                            ON q3.codigoempleado = pr.codigoempleado
                    ), ' + '
                    ParamConfig AS (
                        SELECT
                            nge.esquema AS concepto
                            , ngce.tope
                            , ngce.orden
                        FROM [becma-core2].[dbo].nomina_gape_cliente_esquema_combinacion AS ngce
                        INNER JOIN [becma-core2].[dbo].nomina_gape_esquema AS nge
                            ON ngce.id_nomina_gape_esquema = nge.id
                        WHERE
                            ngce.id_nomina_gape_cliente = @idNominaGapeCliente
                            AND ngce.id_nomina_gape_empresa = @idNominaGapeEmpresa
                            AND ngce.combinacion = @idEsquemaCombinacion
                            AND ngce.orden > 1
                    ), ' + '
                    BaseProrrateo AS (
                        SELECT
                            p.codigoempleado,
                            CAST(p.excedente AS DECIMAL(18,2)) AS netoAPagar,
                            c.concepto,
                            c.tope,
                            c.orden,
                            ROW_NUMBER() OVER (PARTITION BY p.codigoempleado ORDER BY c.orden) AS rn
                        FROM preTotalesProrrateo p
                        CROSS JOIN ParamConfig c
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

                        UNION ALL ' + '

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
                        GROUP BY
                            codigoempleado
                    ), ' + '
                    PensionExcedentes AS (
                        SELECT
                            q3.codigoempleado,
                            ''Pension Excedente'' AS concepto,
                                ISNULL(pr.pension_restante, 0) AS pension_excedente
                        FROM TotalesPorEmpleadoQ3 q3
                        LEFT JOIN PensionResultado pr
                            ON pr.codigoempleado = q3.codigoempleado
                    ), ' + '
                    DeduccionExcedentes AS (
                        SELECT
                            pe.codigoempleado,
                            ISNULL(SUM(pe.pension_excedente) , 0) AS total_deducciones_excedente
                        FROM TotalesPorEmpleadoQ3 q3
                        LEFT JOIN PensionExcedentes pe
                            ON pe.codigoempleado = q3.codigoempleado
                        GROUP BY
                            pe.codigoempleado
                    ), ' + '
                    NetoExcedentes AS (
                        SELECT
                            pe.codigoempleado,
                            (pe.total_percepciones_excedente - de.total_deducciones_excedente) AS neto_excedente
                        FROM PercepcionesExcedentes pe
                        LEFT JOIN DeduccionExcedentes de
                            ON pe.codigoempleado = de.codigoempleado
                    ), ' + '
                    NetoTotalAPagar AS (
                        SELECT
                            ne.codigoempleado,
                            (q3.total_percepciones - q3.total_deducciones) + (ne.neto_excedente) AS neto_total_a_pagar
                        FROM NetoExcedentes ne
                        LEFT JOIN TotalesPorEmpleadoQ3 AS q3
                            ON ne.codigoempleado = q3.codigoempleado
                    ), ' + '
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
                        WHERE
                            empPeriodo.cidperiodo = @idPeriodo
                            AND empPeriodo.estadoempleado IN (''A'', ''R'')
                            AND emp.TipoRegimen IN (''02'', ''03'', ''04'')
                            AND empPeriodo.idempleado BETWEEN @idEmpleadoInicial AND @idEmpleadoFinal
                    ), ' + '
                    nomGapeEmpresa AS (
                        SELECT
                            provisiones
                            , fee
                        FROM [becma-core2].dbo.nomina_gape_empresa_periodo_combinacion_parametrizacion
                        WHERE
                            id_nomina_gape_empresa = @idNominaGapeEmpresa
                            AND id_nomina_gape_cliente = @idNominaGapeCliente
                            AND id_nomina_gape_cliente_esquema_combinacion = @idEsquemaCombinacion
                            AND (
                                    id_nomina_gape_tipo_periodo = @idTipoPeriodo
                                    OR idtipoperiodo = @idTipoPeriodo
                                )
                    ), ' + '
                    totalCompensacion AS (
                        SELECT
                            nta.codigoempleado,
                            nge.fee AS  fee,
                            (nta.neto_total_a_pagar + q3.total_deducciones + q2.total_deducciones_excedente)
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
                            INNER JOIN TotalesPorEmpleadoQ3 AS q3
                                ON nta.codigoempleado = q3.codigoempleado
                            INNER JOIN DeduccionExcedentes AS q2
                                ON nta.codigoempleado = q2.codigoempleado
                            CROSS JOIN nomGapeEmpresa nge
                    ), ' + '
                    MovimientosObligaciones AS (
						SELECT
							emp.codigoempleado AS codigoempleado,
                            pdo.descripcion AS concepto,
							''O'' AS tipoConcepto,
							pdo.importetotal AS monto
						FROM nom10001 emp
						INNER JOIN nom10034 empPeriodo
							ON emp.idempleado = empPeriodo.idempleado
							    AND empPeriodo.cidperiodo = @idPeriodo
						LEFT JOIN Movimientos pdo
							ON empPeriodo.cidperiodo = pdo.idperiodo
                                AND emp.idempleado = pdo.idempleado
                                AND pdo.tipoconcepto = ''O''
                                AND pdo.numeroconcepto IN (90, 89, 93, 96, 97, 98)
						WHERE
                            empPeriodo.idtipoperiodo = @idTipoPeriodo
							AND empPeriodo.idempleado BETWEEN @idEmpleadoInicial AND @idEmpleadoFinal
                            AND emp.TipoRegimen IN (''02'', ''03'', ''04'')
					), ' + '
                    TotalesPorEmpleadoObligacion AS (
                        SELECT
                            codigoempleado AS codigoempleado,
                            SUM(monto) AS monto
                        FROM MovimientosObligaciones
                        GROUP BY
                            codigoempleado
                    ),
                    Comision AS (
                        SELECT
                            t.codigoempleado AS codigoempleado,
                            (com.monto_asignado + t.monto) * (com.fee / 100) AS total_comision
                        FROM TotalesPorEmpleadoObligacion AS t
                        INNER JOIN totalCompensacion com
                            ON t.codigoempleado = com.codigoempleado
                    ),
                    CostoTotal AS (
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
                    FROM CostoTotal
                    PIVOT (
                        SUM(monto)
                        FOR concepto IN (' + @cols + ')
                    ) p
                    ORDER BY codigoempleado;
                    ';

                    EXEC(@sql);
            ";

            //return $sql;
            $result = DB::connection('sqlsrv_dynamic')->select($sql);

            return $result;
        } catch (\Exception $e) {
            return [];
        }
    }

    private function totalSueldoImss_01(Request $request)
    {
        try {
            $idNominaGapeCliente = $request->id_nomina_gape_cliente;
            $idNominaGapeEmpresa = $request->id_nomina_gape_empresa;

            $idNominaGapeEsquema = $request->id_nomina_gape_esquema;
            $idEsquemaCombinacion = $request->id_esquema;

            $idTipoPeriodo = $request->id_tipo_periodo;
            $idPeriodo = $request->periodo_inicial;

            $idEmpleadoInicial = $request->empleado_inicial;
            $idEmpleadoFinal = $request->empleado_final;

            $idNominaGapeIncidencia = $request->id_nomina_gape_incidencia ?? 0;

            $sql = "
                DECLARE @sql  NVARCHAR(MAX);


                SET @sql = CAST('' AS NVARCHAR(MAX)) + '
                DECLARE @idNominaGapeCliente INT;
                DECLARE @idNominaGapeEmpresa INT;

                DECLARE @idNominaGapeEsquema INT;
                DECLARE @idEsquemaCombinacion INT;

                DECLARE @idPeriodo INT;
                DECLARE @idTipoPeriodo INT;

                DECLARE @idEmpleadoInicial INT;
                DECLARE @idEmpleadoFinal INT;

				DECLARE @idNominaGapeIncidencia INT;

                SET @idNominaGapeCliente = $idNominaGapeCliente;
                SET @idNominaGapeEmpresa = $idNominaGapeEmpresa;

                SET @idNominaGapeEsquema = $idNominaGapeEsquema;
                SET @idEsquemaCombinacion = $idEsquemaCombinacion;

                SET @idPeriodo = $idPeriodo;
                SET @idTipoPeriodo = $idTipoPeriodo;

                SET @idEmpleadoInicial = $idEmpleadoInicial;
                SET @idEmpleadoFinal = $idEmpleadoFinal;

				SET @idNominaGapeIncidencia = $idNominaGapeIncidencia;

                ' + '

                ;WITH MovimientosRetro AS (
                        SELECT idempleado, idperiodo, his.idconcepto, valor, importetotal, con.descripcion, con.tipoconcepto
                        FROM Nom10007 AS his
                            INNER JOIN nom10004 con
                                ON his.idconcepto = con.idconcepto
                        WHERE
                            valor > 0
                            AND idperiodo = @idPeriodo
                            AND con.tipoconcepto IN (''P'')
                            AND con.descripcion LIKE ''%retroactivo%''
                        UNION ALL
                        SELECT idempleado, idperiodo, actual.idconcepto, valor, importetotal, con.descripcion, con.tipoconcepto
                        FROM Nom10008 AS actual
                        INNER JOIN nom10004 con
                            ON actual.idconcepto = con.idconcepto
                        WHERE
                            valor > 0
                            AND idperiodo = @idPeriodo
                            AND con.tipoconcepto IN (''P'')
                            AND con.descripcion LIKE ''%retroactivo%''
                    ), ' + '
                    tarjetaControl AS (
                        SELECT idempleado, idperiodo, valor, idtarjetaincapacidad, ti.mnemonico AS mnemonico
                        FROM nom10009 AS fi
                        INNER JOIN nom10022 ti
                            ON fi.idtipoincidencia = ti.idtipoincidencia
                        WHERE
                            ti.mnemonico IN (''INC'', ''FINJ'')
                            AND idperiodo = @idPeriodo
                        UNION ALL
                        SELECT idempleado, idperiodo, valor, idtarjetaincapacidad, ti.mnemonico AS mnemonico
                        FROM NOM10010 AS fi
                        INNER JOIN nom10022 ti
                            ON fi.idtipoincidencia = ti.idtipoincidencia
                        WHERE
                            ti.mnemonico IN (''INC'', ''FINJ'')
                            AND idperiodo = @idPeriodo
                    ), ' + '
                    TarjetaControlAgrupado AS (
                        SELECT
                            idempleado,
                            SUM(CASE WHEN mnemonico = ''INC'' THEN valor ELSE 0 END) AS incapacidad,
                            SUM(CASE WHEN mnemonico = ''FINJ'' THEN valor ELSE 0 END) AS faltas
                        FROM tarjetaControl
                        GROUP BY
                            idempleado
                    ), ' + '
                    Movimientos AS (
                        SELECT idempleado, idperiodo, his.idconcepto, valor, importetotal, con.descripcion, con.tipoconcepto, con.numeroconcepto
                        FROM Nom10007 AS his
                        INNER JOIN nom10004 con
                            ON his.idconcepto = con.idconcepto
                        WHERE
                            importetotal > 0
                            AND idperiodo = @idPeriodo
                            AND con.tipoconcepto IN (''P'',''D'')
                        UNION ALL
                        SELECT idempleado, idperiodo, actual.idconcepto, valor, importetotal, con.descripcion, con.tipoconcepto, con.numeroconcepto
                        FROM Nom10008 AS actual
                        INNER JOIN nom10004 con
                            ON actual.idconcepto = con.idconcepto
                        WHERE
                            importetotal > 0
                            AND idperiodo = @idPeriodo
                            AND con.tipoconcepto IN (''P'',''D'')
                    ), ' + '
                    MovimientosSumaQ2 AS (
                        SELECT
                            emp.codigoempleado,
                            ISNULL(
                                ROUND(
                                    emp.ccampoextranumerico2 *
                                    CASE
                                        WHEN empPeriodo.fechaalta < periodo.fechainicio THEN
                                            periodo.diasdepago
                                            + ISNULL(SUM(movP.valor), 0)
                                            - ISNULL(tc.incapacidad, 0)
                                            - ISNULL(tc.faltas, 0)

                                        WHEN empPeriodo.fechaalta BETWEEN periodo.fechainicio AND periodo.fechafin THEN
                                            DATEDIFF(DAY, empPeriodo.fechaalta, periodo.fechafin) + 1
                                            + ISNULL(SUM(movP.valor), 0)
                                            - ISNULL(tc.incapacidad, 0)
                                            - ISNULL(tc.faltas, 0)

                                        ELSE 0
                                    END,
                                2),
                            0) AS sueldo,
                            pdo.descripcion,
                            pdo.tipoconcepto AS tipoConcepto,
                            SUM(pdo.valor) AS cantidad,
                            SUM(pdo.importetotal) AS monto,
                            emp.ccampoextranumerico3 AS pension
                        FROM nom10001 emp ' + '
                        INNER JOIN nom10034 empPeriodo
                            ON emp.idempleado = empPeriodo.idempleado
                                AND empPeriodo.cidperiodo = @idPeriodo
                        INNER JOIN nom10002 AS periodo
                            ON empPeriodo.cidperiodo = periodo.idperiodo
                        LEFT JOIN Movimientos pdo
                            ON empPeriodo.cidperiodo = pdo.idperiodo
                                AND emp.idempleado = pdo.idempleado
                                AND (
                                    pdo.descripcion LIKE ''%ahorro%'' -- caja de ahorro
                                    OR pdo.descripcion LIKE ''%infonavit%''
                                    OR pdo.descripcion LIKE ''%fonacot%''
                                    OR pdo.numeroconcepto IN (20) -- prima vacacional
                                )
                        LEFT JOIN MovimientosRetro AS movP
                            ON empPeriodo.idempleado = movP.idempleado
                        LEFT JOIN TarjetaControlAgrupado AS tc
                            ON empPeriodo.idempleado = tc.idempleado
                        WHERE
                            empPeriodo.idtipoperiodo = @idTipoPeriodo
                            AND empPeriodo.estadoempleado IN (''A'', ''R'')
                            AND emp.TipoRegimen IN (''02'', ''03'', ''04'')
                            AND empPeriodo.idempleado BETWEEN @idEmpleadoInicial AND @idEmpleadoFinal
                        GROUP BY
                            emp.codigoempleado,
                            emp.ccampoextranumerico2,
                            empPeriodo.cdiaspagados,
                            pdo.descripcion,
                            pdo.tipoconcepto,
                            emp.ccampoextranumerico3,
                            periodo.diasdepago,
                            empPeriodo.fechaalta,
                            periodo.fechainicio,
                            periodo.fechafin,
                            tc.faltas,
                            tc.incapacidad
                    ), ' + '
                    IncidenciasNormalizadasQ2 AS (
                        SELECT
                            emp.codigoempleado as codigoempleado,
                            x.descripcion AS descripcion,
                            ''P'' AS tipoConcepto,
                            x.valor AS valor,
                            emp.ccampoextranumerico2 AS pagoPorDia,
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
                            (''Bono'',                      ngid.bono),
                            (''Comisiones'',                ngid.comision),
                            (''Dia Festivo cantidad'',      ngid.cantidad_dias_festivos),
                            (''Dia Festivo monto'',         ISNULL(ngid.cantidad_dias_festivos, 0) * emp.ccampoextranumerico2 * 2),
                            (''Horas Extra Doble cantidad'', ngid.horas_extra_doble_cantidad),
                            (''Horas Extra Doble monto'',    ISNULL(ngid.horas_extra_doble_cantidad, 0) * (emp.ccampoextranumerico2 / 8.0) * 2),
                            (''Horas Extra Triple cantidad'', ngid.horas_extra_triple_cantidad),
                            (''Horas Extra Triple monto'',    ISNULL(ngid.horas_extra_triple_cantidad, 0) * (emp.ccampoextranumerico2 / 8.0) * 3),
                            (''Pago adicional'',             ngid.pago_adicional),
                            (''Premio puntualidad'',         ngid.premio_puntualidad),
                            (''Prima Dominical cantidad'',   ngid.cantidad_prima_dominical),
                            (''Prima Dominical monto'',      ISNULL(ngid.cantidad_prima_dominical, 0) * emp.ccampoextranumerico2 * 0.25)
                        ) AS x(descripcion, valor)
                        WHERE
                            (@idNominaGapeIncidencia IS NULL OR ngi.id = @idNominaGapeIncidencia)
                            AND ngi.id_nomina_gape_empresa = @idNominaGapeEmpresa
                            AND ngid.id_empleado BETWEEN @idEmpleadoInicial AND @idEmpleadoFinal
                            AND emp.TipoRegimen IN (''02'', ''03'', ''04'')
                    ), ' + '
                    PreTotalesPorEmpleadoGeneralQ2 AS (
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
                            WHERE descripcion NOT LIKE ''%cantidad%''
                        ) AS x
                        GROUP BY
                            codigoempleado,
                            pension
                    ), ' + '
					PensionGeneralQ2 AS (
                        SELECT
                            codigoempleado,
                            (CASE WHEN porcentajePension > 0 THEN (sueldo + total_percepciones_sin_sueldo) * (porcentajePension / 100) ELSE 0 END) pension_q2
                        FROM PreTotalesPorEmpleadoGeneralQ2
                        WHERE porcentajePension > 0
                    ), ' + '
                    TotalesPorEmpleadoGeneralQ2 AS (
                        SELECT
                            codigoempleado,
                            sueldo,
                            sueldo + total_percepciones_sin_sueldo AS total_percepciones,
                            total_deducciones + (CASE WHEN porcentajePension > 0 THEN (sueldo + total_percepciones_sin_sueldo) * (porcentajePension / 100) ELSE 0 END) total_deducciones,
                            (sueldo + total_percepciones_sin_sueldo) - (total_deducciones + CASE WHEN porcentajePension > 0 THEN (sueldo + total_percepciones_sin_sueldo) * (porcentajePension / 100) ELSE 0 END) AS neto_a_pagar
                        FROM PreTotalesPorEmpleadoGeneralQ2
                    ), ' + '
                    Previsiones AS (
                        SELECT
                            ngcp.id_concepto AS idconcepto
                        FROM [becma-core2].dbo.nomina_gape_empresa_periodo_combinacion_parametrizacion AS ngepcp
                        INNER JOIN [becma-core2].dbo.nomina_gape_combinacion_prevision AS ngcp
                            ON ngepcp.id = ngcp.nomina_gape_empresa_periodo_combinacion_parametrizacion
                        WHERE
                            ngepcp.id_nomina_gape_cliente = @idNominaGapeCliente
                            AND ngepcp.id_nomina_gape_empresa = @idNominaGapeEmpresa
                            AND ngepcp.id_nomina_gape_cliente_esquema_combinacion = @idEsquemaCombinacion
                            AND (
                                    ngepcp.id_nomina_gape_tipo_periodo = @idTipoPeriodo
                                    OR ngepcp.idtipoperiodo = @idTipoPeriodo
                                )
                    ), ' + '
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
                            LEFT JOIN Movimientos pdo
                                ON empPeriodo.cidperiodo = pdo.idperiodo
                                    AND emp.idempleado = pdo.idempleado
                                    AND (
                                            -- (Sueldo, Vacaciones, Prima vacacional, Retroactivos)
                                            (pdo.tipoconcepto = ''P''
                                                AND pdo.numeroconcepto IN (1, 19, 20, 16)
                                                OR EXISTS (
                                                    SELECT 1
                                                    FROM Previsiones p
                                                    WHERE p.idconcepto = pdo.idconcepto
                                                )
                                            )
                                            OR ' + '
                                            (
                                                pdo.tipoconcepto = ''D''
                                                AND (
                                                    pdo.descripcion LIKE ''%ahorro%'' -- caja de ahorro
                                                    OR pdo.descripcion LIKE ''%infonavit%''
                                                    OR pdo.descripcion LIKE ''%fonacot%''
                                                    OR pdo.descripcion LIKE ''%alimenticia%''
                                                    OR pdo.descripcion LIKE ''%sindical%''
                                                    OR pdo.numeroconcepto IN (52,35) -- Imss, isr
                                                    )
                                            )
                                        )
                            WHERE
                                empPeriodo.idtipoperiodo = @idTipoPeriodo
                                AND empPeriodo.estadoempleado IN (''A'', ''R'')
                                AND emp.TipoRegimen IN (''02'', ''03'', ''04'')
                                AND empPeriodo.idempleado BETWEEN @idEmpleadoInicial AND @idEmpleadoFinal
                            GROUP BY
                                emp.codigoempleado,
                                empPeriodo.sueldodiario,
                                empPeriodo.sueldointegrado,
                                pdo.descripcion,
                                pdo.tipoconcepto,
                                emp.ccampoextranumerico3
                    ), ' + '
					PensionGeneralQ3 AS (
                        SELECT
                            codigoempleado,
                            SUM(CASE WHEN tipoConcepto = ''D'' AND descripcion LIKE ''%alimenticia%'' THEN monto ELSE 0 END) AS pension_q3
                        FROM MovimientosSumaQ3
                        WHERE
                            pension > 0
                        GROUP BY
                            codigoempleado,
                            pension
                    ), ' + '
                    PensionResultado AS (
                        SELECT
                            q2.codigoempleado,
                            ISNULL(q2.pension_q2, 0) - ISNULL(q3.pension_q3, 0) AS pension_restante
                        FROM PensionGeneralQ2 q2
                        LEFT JOIN PensionGeneralQ3 q3
                            ON q2.codigoempleado = q3.codigoempleado
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
                        GROUP BY
                            codigoempleado,
                            pension,
                            sdi,
                            sd
                    ), ' + '
                    preTotalesProrrateo AS (
                        SELECT
                            q2.codigoempleado,
                            (q2.neto_a_pagar - (q3.total_percepciones -  q3.total_deducciones)) + ISNULL(pr.pension_restante, 0) AS excedente
                        FROM TotalesPorEmpleadoGeneralQ2 q2
                        LEFT JOIN TotalesPorEmpleadoQ3 q3
                            ON q2.codigoempleado = q3.codigoempleado
                        LEFT JOIN PensionResultado pr
                            ON q3.codigoempleado = pr.codigoempleado
                    ), ' + '
                    ParamConfig AS (
                        SELECT
                            nge.esquema AS concepto
                            , ngce.tope
                            , ngce.orden
                        FROM [becma-core2].[dbo].nomina_gape_cliente_esquema_combinacion AS ngce
                        INNER JOIN [becma-core2].[dbo].nomina_gape_esquema AS nge
                            ON ngce.id_nomina_gape_esquema = nge.id
                        WHERE
                            ngce.id_nomina_gape_cliente = @idNominaGapeCliente
                            AND ngce.id_nomina_gape_empresa = @idNominaGapeEmpresa
                            AND ngce.combinacion = @idEsquemaCombinacion
                            AND ngce.orden > 1
                    ), ' + '
                    BaseProrrateo AS (
                        SELECT
                            p.codigoempleado,
                            CAST(p.excedente AS DECIMAL(18,2)) AS netoAPagar,
                            c.concepto,
                            c.tope,
                            c.orden,
                            ROW_NUMBER() OVER (PARTITION BY p.codigoempleado ORDER BY c.orden) AS rn
                        FROM preTotalesProrrateo p
                        CROSS JOIN ParamConfig c
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

                        UNION ALL ' + '

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
                        GROUP
                            BY codigoempleado
                    ), ' + '
                    PensionExcedentes AS (
                        SELECT
                            q3.codigoempleado,
                            ''Pension Excedente'' AS concepto,
                                ISNULL(pr.pension_restante, 0) AS pension_excedente
                        FROM TotalesPorEmpleadoQ3 q3
                        LEFT JOIN PensionResultado pr
                            ON pr.codigoempleado = q3.codigoempleado
                    ), ' + '
                    DeduccionExcedentes AS (
                        SELECT
                            pe.codigoempleado,
                            ISNULL(SUM(pe.pension_excedente) , 0) AS total_deducciones_excedente
                        FROM TotalesPorEmpleadoQ3 q3
                        LEFT JOIN PensionExcedentes pe
                            ON pe.codigoempleado = q3.codigoempleado
                        GROUP BY
                            pe.codigoempleado
                    ), ' + '
                    NetoExcedentes AS (
                        SELECT
                            pe.codigoempleado,
                            (pe.total_percepciones_excedente - de.total_deducciones_excedente) AS neto_excedente
                        FROM PercepcionesExcedentes pe
                        LEFT JOIN DeduccionExcedentes de
                            ON pe.codigoempleado = de.codigoempleado
                    ), ' + '
                    NetoTotalAPagar AS (
                        SELECT
                            ne.codigoempleado,
                            (q3.total_percepciones - q3.total_deducciones) + (ne.neto_excedente) AS neto_total_a_pagar
                        FROM NetoExcedentes ne
                        LEFT JOIN TotalesPorEmpleadoQ3 AS q3
                            ON ne.codigoempleado = q3.codigoempleado
                    ), ' + '
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
                        WHERE
                            empPeriodo.cidperiodo = @idPeriodo
                            AND empPeriodo.estadoempleado IN (''A'', ''R'')
                            AND emp.TipoRegimen IN (''02'', ''03'', ''04'')
                            AND empPeriodo.idempleado BETWEEN @idEmpleadoInicial AND @idEmpleadoFinal
                    ), ' + '
                    nomGapeEmpresa AS (
                        SELECT
                            provisiones
                            , fee
                        FROM [becma-core2].dbo.nomina_gape_empresa_periodo_combinacion_parametrizacion
                        WHERE
                            id_nomina_gape_empresa = @idNominaGapeEmpresa
                            AND id_nomina_gape_cliente = @idNominaGapeCliente
                            AND id_nomina_gape_cliente_esquema_combinacion = @idEsquemaCombinacion
                            AND (
                                    id_nomina_gape_tipo_periodo = @idTipoPeriodo
                                    OR idtipoperiodo = @idTipoPeriodo
                                )
                    ), ' + '
                    totalCompensacion AS (
                        SELECT
                            nta.codigoempleado,
                            nge.fee AS  fee,
                            (nta.neto_total_a_pagar + q3.total_deducciones + q2.total_deducciones_excedente)
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
                            INNER JOIN TotalesPorEmpleadoQ3 AS q3
                                ON nta.codigoempleado = q3.codigoempleado
                            INNER JOIN DeduccionExcedentes AS q2
                                ON nta.codigoempleado = q2.codigoempleado
                            CROSS JOIN nomGapeEmpresa nge
                    ), ' + '
                    MovimientosO AS (
                        SELECT idempleado, idperiodo, his.idconcepto, valor, importetotal, con.descripcion, con.tipoconcepto, con.numeroconcepto
                        FROM Nom10007 AS his
                        INNER JOIN nom10004 con
                            ON his.idconcepto = con.idconcepto
                        WHERE
                            importetotal > 0
                            AND idperiodo = @idPeriodo
                            AND con.tipoconcepto IN (''O'')
                            AND con.numeroconcepto IN (90,89, 93, 96, 97, 98)
                        UNION ALL
                        SELECT idempleado, idperiodo, actual.idconcepto, valor, importetotal, con.descripcion, con.tipoconcepto, con.numeroconcepto
                        FROM Nom10008 AS actual
                        INNER JOIN nom10004 con
                            ON actual.idconcepto = con.idconcepto
                        WHERE
                            importetotal > 0
                            AND idperiodo = @idPeriodo
                            AND con.tipoconcepto IN (''O'')
                            AND con.numeroconcepto IN (90,89, 93, 96, 97, 98)
                    ), ' + '
                    MovimientosObligaciones AS (
						SELECT
							emp.codigoempleado AS codigoempleado,
							CASE
								WHEN pdo.numeroconcepto = 90
									THEN ''ISN''
								WHEN pdo.numeroconcepto IN (89, 93, 96, 97, 98)
									THEN ''IMSS Patronal''
							END AS concepto,
							''O'' AS tipoConcepto,
							SUM(pdo.importetotal) AS monto
						FROM nom10001 emp
						INNER JOIN nom10034 empPeriodo
							ON emp.idempleado = empPeriodo.idempleado
							AND empPeriodo.cidperiodo = @idPeriodo
						LEFT JOIN MovimientosO pdo
							ON empPeriodo.cidperiodo = pdo.idperiodo
							AND emp.idempleado = pdo.idempleado
						WHERE
                            empPeriodo.idtipoperiodo = @idTipoPeriodo
							AND empPeriodo.idempleado BETWEEN @idEmpleadoInicial AND @idEmpleadoFinal
							AND empPeriodo.estadoempleado IN (''A'', ''R'')
                            AND emp.TipoRegimen IN (''02'', ''03'', ''04'')
						GROUP BY
							emp.codigoempleado,
							CASE
								WHEN pdo.numeroconcepto = 90
									THEN ''ISN''
								WHEN pdo.numeroconcepto IN (89, 93, 96, 97, 98)
									THEN ''IMSS Patronal''
							END
					),
                    TotalesPorEmpleadoObligacion AS (
                        SELECT
                            codigoempleado AS codigoempleado,
                            SUM(monto) AS monto
                        FROM MovimientosObligaciones
                        GROUP
                            BY codigoempleado
                    ),
                    Comision AS (
                        SELECT
                            t.codigoempleado AS codigoempleado,
                            (com.monto_asignado + t.monto) * (com.fee / 100) AS total_comision
                        FROM TotalesPorEmpleadoObligacion AS t
                        INNER JOIN totalCompensacion com
                            ON t.codigoempleado = com.codigoempleado
                    ),
                    CostoTotal AS (
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
                    ) , ' + '

                    ResumenFinal AS (
                        SELECT
                            SUM(CASE WHEN concepto = ''Compensacion'' THEN monto ELSE 0 END) AS percepcion_bruta,
                            SUM(CASE WHEN concepto = ''Total cs'' THEN monto ELSE 0 END) AS costo_social
                        FROM CostoTotal
                    ), ' + '
                    TotalesCalculados AS (
                        SELECT
                            percepcion_bruta,
                            costo_social,
                            (percepcion_bruta + costo_social) AS base,
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
            /*dd([
                'row' => $sql,
            ]);
            */
            //return $sql;
            $result = collect(DB::connection('sqlsrv_dynamic')->select($sql))->first();

            return $result;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Consulta para formato Asiimilados
     */

    private function datosAsimilados_1(Request $request)
    {
        try {
            $idNominaGapeEmpresa = $request->id_nomina_gape_empresa;
            $idTipoPeriodo = $request->id_tipo_periodo;
            $idPeriodo = $request->periodo_inicial;

            $idEmpleadoInicial = $request->empleado_inicial;
            $idEmpleadoFinal = $request->empleado_final;

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

                ;WITH MovimientosRetro AS (
                    SELECT idempleado, idperiodo, his.idconcepto, valor, importetotal, con.descripcion, con.tipoconcepto
                    FROM Nom10007 AS his
                        INNER JOIN nom10004 con
                            ON his.idconcepto = con.idconcepto
                    WHERE
                        idperiodo = @idPeriodo
                        AND con.tipoconcepto IN ('P', 'N')
                        AND (con.descripcion LIKE '%retroactivo%'
                            OR con.descripcion LIKE '%Neto%')
                    UNION ALL
                    SELECT idempleado, idperiodo, actual.idconcepto, valor, importetotal, con.descripcion, con.tipoconcepto
                    FROM Nom10008 AS actual
                    INNER JOIN nom10004 con
                        ON actual.idconcepto = con.idconcepto
                    WHERE
                        idperiodo = @idPeriodo
                        AND con.tipoconcepto IN ('P', 'N')
                        AND (con.descripcion LIKE '%retroactivo%'
                            OR con.descripcion LIKE '%Neto%')
                ),
                tarjetaControl AS (
                    SELECT idempleado, idperiodo, valor, idtarjetaincapacidad, ti.mnemonico AS mnemonico
                    FROM nom10009 AS fi
                        INNER JOIN nom10022 ti
                            ON fi.idtipoincidencia = ti.idtipoincidencia
                    WHERE
                        ti.mnemonico IN ('INC', 'FINJ')
                        AND idperiodo = @idPeriodo
                    UNION ALL
                    SELECT idempleado, idperiodo, valor, idtarjetaincapacidad, ti.mnemonico AS mnemonico
                    FROM NOM10010 AS fi
                        INNER JOIN nom10022 ti
                            ON fi.idtipoincidencia = ti.idtipoincidencia
                    WHERE
                        ti.mnemonico IN ('INC', 'FINJ')
                        AND idperiodo = @idPeriodo
                ),
                TarjetaControlAgrupado AS (
                    SELECT
                        idempleado,
                        SUM(CASE WHEN mnemonico = 'INC' THEN valor ELSE 0 END) AS incapacidad,
                        SUM(CASE WHEN mnemonico = 'FINJ' THEN valor ELSE 0 END) AS faltas
                    FROM tarjetaControl
                    GROUP BY
                        idempleado
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
                    , CASE
						WHEN empPeriodo.fechaalta < periodo.fechainicio
							THEN (periodo.diasdepago)
						WHEN empPeriodo.fechaalta >= periodo.fechainicio AND empPeriodo.fechaalta <= periodo.fechafin
							THEN (DATEDIFF(DAY, empPeriodo.fechaalta, periodo.fechafin) + 1)
						ELSE 0
					  END AS diasPeriodo
                    , ISNULL(SUM(movP.valor), 0) AS diasRetroactivos
                    , ISNULL(tc.incapacidad, 0) AS incapacidad
                    , ISNULL(tc.faltas, 0) AS faltas
                    , CASE
                        WHEN empPeriodo.fechaalta < periodo.fechainicio THEN
                            periodo.diasdepago
                            + ISNULL(SUM(movP.valor), 0)
                            - ISNULL(tc.incapacidad, 0)
                            - ISNULL(tc.faltas, 0)

                        WHEN empPeriodo.fechaalta BETWEEN periodo.fechainicio AND periodo.fechafin THEN
                            DATEDIFF(DAY, empPeriodo.fechaalta, periodo.fechafin) + 1
                            + ISNULL(SUM(movP.valor), 0)
                            - ISNULL(tc.incapacidad, 0)
                            - ISNULL(tc.faltas, 0)

                        ELSE 0
                        END AS diasPagados
                    , ISNULL(
                        CASE WHEN movP.tipoconcepto = 'N' THEN movP.importetotal ELSE 0 END
                    ,0) AS sueldo
                FROM nom10001 emp
                INNER JOIN nom10034 AS empPeriodo
                    ON emp.idempleado = empPeriodo.idempleado
                        AND empPeriodo.cidperiodo = @idPeriodo
                INNER JOIN nom10002 AS periodo
                    ON empPeriodo.cidperiodo = periodo.idperiodo
                LEFT JOIN nom10006 AS puesto
                    ON emp.idpuesto = puesto.idpuesto
                LEFT JOIN MovimientosRetro AS movP
                    ON empPeriodo.idempleado = movP.idempleado
                LEFT JOIN TarjetaControlAgrupado AS tc
                    ON empPeriodo.idempleado = tc.idempleado
                WHERE
                    empPeriodo.idtipoperiodo = @idTipoPeriodo
                    AND empPeriodo.idempleado BETWEEN @idEmpleadoInicial AND @idEmpleadoFinal
                    AND empPeriodo.estadoempleado IN ('A', 'R')
                    AND emp.TipoRegimen IN ('05', '06', '07', '08', '09', '10', '11')
                GROUP BY
                    emp.idempleado,
                    emp.codigoempleado,
                    emp.nombrelargo,
                    puesto.descripcion,
                    emp.fechaalta,
                    emp.campoextra1,
                    emp.numerosegurosocial,
                    emp.rfc,
                    emp.fechanacimiento,
                    emp.homoclave,
                    emp.ccampoextranumerico1,
                    emp.ccampoextranumerico2,
                    periodo.diasdepago,
                    empPeriodo.cdiasincapacidades,
                    empPeriodo.cdiasausencia,
                    empPeriodo.cdiaspagados,
                    emp.curpi,
                    emp.curpf,
                    empPeriodo.fechaalta,
                    periodo.fechainicio,
                    periodo.fechafin,
                    tc.faltas,
                    tc.incapacidad,
                    movP.tipoconcepto,
                    movP.importetotal
                ORDER BY
                    emp.codigoempleado
            ";

            /*
            dd([
                'row' => $sql,
            ]);
            */
            //return $sql;
            $result = DB::connection('sqlsrv_dynamic')->select($sql);


            return $result;
        } catch (\Exception $e) {
            return [];
        }
    }

    private function datosAsimilados_2(Request $request)
    {
        try {
            $idNominaGapeEmpresa = $request->id_nomina_gape_empresa;
            $idTipoPeriodo = $request->id_tipo_periodo;
            $idPeriodo = $request->periodo_inicial;

            $idEmpleadoInicial = $request->empleado_inicial;
            $idEmpleadoFinal = $request->empleado_final;

            $idNominaGapeIncidencia = $request->id_nomina_gape_incidencia ?? 0;

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
                    WHERE
                        importetotal > 0
                        AND idperiodo = @idPeriodo
						AND con.tipoconcepto IN ('P','D')
						AND (
							con.descripcion LIKE '%ahorro%' -- caja de ahorro
							OR con.descripcion LIKE '%infonavit%'
							OR con.descripcion LIKE '%fonacot%'
							OR con.numeroconcepto IN (20) -- prima vacacional
							)
                    UNION ALL
                    SELECT idempleado, idperiodo, actual.idconcepto, valor, importetotal, con.descripcion, con.tipoconcepto
                    FROM Nom10008 AS actual
					INNER JOIN nom10004 con
                        ON actual.idconcepto = con.idconcepto
                    WHERE
                        importetotal > 0
                        AND idperiodo = @idPeriodo
						AND con.tipoconcepto IN ('P','D')
						AND (
                                con.descripcion LIKE '%ahorro%' -- caja de ahorro
                                OR con.descripcion LIKE '%infonavit%'
                                OR con.descripcion LIKE '%fonacot%'
                                OR con.numeroconcepto IN (20) -- prima vacacional
							)
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
                    WHERE
                        empPeriodo.idtipoperiodo = @idTipoPeriodo
						AND empPeriodo.idempleado BETWEEN @idEmpleadoInicial AND @idEmpleadoFinal
                        AND empPeriodo.estadoempleado IN ('A', 'R')
                        AND emp.TipoRegimen IN ('05', '06', '07', '08', '09', '10', '11')
                    GROUP BY
                        pdo.descripcion,
                        pdo.tipoconcepto
                ),
                Incidencias AS (
					SELECT * FROM (VALUES
						('Prima Dominical cantidad', 'P', 500, 1),
						('Prima Dominical monto', 'P', 500, 1),
						('Dia Festivo cantidad', 'P', 500, 1),
						('Dia Festivo monto', 'P', 500, 1),
						('Comisiones', 'P', 500, 1),
						('Bono', 'P', 500, 1),
						('Horas Extra Doble cantidad', 'P', 500, 1),
						('Horas Extra Doble monto', 'P', 500, 1),
						('Horas Extra Triple cantidad', 'P', 500, 1),
						('Horas Extra Triple monto', 'P', 500, 1),
						('Pago adicional', 'P', 500, 1),
						('Premio puntualidad', 'P', 500, 1),
                        ('Descuentos', 'D', 500, 2),
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

                DECLARE @idNominaGapeIncidencia INT;

                SET @idPeriodo = $idPeriodo;
                SET @idTipoPeriodo = $idTipoPeriodo;

                SET @idEmpleadoInicial = $idEmpleadoInicial;
                SET @idEmpleadoFinal = $idEmpleadoFinal;

                SET @idNominaGapeEmpresa = $idNominaGapeEmpresa;

                SET @idNominaGapeIncidencia = $idNominaGapeIncidencia;

                ' + '

                ;WITH MovimientosRetro AS (
                    SELECT idempleado, idperiodo, his.idconcepto, valor, importetotal, con.descripcion, con.tipoconcepto
                    FROM Nom10007 AS his
                    INNER JOIN nom10004 con
                        ON his.idconcepto = con.idconcepto
                    WHERE
                        idperiodo = @idPeriodo
                        AND con.tipoconcepto IN (''P'', ''N'')
                        AND (con.descripcion LIKE ''%retroactivo%''
                            OR con.descripcion LIKE ''%Neto%'')
                    UNION ALL
                    SELECT idempleado, idperiodo, actual.idconcepto, valor, importetotal, con.descripcion, con.tipoconcepto
                    FROM Nom10008 AS actual
                    INNER JOIN nom10004 con
                        ON actual.idconcepto = con.idconcepto
                    WHERE
                        idperiodo = @idPeriodo
                        AND con.tipoconcepto IN (''P'', ''N'')
                        AND (con.descripcion LIKE ''%retroactivo%''
                            OR con.descripcion LIKE ''%Neto%'')
                ), ' + '
                tarjetaControl AS (
                    SELECT idempleado, idperiodo, valor, idtarjetaincapacidad, ti.mnemonico AS mnemonico
                    FROM nom10009 AS fi
                    INNER JOIN nom10022 ti
                        ON fi.idtipoincidencia = ti.idtipoincidencia
                    WHERE
                        ti.mnemonico IN (''INC'', ''FINJ'')
                        AND idperiodo = @idPeriodo
                    UNION ALL
                    SELECT idempleado, idperiodo, valor, idtarjetaincapacidad, ti.mnemonico AS mnemonico
                    FROM NOM10010 AS fi
                    INNER JOIN nom10022 ti
                        ON fi.idtipoincidencia = ti.idtipoincidencia
                    WHERE
                        ti.mnemonico IN (''INC'', ''FINJ'')
                        AND idperiodo = @idPeriodo
                ), ' + '
                TarjetaControlAgrupado AS (
                    SELECT
                        idempleado,
                        SUM(CASE WHEN mnemonico = ''INC'' THEN valor ELSE 0 END) AS incapacidad,
                        SUM(CASE WHEN mnemonico = ''FINJ'' THEN valor ELSE 0 END) AS faltas
                    FROM tarjetaControl
                    GROUP BY
                        idempleado
                ), ' + '
                Movimientos AS (
                    SELECT idempleado, idperiodo, his.idconcepto, valor, importetotal, con.descripcion, con.tipoconcepto
                    FROM Nom10007 AS his
					INNER JOIN nom10004 con
                        ON his.idconcepto = con.idconcepto
                    WHERE
                        importetotal > 0
                        AND idperiodo = @idPeriodo
						AND con.tipoconcepto IN (''P'',''D'')
						AND (
                                con.descripcion LIKE ''%ahorro%'' -- caja de ahorro
                                OR con.descripcion LIKE ''%infonavit%''
                                OR con.descripcion LIKE ''%fonacot%''
                                OR con.numeroconcepto IN (20) -- prima vacacional
							)
                    UNION ALL
                    SELECT idempleado, idperiodo, actual.idconcepto, valor, importetotal, con.descripcion, con.tipoconcepto
                    FROM Nom10008 AS actual
					INNER JOIN nom10004 con
                        ON actual.idconcepto = con.idconcepto
                    WHERE
                        importetotal > 0
                        AND idperiodo = @idPeriodo
						AND con.tipoconcepto IN (''P'',''D'')
						AND (
                                con.descripcion LIKE ''%ahorro%'' -- caja de ahorro
                                OR con.descripcion LIKE ''%infonavit%''
                                OR con.descripcion LIKE ''%fonacot%''
                                OR con.numeroconcepto IN (20) -- prima vacacional
							)
                ), ' + '
                MovimientosSuma AS (
                    SELECT
                        emp.codigoempleado,
                        ISNULL(
                            CASE WHEN movP.tipoconcepto = ''N'' THEN movP.importetotal ELSE 0 END
                        ,0) AS sueldo,
                        pdo.descripcion,
                        pdo.tipoconcepto AS tipoConcepto,
                        SUM(pdo.valor) AS cantidad,
                        SUM(pdo.importetotal) AS monto,
                        emp.ccampoextranumerico3 AS pension
                    FROM nom10001 emp
                    LEFT JOIN nom10034 empPeriodo
                        ON emp.idempleado = empPeriodo.idempleado
                            AND empPeriodo.cidperiodo = @idPeriodo
					LEFT JOIN nom10002 AS periodo
						ON empPeriodo.cidperiodo = periodo.idperiodo
                    LEFT JOIN Movimientos pdo
                        ON empPeriodo.cidperiodo = pdo.idperiodo
							AND emp.idempleado = pdo.idempleado
					LEFT JOIN MovimientosRetro AS movP
                        ON empPeriodo.idempleado = movP.idempleado
                    LEFT JOIN TarjetaControlAgrupado AS tc
                        ON empPeriodo.idempleado = tc.idempleado
                    WHERE
                        empPeriodo.idtipoperiodo = @idTipoPeriodo
                        AND empPeriodo.idempleado BETWEEN @idEmpleadoInicial AND @idEmpleadoFinal
                        AND empPeriodo.estadoempleado IN (''A'', ''R'')
                        AND emp.TipoRegimen IN (''05'', ''06'', ''07'', ''08'', ''09'', ''10'', ''11'')
                    GROUP BY
                        emp.codigoempleado
                        , emp.ccampoextranumerico2
                        , empPeriodo.cdiaspagados
                        , pdo.descripcion
                        , pdo.tipoconcepto
                        , emp.ccampoextranumerico3
                        , periodo.diasdepago
                        , empPeriodo.fechaalta
                        , periodo.fechainicio
                        , periodo.fechafin
                        , tc.faltas
                        , tc.incapacidad
                        , movP.tipoconcepto
                        , movP.importetotal
                ), ' + '
                IncidenciasNormalizadas AS (
					SELECT
						emp.codigoempleado as codigoempleado,
						x.descripcion AS descripcion,
						''P'' AS tipoConcepto,
						x.valor AS valor,
						emp.ccampoextranumerico2 AS pagoPorDia,
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
                            AND emp.TipoRegimen IN (''02'', ''03'', ''04'')
					CROSS APPLY (VALUES
						(''Bono'',                      ngid.bono),
						(''Comisiones'',                ngid.comision),
						(''Dia Festivo cantidad'',      ngid.cantidad_dias_festivos),
						(''Dia Festivo monto'',         ISNULL(ngid.cantidad_dias_festivos, 0) * emp.ccampoextranumerico2 * 2),
						(''Horas Extra Doble cantidad'', ngid.horas_extra_doble_cantidad),
						(''Horas Extra Doble monto'',    ISNULL(ngid.horas_extra_doble_cantidad, 0) * (emp.ccampoextranumerico2 / 8.0) * 2),
						(''Horas Extra Triple cantidad'', ngid.horas_extra_triple_cantidad),
						(''Horas Extra Triple monto'',    ISNULL(ngid.horas_extra_triple_cantidad, 0) * (emp.ccampoextranumerico2 / 8.0) * 3),
						(''Pago adicional'',             ngid.pago_adicional),
						(''Premio puntualidad'',         ngid.premio_puntualidad),
						(''Prima Dominical cantidad'',   ngid.cantidad_prima_dominical),
						(''Prima Dominical monto'',      ISNULL(ngid.cantidad_prima_dominical, 0) * emp.ccampoextranumerico2 * 0.25)
					) AS x(descripcion, valor)
					WHERE
                        (@idNominaGapeIncidencia IS NULL OR ngi.id = @idNominaGapeIncidencia)
					    AND ngi.id_nomina_gape_empresa = @idNominaGapeEmpresa
					    AND ngid.id_empleado BETWEEN @idEmpleadoInicial AND @idEmpleadoFinal
				), ' + '
                TotalesPorEmpleado AS (
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
                        WHERE
                            descripcion NOT LIKE ''%cantidad%''
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

            //return $sql;

            /*
            dd([
                'row' => $sql,
            ]);
            */

            $result = DB::connection('sqlsrv_dynamic')->select($sql);

            return $result;
        } catch (\Exception $e) {
            return [];
        }
    }

    private function datosAsimilados_3(Request $request)
    {
        try {
            $idNominaGapeCliente = $request->id_nomina_gape_cliente;
            $idNominaGapeEmpresa = $request->id_nomina_gape_empresa;

            $idNominaGapeEsquema = $request->id_nomina_gape_esquema;
            $idEsquemaCombinacion = $request->id_esquema;

            $idTipoPeriodo = $request->id_tipo_periodo;
            $idPeriodo = $request->periodo_inicial;

            $idEmpleadoInicial = $request->empleado_inicial;
            $idEmpleadoFinal = $request->empleado_final;

            $sql = "
                DECLARE @idNominaGapeCliente INT;
                DECLARE @idNominaGapeEmpresa INT;

                DECLARE @idNominaGapeEsquema INT;
                DECLARE @idEsquemaCombinacion INT;

                DECLARE @idPeriodo INT;
                DECLARE @idTipoPeriodo INT;

                DECLARE @idEmpleadoInicial INT;
                DECLARE @idEmpleadoFinal INT;

                SET @idNominaGapeCliente = $idNominaGapeCliente;
                SET @idNominaGapeEmpresa = $idNominaGapeEmpresa;

                SET @idNominaGapeEsquema = $idNominaGapeEsquema;
                SET @idEsquemaCombinacion = $idEsquemaCombinacion;

                SET @idPeriodo = $idPeriodo;
                SET @idTipoPeriodo = $idTipoPeriodo;

                SET @idEmpleadoInicial = $idEmpleadoInicial;
                SET @idEmpleadoFinal = $idEmpleadoFinal;

                DECLARE @cols NVARCHAR(MAX),
                        @query NVARCHAR(MAX);

                ;WITH Previsiones AS (
                    SELECT
                        ngcp.id_concepto AS idconcepto
                    FROM [becma-core2].dbo.nomina_gape_empresa_periodo_combinacion_parametrizacion AS ngepcp
                    INNER JOIN [becma-core2].dbo.nomina_gape_combinacion_prevision AS ngcp
                        ON ngepcp.id = ngcp.nomina_gape_empresa_periodo_combinacion_parametrizacion
                    WHERE
                        ngepcp.id_nomina_gape_cliente = @idNominaGapeCliente
                        AND ngepcp.id_nomina_gape_empresa = @idNominaGapeEmpresa
                        AND ngepcp.id_nomina_gape_cliente_esquema_combinacion = @idEsquemaCombinacion
                        AND (
                                ngepcp.id_nomina_gape_tipo_periodo = @idTipoPeriodo
                                OR ngepcp.idtipoperiodo = @idTipoPeriodo
                            )
                ),
                Movimientos AS (
                    SELECT idempleado, idperiodo, his.idconcepto, valor, importetotal, con.descripcion, con.tipoconcepto
                    FROM Nom10007 AS his
                    INNER JOIN nom10004 con
                        ON his.idconcepto = con.idconcepto
                    LEFT JOIN Previsiones p
		                ON con.idconcepto = p.idconcepto
                    WHERE importetotal > 0
                        AND idperiodo = @idPeriodo
                        AND
                        (
                        -- (Sueldo, Vacaciones, Prima vacacional, Retroactivos)
                        (con.tipoconcepto = 'P' AND (
                                con.numeroconcepto IN (19, 20, 16)
                                OR con.descripcion LIKE '%asimilados%'
                                OR p.idconcepto IS NOT NULL
                            ))
                        OR
                        (con.tipoconcepto = 'D' AND (
                                con.descripcion LIKE '%ahorro%' -- caja de ahorro
                                OR con.descripcion LIKE '%infonavit%'
                                OR con.descripcion LIKE '%fonacot%'
                                OR con.descripcion LIKE '%alimenticia%'
                                OR con.descripcion LIKE '%sindical%'
                                OR con.numeroconcepto IN (52,35,45) -- Imss, isr
                                ))
                        )
                    UNION ALL
                    SELECT idempleado, idperiodo, actual.idconcepto, valor, importetotal, con.descripcion, con.tipoconcepto
                    FROM Nom10008 AS actual
                    INNER JOIN nom10004 con
                        ON actual.idconcepto = con.idconcepto
                    LEFT JOIN Previsiones p
		                ON con.idconcepto = p.idconcepto
                    WHERE
                        importetotal > 0
                        AND idperiodo = @idPeriodo
                        AND (
                        -- (Sueldo, Vacaciones, Prima vacacional, Retroactivos)
                        (con.tipoconcepto = 'P' AND (
                                con.numeroconcepto IN (19, 20, 16)
                                OR con.descripcion LIKE '%asimilados%'
                                OR p.idconcepto IS NOT NULL
                            ))
                        OR
                        (con.tipoconcepto = 'D' AND (
                                con.descripcion LIKE '%ahorro%' -- caja de ahorro
                                OR con.descripcion LIKE '%infonavit%'
                                OR con.descripcion LIKE '%fonacot%'
                                OR con.descripcion LIKE '%alimenticia%'
                                OR con.descripcion LIKE '%sindical%'
                                OR con.numeroconcepto IN (52,35,45) -- Imss, isr
                                ))
                        )
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
                    WHERE
                        empPeriodo.idtipoperiodo = @idTipoPeriodo
                        AND empPeriodo.idempleado BETWEEN @idEmpleadoInicial AND @idEmpleadoFinal
                        AND empPeriodo.estadoempleado IN ('A', 'R')
                        AND emp.TipoRegimen IN ('05', '06', '07', '08', '09', '10', '11')
                    GROUP BY
                        pdo.descripcion
                        , pdo.tipoconcepto
                ),
                Encabezados AS (
                    SELECT * FROM (VALUES
                        ('TOTAL PERCEPCIONES FISCAL', 'P', 1000, 1),
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
                    DECLARE @idNominaGapeCliente INT;
                    DECLARE @idNominaGapeEmpresa INT;

                    DECLARE @idNominaGapeEsquema INT;
                    DECLARE @idEsquemaCombinacion INT;

                    DECLARE @idPeriodo INT;
                    DECLARE @idTipoPeriodo INT;

                    DECLARE @idEmpleadoInicial INT;
                    DECLARE @idEmpleadoFinal INT;

                    SET @idNominaGapeCliente = $idNominaGapeCliente;
                    SET @idNominaGapeEmpresa = $idNominaGapeEmpresa;

                    SET @idNominaGapeEsquema = $idNominaGapeEsquema;
                    SET @idEsquemaCombinacion = $idEsquemaCombinacion;

                    SET @idPeriodo = $idPeriodo;
                    SET @idTipoPeriodo = $idTipoPeriodo;

                    SET @idEmpleadoInicial = $idEmpleadoInicial;
                    SET @idEmpleadoFinal = $idEmpleadoFinal;

                    ' + '

                    ;WITH Previsiones AS (
                        SELECT
                            ngcp.id_concepto AS idconcepto
                        FROM [becma-core2].dbo.nomina_gape_empresa_periodo_combinacion_parametrizacion AS ngepcp
                        INNER JOIN [becma-core2].dbo.nomina_gape_combinacion_prevision AS ngcp
                            ON ngepcp.id = ngcp.nomina_gape_empresa_periodo_combinacion_parametrizacion
                        WHERE ngepcp.id_nomina_gape_cliente = @idNominaGapeCliente
                            AND ngepcp.id_nomina_gape_empresa = @idNominaGapeEmpresa
                            AND ngepcp.id_nomina_gape_cliente_esquema_combinacion = @idEsquemaCombinacion
                            AND (
                                    ngepcp.id_nomina_gape_tipo_periodo = @idTipoPeriodo
                                    OR ngepcp.idtipoperiodo = @idTipoPeriodo
                                )
                    ), ' + '
                    Movimientos AS (
                        SELECT idempleado, idperiodo, his.idconcepto, valor, importetotal, con.descripcion, con.tipoconcepto
                        FROM Nom10007 AS his
                        INNER JOIN nom10004 con
                            ON his.idconcepto = con.idconcepto
                        LEFT JOIN Previsiones p
		                    ON con.idconcepto = p.idconcepto
                        WHERE
                            importetotal > 0
                            AND idperiodo = @idPeriodo
                            AND (
                            -- (Sueldo, Vacaciones, Prima vacacional, Retroactivos)
                            (con.tipoconcepto = ''P'' AND (
                                    con.numeroconcepto IN (19, 20, 16)
                                    OR con.descripcion LIKE ''%asimilados%''
                                    OR p.idconcepto IS NOT NULL
                                ))
                            OR
                            (con.tipoconcepto = ''D'' AND (
                                    con.descripcion LIKE ''%ahorro%''
                                    OR con.descripcion LIKE ''%infonavit%''
                                    OR con.descripcion LIKE ''%fonacot%''
                                    OR con.descripcion LIKE ''%alimenticia%''
                                    OR con.descripcion LIKE ''%sindical%''
                                    OR con.numeroconcepto IN (52,35,45) -- Imss, isr
                                    ))
                            )
                        UNION ALL ' + '
                        SELECT idempleado, idperiodo, actual.idconcepto, valor, importetotal, con.descripcion, con.tipoconcepto
                        FROM Nom10008 AS actual
                        INNER JOIN nom10004 con
                            ON actual.idconcepto = con.idconcepto
                        LEFT JOIN Previsiones p
		                    ON con.idconcepto = p.idconcepto
                        WHERE
                            importetotal > 0
                            AND idperiodo = @idPeriodo
                            AND (
                            -- (Sueldo, Vacaciones, Prima vacacional, Retroactivos)
                            (con.tipoconcepto = ''P'' AND (
                                    con.numeroconcepto IN (19, 20, 16)
                                    OR con.descripcion LIKE ''%asimilados%''
                                    OR p.idconcepto IS NOT NULL
                                ))
                            OR
                            (con.tipoconcepto = ''D'' AND (
                                    con.descripcion LIKE ''%ahorro%''
                                    OR con.descripcion LIKE ''%infonavit%''
                                    OR con.descripcion LIKE ''%fonacot%''
                                    OR con.descripcion LIKE ''%alimenticia%''
                                    OR con.descripcion LIKE ''%sindical%''
                                    OR con.numeroconcepto IN (52,35,45) -- Imss, isr
                                    ))
                            )
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
                        LEFT JOIN Movimientos pdo
                            ON empPeriodo.cidperiodo = pdo.idperiodo
                                AND emp.idempleado = pdo.idempleado
                        WHERE
                            empPeriodo.idtipoperiodo = @idTipoPeriodo
                            AND empPeriodo.estadoempleado IN (''A'', ''R'')
                            AND emp.TipoRegimen IN (''05'', ''06'', ''07'', ''08'', ''09'', ''10'', ''11'')
                            AND empPeriodo.idempleado BETWEEN @idEmpleadoInicial AND @idEmpleadoFinal
                        GROUP BY
                            emp.codigoempleado
                            , empPeriodo.sueldodiario
                            , empPeriodo.sueldointegrado
                            , pdo.descripcion
                            , pdo.tipoconcepto
                            , emp.ccampoextranumerico3
                    ), ' + '
                    TotalesPorEmpleado AS (
                        SELECT
                            codigoempleado,
                            sd,
                            sdi,
                            SUM(CASE WHEN tipoConcepto = ''P'' THEN monto ELSE 0 END) AS total_percepciones,
                            SUM(CASE WHEN tipoConcepto = ''D'' THEN monto ELSE 0 END) AS total_deducciones,
                            pension AS porcentajePension
                        FROM MovimientosSuma
                        GROUP BY
                            codigoempleado
                            , pension
                            , sdi
                            , sd
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
                            ''TOTAL DEDUCCIONES FISCAL'' AS columna,
                            t.total_deducciones AS valor
                        FROM TotalesPorEmpleado t
                        UNION ALL
                        SELECT
                            t.codigoempleado,
                            t.sd,
                            t.sdi,
                            ''NETO FISCAL'' AS columna,
                            (t.total_percepciones) - (t.total_deducciones) AS valor
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

    private function datosAsimilados_4(Request $request)
    {
        try {
            $idNominaGapeCliente = $request->id_nomina_gape_cliente;
            $idNominaGapeEmpresa = $request->id_nomina_gape_empresa;

            $idNominaGapeEsquema = $request->id_nomina_gape_esquema;
            $idEsquemaCombinacion = $request->id_esquema;

            $idTipoPeriodo = $request->id_tipo_periodo;
            $idPeriodo = $request->periodo_inicial;

            $idEmpleadoInicial = $request->empleado_inicial;
            $idEmpleadoFinal = $request->empleado_final;

            $idNominaGapeIncidencia = $request->id_nomina_gape_incidencia ?? 0;

            $sql = "
                DECLARE @idNominaGapeCliente INT;
                DECLARE @idNominaGapeEmpresa INT;

                DECLARE @idNominaGapeEsquema INT;
                DECLARE @idEsquemaCombinacion INT;

                DECLARE @idPeriodo INT;
                DECLARE @idTipoPeriodo INT;

                DECLARE @idEmpleadoInicial INT;
                DECLARE @idEmpleadoFinal INT;

				DECLARE @idNominaGapeIncidencia INT;

                SET @idNominaGapeCliente = $idNominaGapeCliente;
                SET @idNominaGapeEmpresa = $idNominaGapeEmpresa;

                SET @idNominaGapeEsquema = $idNominaGapeEsquema;
                SET @idEsquemaCombinacion = $idEsquemaCombinacion;

                SET @idPeriodo = $idPeriodo;
                SET @idTipoPeriodo = $idTipoPeriodo;

                SET @idEmpleadoInicial = $idEmpleadoInicial;
                SET @idEmpleadoFinal = $idEmpleadoFinal;

				SET @idNominaGapeIncidencia = $idNominaGapeIncidencia;

                DECLARE @cols NVARCHAR(MAX),
                        @sql  NVARCHAR(MAX);
                ;WITH ParamConfig AS (
                    SELECT
                        nge.esquema AS descripcion
                        , ngce.tope
                        , ngce.orden
                    FROM [becma-core2].[dbo].nomina_gape_cliente_esquema_combinacion AS ngce
                    INNER JOIN [becma-core2].[dbo].nomina_gape_esquema AS nge
                        ON ngce.id_nomina_gape_esquema = nge.id
                    WHERE
                        ngce.id_nomina_gape_cliente = @idNominaGapeCliente
                        AND ngce.id_nomina_gape_empresa = @idNominaGapeEmpresa
                        AND ngce.combinacion = @idEsquemaCombinacion
                        AND ngce.orden > 1
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
                titulos AS (
                    SELECT descripcion AS descripcion, orden FROM Encabezados
                    UNION ALL
                    SELECT descripcion, orden FROM ParamConfig
                )
                SELECT @cols = STUFF((
                    SELECT ', ' + QUOTENAME(descripcion)
                    FROM titulos
                    ORDER BY orden
                    FOR XML PATH(''), TYPE
                ).value('.', 'NVARCHAR(MAX)'), 1, 2, '');

                SET @sql = CAST('' AS NVARCHAR(MAX)) + '
                DECLARE @idNominaGapeCliente INT;
                DECLARE @idNominaGapeEmpresa INT;

                DECLARE @idNominaGapeEsquema INT;
                DECLARE @idEsquemaCombinacion INT;

                DECLARE @idPeriodo INT;
                DECLARE @idTipoPeriodo INT;

                DECLARE @idEmpleadoInicial INT;
                DECLARE @idEmpleadoFinal INT;

				DECLARE @idNominaGapeIncidencia INT;

                SET @idNominaGapeCliente = $idNominaGapeCliente;
                SET @idNominaGapeEmpresa = $idNominaGapeEmpresa;

                SET @idNominaGapeEsquema = $idNominaGapeEsquema;
                SET @idEsquemaCombinacion = $idEsquemaCombinacion;

                SET @idPeriodo = $idPeriodo;
                SET @idTipoPeriodo = $idTipoPeriodo;

                SET @idEmpleadoInicial = $idEmpleadoInicial;
                SET @idEmpleadoFinal = $idEmpleadoFinal;

				SET @idNominaGapeIncidencia = $idNominaGapeIncidencia;

                ' + '

                ;WITH MovimientosRetro AS (
                        SELECT idempleado, idperiodo, his.idconcepto, valor, importetotal, con.descripcion, con.tipoconcepto
                        FROM Nom10007 AS his
                            INNER JOIN nom10004 con
                                ON his.idconcepto = con.idconcepto
                        WHERE
                            idperiodo = @idPeriodo
                            AND con.tipoconcepto IN (''P'', ''N'')
                            AND (con.descripcion LIKE ''%retroactivo%''
                                OR con.descripcion LIKE ''%Neto%'')
                        UNION ALL
                        SELECT idempleado, idperiodo, actual.idconcepto, valor, importetotal, con.descripcion, con.tipoconcepto
                        FROM Nom10008 AS actual
                        INNER JOIN nom10004 con
                            ON actual.idconcepto = con.idconcepto
                        WHERE
                            idperiodo = @idPeriodo
                            AND con.tipoconcepto IN (''P'', ''N'')
                            AND (con.descripcion LIKE ''%retroactivo%''
                                OR con.descripcion LIKE ''%Neto%'')
                    ), ' + '
                    tarjetaControl AS (
                        SELECT idempleado, idperiodo, valor, idtarjetaincapacidad, ti.mnemonico AS mnemonico
                        FROM nom10009 AS fi
                        INNER JOIN nom10022 ti
                            ON fi.idtipoincidencia = ti.idtipoincidencia
                        WHERE
                            ti.mnemonico IN (''INC'', ''FINJ'')
                            AND idperiodo = @idPeriodo
                        UNION ALL
                        SELECT idempleado, idperiodo, valor, idtarjetaincapacidad, ti.mnemonico AS mnemonico
                        FROM NOM10010 AS fi
                        INNER JOIN nom10022 ti
                            ON fi.idtipoincidencia = ti.idtipoincidencia
                        WHERE
                            ti.mnemonico IN (''INC'', ''FINJ'')
                            AND idperiodo = @idPeriodo
                    ), ' + '
                    TarjetaControlAgrupado AS (
                        SELECT
                            idempleado,
                            SUM(CASE WHEN mnemonico = ''INC'' THEN valor ELSE 0 END) AS incapacidad,
                            SUM(CASE WHEN mnemonico = ''FINJ'' THEN valor ELSE 0 END) AS faltas
                        FROM tarjetaControl
                        GROUP BY
                            idempleado
                    ), ' + '
                    Movimientos AS (
                        SELECT idempleado, idperiodo, his.idconcepto, valor, importetotal, con.descripcion, con.tipoconcepto, con.numeroconcepto
                        FROM Nom10007 AS his
                        INNER JOIN nom10004 con
                            ON his.idconcepto = con.idconcepto
                        WHERE
                            importetotal > 0
                            AND idperiodo = @idPeriodo
                            AND con.tipoconcepto IN (''P'',''D'')
                        UNION ALL
                        SELECT idempleado, idperiodo, actual.idconcepto, valor, importetotal, con.descripcion, con.tipoconcepto, con.numeroconcepto
                        FROM Nom10008 AS actual
                        INNER JOIN nom10004 con
                            ON actual.idconcepto = con.idconcepto
                        WHERE
                            importetotal > 0
                            AND idperiodo = @idPeriodo
                            AND con.tipoconcepto IN (''P'',''D'')
                    ), ' + '
                    MovimientosSumaQ2 AS (
                        SELECT
                            emp.codigoempleado,
                            ISNULL(
                                CASE WHEN movP.tipoconcepto = ''N'' THEN movP.importetotal ELSE 0 END
                            ,0) AS sueldo,
                            pdo.descripcion,
                            pdo.tipoconcepto AS tipoConcepto,
                            SUM(pdo.valor) AS cantidad,
                            SUM(pdo.importetotal) AS monto,
                            emp.ccampoextranumerico3 AS pension
                        FROM nom10001 emp
                        INNER JOIN nom10034 empPeriodo
                            ON emp.idempleado = empPeriodo.idempleado
                                AND empPeriodo.cidperiodo = @idPeriodo
                        INNER JOIN nom10002 AS periodo
                            ON empPeriodo.cidperiodo = periodo.idperiodo
                        LEFT JOIN Movimientos pdo
                            ON empPeriodo.cidperiodo = pdo.idperiodo
                                AND emp.idempleado = pdo.idempleado
                                AND (
                                    pdo.descripcion LIKE ''%ahorro%'' -- caja de ahorro
                                    OR pdo.descripcion LIKE ''%infonavit%''
                                    OR pdo.descripcion LIKE ''%fonacot%''
                                    OR pdo.numeroconcepto IN (20) -- prima vacacional
                                )
                        LEFT JOIN MovimientosRetro AS movP
                            ON empPeriodo.idempleado = movP.idempleado
                        LEFT JOIN TarjetaControlAgrupado AS tc
                            ON empPeriodo.idempleado = tc.idempleado
                        WHERE
                            empPeriodo.idtipoperiodo = @idTipoPeriodo
                            AND empPeriodo.estadoempleado IN (''A'', ''R'')
                            AND emp.TipoRegimen IN (''05'', ''06'', ''07'', ''08'', ''09'', ''10'', ''11'')
                            AND empPeriodo.idempleado BETWEEN @idEmpleadoInicial AND @idEmpleadoFinal
                        GROUP BY
                            emp.codigoempleado
                            , emp.ccampoextranumerico2
                            , empPeriodo.cdiaspagados
                            , pdo.descripcion
                            , pdo.tipoconcepto
                            , emp.ccampoextranumerico3
                            , periodo.diasdepago
                            , empPeriodo.fechaalta
                            , periodo.fechainicio
                            , periodo.fechafin
                            , tc.faltas
                            , tc.incapacidad
                            , movP.tipoconcepto
                            , movP.importetotal
                    ), ' + '
                    IncidenciasNormalizadasQ2 AS (
                        SELECT
                            emp.codigoempleado as codigoempleado,
                            x.descripcion AS descripcion,
                            ''P'' AS tipoConcepto,
                            x.valor AS valor,
                            emp.ccampoextranumerico2 AS pagoPorDia,
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
                            (''Bono'',                      ngid.bono),
                            (''Comisiones'',                ngid.comision),
                            (''Dia Festivo cantidad'',      ngid.cantidad_dias_festivos),
                            (''Dia Festivo monto'',         ISNULL(ngid.cantidad_dias_festivos, 0) * emp.ccampoextranumerico2 * 2),
                            (''Horas Extra Doble cantidad'', ngid.horas_extra_doble_cantidad),
                            (''Horas Extra Doble monto'',    ISNULL(ngid.horas_extra_doble_cantidad, 0) * (emp.ccampoextranumerico2 / 8.0) * 2),
                            (''Horas Extra Triple cantidad'', ngid.horas_extra_triple_cantidad),
                            (''Horas Extra Triple monto'',    ISNULL(ngid.horas_extra_triple_cantidad, 0) * (emp.ccampoextranumerico2 / 8.0) * 3),
                            (''Pago adicional'',             ngid.pago_adicional),
                            (''Premio puntualidad'',         ngid.premio_puntualidad),
                            (''Prima Dominical cantidad'',   ngid.cantidad_prima_dominical),
                            (''Prima Dominical monto'',      ISNULL(ngid.cantidad_prima_dominical, 0) * emp.ccampoextranumerico2 * 0.25)
                        ) AS x(descripcion, valor)
                        WHERE
                            (@idNominaGapeIncidencia IS NULL OR ngi.id = @idNominaGapeIncidencia)
                            AND ngi.id_nomina_gape_empresa = @idNominaGapeEmpresa
                            AND ngid.id_empleado BETWEEN @idEmpleadoInicial AND @idEmpleadoFinal
                            AND emp.TipoRegimen IN (''05'', ''06'', ''07'', ''08'', ''09'', ''10'', ''11'')
                    ), ' + '
                    PreTotalesPorEmpleadoGeneralQ2 AS (
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
                            WHERE descripcion NOT LIKE ''%cantidad%''
                        ) AS x
                        GROUP BY
                            codigoempleado
                            , pension
                    ), ' + '
                    PensionGeneralQ2 AS (
                        SELECT
                            codigoempleado,
                            (CASE WHEN porcentajePension > 0 THEN (sueldo + total_percepciones_sin_sueldo) * (porcentajePension / 100) ELSE 0 END) pension_q2
                        FROM PreTotalesPorEmpleadoGeneralQ2
                        WHERE
                            porcentajePension > 0
                    ), ' + '
                    TotalesPorEmpleadoGeneralQ2 AS (
                        SELECT
                            codigoempleado,
                            sueldo,
                            sueldo + total_percepciones_sin_sueldo AS total_percepciones,
                            total_deducciones + (CASE WHEN porcentajePension > 0 THEN (sueldo + total_percepciones_sin_sueldo) * (porcentajePension / 100) ELSE 0 END) total_deducciones,
                            (sueldo + total_percepciones_sin_sueldo) - (total_deducciones + CASE WHEN porcentajePension > 0 THEN (sueldo + total_percepciones_sin_sueldo) * (porcentajePension / 100) ELSE 0 END) AS neto_a_pagar
                        FROM PreTotalesPorEmpleadoGeneralQ2
                    ), ' + '
                    Previsiones AS (
                        SELECT
                            ngcp.id_concepto AS idconcepto
                        FROM [becma-core2].dbo.nomina_gape_empresa_periodo_combinacion_parametrizacion AS ngepcp
                        INNER JOIN [becma-core2].dbo.nomina_gape_combinacion_prevision AS ngcp
                            ON ngepcp.id = ngcp.nomina_gape_empresa_periodo_combinacion_parametrizacion
                        WHERE
                            ngepcp.id_nomina_gape_cliente = @idNominaGapeCliente
                            AND ngepcp.id_nomina_gape_empresa = @idNominaGapeEmpresa
                            AND ngepcp.id_nomina_gape_cliente_esquema_combinacion = @idEsquemaCombinacion
                            AND (
                                    ngepcp.id_nomina_gape_tipo_periodo = @idTipoPeriodo
                                    OR ngepcp.idtipoperiodo = @idTipoPeriodo
                                )
                    ), ' + '
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
                            LEFT JOIN Movimientos pdo
                                ON empPeriodo.cidperiodo = pdo.idperiodo
                                AND emp.idempleado = pdo.idempleado
                                AND (
                                    -- (Sueldo, Vacaciones, Prima vacacional, Retroactivos)
                                        (pdo.tipoconcepto = ''P''
                                        AND (
                                            pdo.numeroconcepto IN (19, 20, 16)
                                            OR pdo.descripcion LIKE ''%asimilados%''
                                            OR EXISTS (
                                                    SELECT 1
                                                    FROM Previsiones p
                                                    WHERE p.idconcepto = pdo.idconcepto
                                                )
                                            )
                                        )
                                    OR
                                    (
                                        pdo.tipoconcepto = ''D''
                                        AND (
                                            pdo.descripcion LIKE ''%ahorro%'' -- caja de ahorro
                                            OR pdo.descripcion LIKE ''%infonavit%''
                                            OR pdo.descripcion LIKE ''%fonacot%''
                                            OR pdo.descripcion LIKE ''%alimenticia%''
                                            OR pdo.descripcion LIKE ''%sindical%''
                                            OR pdo.numeroconcepto IN (52,35,45) -- Imss, isr
                                            )
                                    )
                                )
                            WHERE
                                empPeriodo.idtipoperiodo = @idTipoPeriodo
                                AND empPeriodo.estadoempleado IN (''A'', ''R'')
                                AND emp.TipoRegimen IN (''05'', ''06'', ''07'', ''08'', ''09'', ''10'', ''11'')
                                AND empPeriodo.idempleado BETWEEN @idEmpleadoInicial AND @idEmpleadoFinal
                            GROUP BY
                                emp.codigoempleado
                                , empPeriodo.sueldodiario
                                , empPeriodo.sueldointegrado
                                , pdo.descripcion
                                , pdo.tipoconcepto
                                , emp.ccampoextranumerico3
                    ), ' + '
                    PensionGeneralQ3 AS (
                        SELECT
                            codigoempleado,
                            SUM(CASE WHEN tipoConcepto = ''D'' AND descripcion LIKE ''%alimenticia%'' THEN monto ELSE 0 END) AS pension_q3
                        FROM MovimientosSumaQ3
                        WHERE
                            pension > 0
                        GROUP BY
                            codigoempleado
                            , pension
                    ), ' + '
                    PensionResultado AS (
                        SELECT
                            q2.codigoempleado,
                            ISNULL(q2.pension_q2, 0) - ISNULL(q3.pension_q3, 0) AS pension_restante
                        FROM PensionGeneralQ2 q2
                        LEFT JOIN PensionGeneralQ3 q3
                            ON q2.codigoempleado = q3.codigoempleado
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
                            GROUP BY
                                codigoempleado
                                , pension
                                , sdi
                                , sd
                    ), ' + '
                    preTotalesProrrateo AS (
                        SELECT
                            q2.codigoempleado,
                            (q2.neto_a_pagar - (q3.total_percepciones -  q3.total_deducciones)) + ISNULL(pr.pension_restante, 0) AS excedente
                        FROM TotalesPorEmpleadoGeneralQ2 q2
                        LEFT JOIN TotalesPorEmpleadoQ3 q3
                            ON q2.codigoempleado = q3.codigoempleado
                        LEFT JOIN PensionResultado pr
                            ON q3.codigoempleado = pr.codigoempleado
                    ), ' + '
                    ParamConfig AS (
                        SELECT
                            nge.esquema AS concepto
                            , ngce.tope
                            , ngce.orden
                        FROM [becma-core2].[dbo].nomina_gape_cliente_esquema_combinacion AS ngce
                        INNER JOIN [becma-core2].[dbo].nomina_gape_esquema AS nge
                            ON ngce.id_nomina_gape_esquema = nge.id
                        WHERE
                            ngce.id_nomina_gape_cliente = @idNominaGapeCliente
                            AND ngce.id_nomina_gape_empresa = @idNominaGapeEmpresa
                            AND ngce.combinacion = @idEsquemaCombinacion
                            AND ngce.orden > 1
                    ), ' + '
                    BaseProrrateo AS (
                        SELECT
                            p.codigoempleado,
                            CAST(p.excedente AS DECIMAL(18,2)) AS netoAPagar,
                            c.concepto,
                            c.tope,
                            c.orden,
                            ROW_NUMBER() OVER (PARTITION BY p.codigoempleado ORDER BY c.orden) AS rn
                        FROM preTotalesProrrateo p
                        CROSS JOIN ParamConfig c
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
                        GROUP BY
                            codigoempleado
                    ), ' + '
                    PensionExcedentes AS (
                        SELECT
                            q3.codigoempleado,
                            ''Pension Excedente'' AS concepto,
                             ISNULL(pr.pension_restante, 0) AS pension_excedente
                        FROM TotalesPorEmpleadoQ3 q3
						LEFT JOIN PensionResultado pr
                            ON pr.codigoempleado = q3.codigoempleado
                    ), ' + '
                    DeduccionExcedentes AS (
                        SELECT
                            pe.codigoempleado,
                            ISNULL(SUM(pe.pension_excedente) , 0) AS total_deducciones_excedente
                        FROM TotalesPorEmpleadoQ3 q3
						LEFT JOIN PensionExcedentes pe
							ON pe.codigoempleado = q3.codigoempleado
						GROUP BY
                            pe.codigoempleado
                    ), ' + '
                    NetoExcedentes AS (
                        SELECT
                            pe.codigoempleado,
                            (pe.total_percepciones_excedente - de.total_deducciones_excedente) AS neto_excedente
                        FROM PercepcionesExcedentes pe
                        LEFT JOIN DeduccionExcedentes de
                            ON pe.codigoempleado = de.codigoempleado
                    ), ' + '
                    NetoTotalAPagar AS (
                        SELECT
                            ne.codigoempleado,
                            (q3.total_percepciones - q3.total_deducciones) + (ne.neto_excedente) AS neto_total_a_pagar
                        FROM NetoExcedentes ne
                        LEFT JOIN TotalesPorEmpleadoQ3 AS q3
                            ON ne.codigoempleado = q3.codigoempleado
                    ), ' + '
                    ProrrateoFinal AS (
                        -- conceptos del prorrateo (columnas dinámicas)
                        SELECT
                            codigoempleado,
                            concepto,
                            monto_asignado
                        FROM ProrrateoRecursivo
                        UNION ALL

                        -- TOTAL PERCEPCION EXCEDENTE
                        SELECT
                            codigoempleado,
                            ''TOTAL PERCEPCION EXCEDENTE'' AS concepto,
                            total_percepciones_excedente AS monto_asignado
                        FROM PercepcionesExcedentes

                        UNION ALL

                        -- PENSION EXCEDENTE
                        SELECT
                            pe.codigoempleado AS codigoempleado,
                            pe.concepto AS concepto,
                            pe.pension_excedente AS monto
                        FROM PensionExcedentes pe

                        UNION ALL

                        -- TOTAL DEDUCCION EXCEDENTE
                        SELECT
                            codigoempleado,
                            ''TOTAL DEDUCCION EXCEDENTE'' AS concepto,
                            total_deducciones_excedente AS monto_asignado
                        FROM DeduccionExcedentes

                        UNION ALL

                        -- NETO EXCEDENTE
                        SELECT
                            codigoempleado,
                            ''NETO EXCEDENTE'' AS concepto,
                            neto_excedente AS monto_asignado
                        FROM NetoExcedentes

                        UNION ALL

                        -- NETO TOTAL A PAGAR
                        SELECT
                            codigoempleado,
                            ''NETO TOTAL A PAGAR'' AS concepto,
                            neto_total_a_pagar AS monto_asignado
                        FROM NetoTotalAPagar
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

            //return $sql;
            $result = DB::connection('sqlsrv_dynamic')->select($sql);

            return $result;
        } catch (\Exception $e) {
            return [];
        }
    }

    private function datosAsimilados_5(Request $request)
    {
        try {
            $idNominaGapeCliente = $request->id_nomina_gape_cliente;
            $idNominaGapeEmpresa = $request->id_nomina_gape_empresa;

            $idNominaGapeEsquema = $request->id_nomina_gape_esquema;
            $idEsquemaCombinacion = $request->id_esquema;

            $idTipoPeriodo = $request->id_tipo_periodo;
            $idPeriodo = $request->periodo_inicial;

            $idEmpleadoInicial = $request->empleado_inicial;
            $idEmpleadoFinal = $request->empleado_final;

            $idNominaGapeIncidencia = $request->id_nomina_gape_incidencia ?? 0;

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
                DECLARE @idNominaGapeCliente INT;
                DECLARE @idNominaGapeEmpresa INT;

                DECLARE @idNominaGapeEsquema INT;
                DECLARE @idEsquemaCombinacion INT;

                DECLARE @idPeriodo INT;
                DECLARE @idTipoPeriodo INT;

                DECLARE @idEmpleadoInicial INT;
                DECLARE @idEmpleadoFinal INT;

				DECLARE @idNominaGapeIncidencia INT;

                SET @idNominaGapeCliente = $idNominaGapeCliente;
                SET @idNominaGapeEmpresa = $idNominaGapeEmpresa;

                SET @idNominaGapeEsquema = $idNominaGapeEsquema;
                SET @idEsquemaCombinacion = $idEsquemaCombinacion;

                SET @idPeriodo = $idPeriodo;
                SET @idTipoPeriodo = $idTipoPeriodo;

                SET @idEmpleadoInicial = $idEmpleadoInicial;
                SET @idEmpleadoFinal = $idEmpleadoFinal;

				SET @idNominaGapeIncidencia = $idNominaGapeIncidencia;

                ' + '

                ;WITH MovimientosRetro AS (
                        SELECT idempleado, idperiodo, his.idconcepto, valor, importetotal, con.descripcion, con.tipoconcepto
                        FROM Nom10007 AS his
                            INNER JOIN nom10004 con
                                ON his.idconcepto = con.idconcepto
                        WHERE
                            idperiodo = @idPeriodo
                            AND con.tipoconcepto IN (''P'', ''N'')
                            AND (con.descripcion LIKE ''%retroactivo%''
                                OR con.descripcion LIKE ''%Neto%'')
                        UNION ALL
                        SELECT idempleado, idperiodo, actual.idconcepto, valor, importetotal, con.descripcion, con.tipoconcepto
                        FROM Nom10008 AS actual
                        INNER JOIN nom10004 con
                            ON actual.idconcepto = con.idconcepto
                        WHERE
                            idperiodo = @idPeriodo
                            AND con.tipoconcepto IN (''P'', ''N'')
                            AND (con.descripcion LIKE ''%retroactivo%''
                                OR con.descripcion LIKE ''%Neto%'')
                    ), ' + '
                    tarjetaControl AS (
                        SELECT idempleado, idperiodo, valor, idtarjetaincapacidad, ti.mnemonico AS mnemonico
                        FROM nom10009 AS fi
                        INNER JOIN nom10022 ti
                            ON fi.idtipoincidencia = ti.idtipoincidencia
                        WHERE
                            ti.mnemonico IN (''INC'', ''FINJ'')
                            AND idperiodo = @idPeriodo
                        UNION ALL
                        SELECT idempleado, idperiodo, valor, idtarjetaincapacidad, ti.mnemonico AS mnemonico
                        FROM NOM10010 AS fi
                        INNER JOIN nom10022 ti
                            ON fi.idtipoincidencia = ti.idtipoincidencia
                        WHERE
                            ti.mnemonico IN (''INC'', ''FINJ'')
                            AND idperiodo = @idPeriodo
                    ), ' + '
                    TarjetaControlAgrupado AS (
                        SELECT
                            idempleado,
                            SUM(CASE WHEN mnemonico = ''INC'' THEN valor ELSE 0 END) AS incapacidad,
                            SUM(CASE WHEN mnemonico = ''FINJ'' THEN valor ELSE 0 END) AS faltas
                        FROM tarjetaControl
                        GROUP BY
                            idempleado
                    ), ' + '
                    Movimientos AS (
                        SELECT idempleado, idperiodo, his.idconcepto, valor, importetotal, con.descripcion, con.tipoconcepto, con.numeroconcepto
                        FROM Nom10007 AS his
                        INNER JOIN nom10004 con
                            ON his.idconcepto = con.idconcepto
                        WHERE
                            importetotal > 0
                            AND idperiodo = @idPeriodo
                            AND con.tipoconcepto IN (''P'',''D'')
                        UNION ALL
                        SELECT idempleado, idperiodo, actual.idconcepto, valor, importetotal, con.descripcion, con.tipoconcepto, con.numeroconcepto
                        FROM Nom10008 AS actual
                        INNER JOIN nom10004 con
                            ON actual.idconcepto = con.idconcepto
                        WHERE
                            importetotal > 0
                            AND idperiodo = @idPeriodo
                            AND con.tipoconcepto IN (''P'',''D'')
                    ), ' + '
                    MovimientosSumaQ2 AS (
                        SELECT
                            emp.codigoempleado,
                            ISNULL(
                                CASE WHEN movP.tipoconcepto = ''N'' THEN movP.importetotal ELSE 0 END
                            ,0) AS sueldo,
                            pdo.descripcion,
                            pdo.tipoconcepto AS tipoConcepto,
                            SUM(pdo.valor) AS cantidad,
                            SUM(pdo.importetotal) AS monto,
                            emp.ccampoextranumerico3 AS pension
                        FROM nom10001 emp
                        INNER JOIN nom10034 empPeriodo
                            ON emp.idempleado = empPeriodo.idempleado
                                AND empPeriodo.cidperiodo = @idPeriodo
                        INNER JOIN nom10002 AS periodo
                            ON empPeriodo.cidperiodo = periodo.idperiodo
                        LEFT JOIN Movimientos pdo
                            ON empPeriodo.cidperiodo = pdo.idperiodo
                                AND emp.idempleado = pdo.idempleado
                                AND (
                                    pdo.descripcion LIKE ''%ahorro%'' -- caja de ahorro
                                    OR pdo.descripcion LIKE ''%infonavit%''
                                    OR pdo.descripcion LIKE ''%fonacot%''
                                    OR pdo.numeroconcepto IN (20) -- prima vacacional
                                )
                        LEFT JOIN MovimientosRetro AS movP
                            ON empPeriodo.idempleado = movP.idempleado
                        LEFT JOIN TarjetaControlAgrupado AS tc
                            ON empPeriodo.idempleado = tc.idempleado
                        WHERE
                            empPeriodo.idtipoperiodo = @idTipoPeriodo
                            AND empPeriodo.estadoempleado IN (''A'', ''R'')
                            AND emp.TipoRegimen IN (''05'', ''06'', ''07'', ''08'', ''09'', ''10'', ''11'')
                            AND empPeriodo.idempleado BETWEEN @idEmpleadoInicial AND @idEmpleadoFinal
                        GROUP BY
                            emp.codigoempleado
                            , emp.ccampoextranumerico2
                            , empPeriodo.cdiaspagados
                            , pdo.descripcion
                            , pdo.tipoconcepto
                            , emp.ccampoextranumerico3
                            , periodo.diasdepago
                            , empPeriodo.fechaalta
                            , periodo.fechainicio
                            , periodo.fechafin
                            , tc.faltas
                            , tc.incapacidad
                            , movP.tipoconcepto
                            , movP.importetotal
                    ), ' + '
                    IncidenciasNormalizadasQ2 AS (
                        SELECT
                            emp.codigoempleado as codigoempleado,
                            x.descripcion AS descripcion,
                            ''P'' AS tipoConcepto,
                            x.valor AS valor,
                            emp.ccampoextranumerico2 AS pagoPorDia,
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
                            (''Bono'',                      ngid.bono),
                            (''Comisiones'',                ngid.comision),
                            (''Dia Festivo cantidad'',      ngid.cantidad_dias_festivos),
                            (''Dia Festivo monto'',         ISNULL(ngid.cantidad_dias_festivos, 0) * emp.ccampoextranumerico2 * 2),
                            (''Horas Extra Doble cantidad'', ngid.horas_extra_doble_cantidad),
                            (''Horas Extra Doble monto'',    ISNULL(ngid.horas_extra_doble_cantidad, 0) * (emp.ccampoextranumerico2 / 8.0) * 2),
                            (''Horas Extra Triple cantidad'', ngid.horas_extra_triple_cantidad),
                            (''Horas Extra Triple monto'',    ISNULL(ngid.horas_extra_triple_cantidad, 0) * (emp.ccampoextranumerico2 / 8.0) * 3),
                            (''Pago adicional'',             ngid.pago_adicional),
                            (''Premio puntualidad'',         ngid.premio_puntualidad),
                            (''Prima Dominical cantidad'',   ngid.cantidad_prima_dominical),
                            (''Prima Dominical monto'',      ISNULL(ngid.cantidad_prima_dominical, 0) * emp.ccampoextranumerico2 * 0.25)
                        ) AS x(descripcion, valor)
                        WHERE
                            (@idNominaGapeIncidencia IS NULL OR ngi.id = @idNominaGapeIncidencia)
                            AND ngi.id_nomina_gape_empresa = @idNominaGapeEmpresa
                            AND ngid.id_empleado BETWEEN @idEmpleadoInicial AND @idEmpleadoFinal
                            AND emp.TipoRegimen IN (''05'', ''06'', ''07'', ''08'', ''09'', ''10'', ''11'')
                    ), ' + '
                    PreTotalesPorEmpleadoGeneralQ2 AS (
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
                            WHERE
                                descripcion NOT LIKE ''%cantidad%''
                        ) AS x
                        GROUP BY codigoempleado, pension
                    ), ' + '
                    PensionGeneralQ2 AS (
                        SELECT
                            codigoempleado,
                            (CASE WHEN porcentajePension > 0 THEN (sueldo + total_percepciones_sin_sueldo) * (porcentajePension / 100) ELSE 0 END) pension_q2
                        FROM PreTotalesPorEmpleadoGeneralQ2
                        WHERE
                            porcentajePension > 0
                    ), ' + '
                    TotalesPorEmpleadoGeneralQ2 AS (
                        SELECT
                            codigoempleado,
                            sueldo,
                            sueldo + total_percepciones_sin_sueldo AS total_percepciones,
                            total_deducciones + (CASE WHEN porcentajePension > 0 THEN (sueldo + total_percepciones_sin_sueldo) * (porcentajePension / 100) ELSE 0 END) total_deducciones,
                            (sueldo + total_percepciones_sin_sueldo) - (total_deducciones + CASE WHEN porcentajePension > 0 THEN (sueldo + total_percepciones_sin_sueldo) * (porcentajePension / 100) ELSE 0 END) AS neto_a_pagar
                        FROM PreTotalesPorEmpleadoGeneralQ2
                    ), ' + '
                    Previsiones AS (
                        SELECT
                            ngcp.id_concepto AS idconcepto
                        FROM [becma-core2].dbo.nomina_gape_empresa_periodo_combinacion_parametrizacion AS ngepcp
                        INNER JOIN [becma-core2].dbo.nomina_gape_combinacion_prevision AS ngcp
                            ON ngepcp.id = ngcp.nomina_gape_empresa_periodo_combinacion_parametrizacion
                        WHERE
                            ngepcp.id_nomina_gape_cliente = @idNominaGapeCliente
                            AND ngepcp.id_nomina_gape_empresa = @idNominaGapeEmpresa
                            AND ngepcp.id_nomina_gape_cliente_esquema_combinacion = @idEsquemaCombinacion
                            AND (
                                    ngepcp.id_nomina_gape_tipo_periodo = @idTipoPeriodo
                                    OR ngepcp.idtipoperiodo = @idTipoPeriodo
                                )
                    ), ' + '
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
                            LEFT JOIN Movimientos pdo
                                ON empPeriodo.cidperiodo = pdo.idperiodo
                                AND emp.idempleado = pdo.idempleado
                                AND (
                                    -- (Sueldo, Vacaciones, Prima vacacional, Retroactivos)
                                        (pdo.tipoconcepto = ''P''
                                        AND (
                                            pdo.numeroconcepto IN (19, 20, 16)
                                            OR pdo.descripcion LIKE ''%asimilados%''
                                            OR EXISTS (
                                                    SELECT 1
                                                    FROM Previsiones p
                                                    WHERE p.idconcepto = pdo.idconcepto
                                                )
                                            )
                                        )
                                    OR
                                        (
                                            pdo.tipoconcepto = ''D''
                                            AND (
                                                pdo.descripcion LIKE ''%ahorro%'' -- caja de ahorro
                                                OR pdo.descripcion LIKE ''%infonavit%''
                                                OR pdo.descripcion LIKE ''%fonacot%''
                                                OR pdo.descripcion LIKE ''%alimenticia%''
                                                OR pdo.descripcion LIKE ''%sindical%''
                                                OR pdo.numeroconcepto IN (52,35,45) -- Imss, isr
                                                )
                                        )
                                    )
                            WHERE
                                empPeriodo.idtipoperiodo = @idTipoPeriodo
                                AND empPeriodo.estadoempleado IN (''A'', ''R'')
                                AND emp.TipoRegimen IN (''05'', ''06'', ''07'', ''08'', ''09'', ''10'', ''11'')
                                AND empPeriodo.idempleado BETWEEN @idEmpleadoInicial AND @idEmpleadoFinal
                            GROUP BY
                                emp.codigoempleado
                                , empPeriodo.sueldodiario
                                , empPeriodo.sueldointegrado
                                , pdo.descripcion
                                , pdo.tipoconcepto
                                , emp.ccampoextranumerico3
                    ), ' + '
                    PensionGeneralQ3 AS (
                        SELECT
                            codigoempleado,
                            SUM(CASE WHEN tipoConcepto = ''D'' AND descripcion LIKE ''%alimenticia%'' THEN monto ELSE 0 END) AS pension_q3
                        FROM MovimientosSumaQ3
                        WHERE
                            pension > 0
                        GROUP BY
                            codigoempleado
                            , pension
                    ), ' + '
                    PensionResultado AS (
                        SELECT
                            q2.codigoempleado,
                            ISNULL(q2.pension_q2, 0) - ISNULL(q3.pension_q3, 0) AS pension_restante
                        FROM PensionGeneralQ2 q2
                        LEFT JOIN PensionGeneralQ3 q3
                            ON q2.codigoempleado = q3.codigoempleado
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
                            GROUP BY
                                codigoempleado
                                , pension
                                , sdi
                                , sd
                    ), ' + '
                    preTotalesProrrateo AS (
                        SELECT
                            q2.codigoempleado,
                            (q2.neto_a_pagar - (q3.total_percepciones -  q3.total_deducciones)) + ISNULL(pr.pension_restante, 0) AS excedente
                        FROM TotalesPorEmpleadoGeneralQ2 q2
                        LEFT JOIN TotalesPorEmpleadoQ3 q3
                            ON q2.codigoempleado = q3.codigoempleado
                        LEFT JOIN PensionResultado pr
                            ON q3.codigoempleado = pr.codigoempleado
                    ), ' + '
                    ParamConfig AS (
                        SELECT
                            nge.esquema AS concepto
                            , ngce.tope
                            , ngce.orden
                        FROM [becma-core2].[dbo].nomina_gape_cliente_esquema_combinacion AS ngce
                        INNER JOIN [becma-core2].[dbo].nomina_gape_esquema AS nge
                            ON ngce.id_nomina_gape_esquema = nge.id
                        WHERE
                            ngce.id_nomina_gape_cliente = @idNominaGapeCliente
                            AND ngce.id_nomina_gape_empresa = @idNominaGapeEmpresa
                            AND ngce.combinacion = @idEsquemaCombinacion
                            AND ngce.orden > 1
                    ), ' + '
                    BaseProrrateo AS (
                        SELECT
                            p.codigoempleado,
                            CAST(p.excedente AS DECIMAL(18,2)) AS netoAPagar,
                            c.concepto,
                            c.tope,
                            c.orden,
                            ROW_NUMBER() OVER (PARTITION BY p.codigoempleado ORDER BY c.orden) AS rn
                        FROM preTotalesProrrateo p
                        CROSS JOIN ParamConfig c
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
                        GROUP BY
                            codigoempleado
                    ), ' + '
                    PensionExcedentes AS (
                        SELECT
                            q3.codigoempleado,
                            ''Pension Excedente'' AS concepto,
                                ISNULL(pr.pension_restante, 0) AS pension_excedente
                        FROM TotalesPorEmpleadoQ3 q3
                        LEFT JOIN PensionResultado pr
                            ON pr.codigoempleado = q3.codigoempleado
                    ), ' + '
                    DeduccionExcedentes AS (
                        SELECT
                            pe.codigoempleado,
                            ISNULL(SUM(pe.pension_excedente) , 0) AS total_deducciones_excedente
                        FROM TotalesPorEmpleadoQ3 q3
                        LEFT JOIN PensionExcedentes pe
                            ON pe.codigoempleado = q3.codigoempleado
                        GROUP BY
                            pe.codigoempleado
                    ), ' + '
                    NetoExcedentes AS (
                        SELECT
                            pe.codigoempleado,
                            (pe.total_percepciones_excedente - de.total_deducciones_excedente) AS neto_excedente
                        FROM PercepcionesExcedentes pe
                        LEFT JOIN DeduccionExcedentes de
                            ON pe.codigoempleado = de.codigoempleado
                    ), ' + '
                    NetoTotalAPagar AS (
                        SELECT
                            ne.codigoempleado,
                            (q3.total_percepciones - q3.total_deducciones) + (ne.neto_excedente) AS neto_total_a_pagar
                        FROM NetoExcedentes ne
                        LEFT JOIN TotalesPorEmpleadoQ3 AS q3
                            ON ne.codigoempleado = q3.codigoempleado
                    ), ' + '
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
                        WHERE
                            empPeriodo.cidperiodo = @idPeriodo
                            AND empPeriodo.estadoempleado IN (''A'', ''R'')
                            AND emp.TipoRegimen IN (''05'', ''06'', ''07'', ''08'', ''09'', ''10'', ''11'')
                            AND empPeriodo.idempleado BETWEEN @idEmpleadoInicial AND @idEmpleadoFinal
                    ), ' + '
                    nomGapeEmpresa AS (
                        SELECT
                            provisiones
                            , fee
                        FROM [becma-core2].dbo.nomina_gape_empresa_periodo_combinacion_parametrizacion
                        WHERE
                            id_nomina_gape_empresa = @idNominaGapeEmpresa
                            AND id_nomina_gape_cliente = @idNominaGapeCliente
                            AND id_nomina_gape_cliente_esquema_combinacion = @idEsquemaCombinacion
                            AND (
                                    id_nomina_gape_tipo_periodo = @idTipoPeriodo
                                    OR idtipoperiodo = @idTipoPeriodo
                                )
                    ), ' + '
                    compensacion AS (
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
						(nta.neto_total_a_pagar + q3.total_deducciones + q2.total_deducciones_excedente)
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
                        INNER JOIN TotalesPorEmpleadoQ3 AS q3
                            ON nta.codigoempleado = q3.codigoempleado
                        INNER JOIN DeduccionExcedentes AS q2
                            ON nta.codigoempleado = q2.codigoempleado
                        CROSS JOIN nomGapeEmpresa nge
                    )
                    SELECT codigoempleado, ' + @cols + '
                    FROM compensacion
                    PIVOT (
                        SUM(monto)
                        FOR concepto IN (' + @cols + ')
                    ) p
                    ORDER BY codigoempleado;
                    ';
                    EXEC(@sql);
            ";

            //return $sql;
            $result = DB::connection('sqlsrv_dynamic')->select($sql);

            return $result;
        } catch (\Exception $e) {
            return [];
        }
    }

    private function datosAsimilados_6(Request $request)
    {
        try {
            $idNominaGapeCliente = $request->id_nomina_gape_cliente;
            $idNominaGapeEmpresa = $request->id_nomina_gape_empresa;

            $idNominaGapeEsquema = $request->id_nomina_gape_esquema;
            $idEsquemaCombinacion = $request->id_esquema;

            $idTipoPeriodo = $request->id_tipo_periodo;
            $idPeriodo = $request->periodo_inicial;

            $idEmpleadoInicial = $request->empleado_inicial;
            $idEmpleadoFinal = $request->empleado_final;

            $idNominaGapeIncidencia = $request->id_nomina_gape_incidencia ?? 0;

            $sql = "
                DECLARE @cols NVARCHAR(MAX),
                        @sql  NVARCHAR(MAX);
                ;WITH
                    Obligaciones AS (
                        SELECT
                            descripcion,
                            numeroconcepto,
                            CASE numeroconcepto
                                WHEN 90 THEN 1
                                WHEN 89 THEN 2
                                WHEN 93 THEN 3
                                WHEN 96 THEN 4
                                WHEN 97 THEN 5
                                WHEN 98 THEN 6
                            END AS orden
                        FROM nom10004
                        WHERE numeroconcepto IN (90, 89, 93, 96, 97, 98)
                    ),
                    titulos AS (
                        SELECT * FROM (VALUES
                            ('Comision Servicios', 'N', 1000, 100),
                            ('Costo Total',  'N', 2000, 299)
                        ) AS X(descripcion, tipoconcepto, numeroconcepto, orden)
                    ),
                    TitulosFinales AS (
                        SELECT descripcion, orden
                        FROM titulos

                        UNION ALL

                        SELECT descripcion, orden
                        FROM Obligaciones
                    )

                    SELECT @cols = STUFF((
                        SELECT ', ' + QUOTENAME(descripcion)
                        FROM TitulosFinales
                        ORDER BY orden
                        FOR XML PATH(''), TYPE
                    ).value('.', 'NVARCHAR(MAX)'), 1, 2, '');

                SET @sql = CAST('' AS NVARCHAR(MAX)) + '
                DECLARE @idNominaGapeCliente INT;
                DECLARE @idNominaGapeEmpresa INT;

                DECLARE @idNominaGapeEsquema INT;
                DECLARE @idEsquemaCombinacion INT;

                DECLARE @idPeriodo INT;
                DECLARE @idTipoPeriodo INT;

                DECLARE @idEmpleadoInicial INT;
                DECLARE @idEmpleadoFinal INT;

				DECLARE @idNominaGapeIncidencia INT;

                SET @idNominaGapeCliente = $idNominaGapeCliente;
                SET @idNominaGapeEmpresa = $idNominaGapeEmpresa;

                SET @idNominaGapeEsquema = $idNominaGapeEsquema;
                SET @idEsquemaCombinacion = $idEsquemaCombinacion;

                SET @idPeriodo = $idPeriodo;
                SET @idTipoPeriodo = $idTipoPeriodo;

                SET @idEmpleadoInicial = $idEmpleadoInicial;
                SET @idEmpleadoFinal = $idEmpleadoFinal;

				SET @idNominaGapeIncidencia = $idNominaGapeIncidencia;

                ' + '

                ;WITH MovimientosRetro AS (
                        SELECT idempleado, idperiodo, his.idconcepto, valor, importetotal, con.descripcion, con.tipoconcepto
                        FROM Nom10007 AS his
                            INNER JOIN nom10004 con
                                ON his.idconcepto = con.idconcepto
                        WHERE
                            idperiodo = @idPeriodo
                            AND con.tipoconcepto IN (''P'', ''N'')
                            AND (con.descripcion LIKE ''%retroactivo%''
                                OR con.descripcion LIKE ''%Neto%'')
                        UNION ALL
                        SELECT idempleado, idperiodo, actual.idconcepto, valor, importetotal, con.descripcion, con.tipoconcepto
                        FROM Nom10008 AS actual
                        INNER JOIN nom10004 con
                            ON actual.idconcepto = con.idconcepto
                        WHERE
                            idperiodo = @idPeriodo
                            AND con.tipoconcepto IN (''P'', ''N'')
                            AND (con.descripcion LIKE ''%retroactivo%''
                                OR con.descripcion LIKE ''%Neto%'')
                    ), ' + '
                    tarjetaControl AS (
                        SELECT idempleado, idperiodo, valor, idtarjetaincapacidad, ti.mnemonico AS mnemonico
                        FROM nom10009 AS fi
                        INNER JOIN nom10022 ti
                            ON fi.idtipoincidencia = ti.idtipoincidencia
                        WHERE
                            ti.mnemonico IN (''INC'', ''FINJ'')
                            AND idperiodo = @idPeriodo
                        UNION ALL
                        SELECT idempleado, idperiodo, valor, idtarjetaincapacidad, ti.mnemonico AS mnemonico
                        FROM NOM10010 AS fi
                        INNER JOIN nom10022 ti
                            ON fi.idtipoincidencia = ti.idtipoincidencia
                        WHERE
                            ti.mnemonico IN (''INC'', ''FINJ'')
                            AND idperiodo = @idPeriodo
                    ), ' + '
                    TarjetaControlAgrupado AS (
                        SELECT
                            idempleado,
                            SUM(CASE WHEN mnemonico = ''INC'' THEN valor ELSE 0 END) AS incapacidad,
                            SUM(CASE WHEN mnemonico = ''FINJ'' THEN valor ELSE 0 END) AS faltas
                        FROM tarjetaControl
                        GROUP BY
                            idempleado
                    ), ' + '
                    Movimientos AS (
                        SELECT idempleado, idperiodo, his.idconcepto, valor, importetotal, con.descripcion, con.tipoconcepto, con.numeroconcepto
                        FROM Nom10007 AS his
                        INNER JOIN nom10004 con
                            ON his.idconcepto = con.idconcepto
                        WHERE
                            importetotal > 0
                            AND idperiodo = @idPeriodo
                            AND con.tipoconcepto IN (''P'',''D'', ''O'')
                        UNION ALL
                        SELECT idempleado, idperiodo, actual.idconcepto, valor, importetotal, con.descripcion, con.tipoconcepto, con.numeroconcepto
                        FROM Nom10008 AS actual
                        INNER JOIN nom10004 con
                            ON actual.idconcepto = con.idconcepto
                        WHERE
                            importetotal > 0
                            AND idperiodo = @idPeriodo
                            AND con.tipoconcepto IN (''P'',''D'', ''O'')
                    ), ' + '
                    MovimientosSumaQ2 AS (
                        SELECT
                            emp.codigoempleado,
                            ISNULL(
                                CASE WHEN movP.tipoconcepto = ''N'' THEN movP.importetotal ELSE 0 END
                            ,0) AS sueldo,
                            pdo.descripcion,
                            pdo.tipoconcepto AS tipoConcepto,
                            SUM(pdo.valor) AS cantidad,
                            SUM(pdo.importetotal) AS monto,
                            emp.ccampoextranumerico3 AS pension
                        FROM nom10001 emp
                        INNER JOIN nom10034 empPeriodo
                            ON emp.idempleado = empPeriodo.idempleado
                                AND empPeriodo.cidperiodo = @idPeriodo
                        INNER JOIN nom10002 AS periodo
                            ON empPeriodo.cidperiodo = periodo.idperiodo
                        LEFT JOIN Movimientos pdo
                            ON empPeriodo.cidperiodo = pdo.idperiodo
                                AND emp.idempleado = pdo.idempleado
                                AND (
                                    pdo.descripcion LIKE ''%ahorro%'' -- caja de ahorro
                                    OR pdo.descripcion LIKE ''%infonavit%''
                                    OR pdo.descripcion LIKE ''%fonacot%''
                                    OR pdo.numeroconcepto IN (20) -- prima vacacional
                                )
                        LEFT JOIN MovimientosRetro AS movP
                            ON empPeriodo.idempleado = movP.idempleado

                        LEFT JOIN TarjetaControlAgrupado AS tc
                            ON empPeriodo.idempleado = tc.idempleado
                        WHERE
                            empPeriodo.idtipoperiodo = @idTipoPeriodo
                            AND empPeriodo.estadoempleado IN (''A'', ''R'')
                            AND emp.TipoRegimen IN (''05'', ''06'', ''07'', ''08'', ''09'', ''10'', ''11'')
                            AND empPeriodo.idempleado BETWEEN @idEmpleadoInicial AND @idEmpleadoFinal
                        GROUP BY
                            emp.codigoempleado
                            , emp.ccampoextranumerico2
                            , empPeriodo.cdiaspagados
                            , pdo.descripcion
                            , pdo.tipoconcepto
                            , emp.ccampoextranumerico3
                            , periodo.diasdepago
                            , empPeriodo.fechaalta
                            , periodo.fechainicio
                            , periodo.fechafin
                            , tc.faltas
                            , tc.incapacidad
                            , movP.tipoconcepto
                            , movP.importetotal
                    ), ' + '
                    IncidenciasNormalizadasQ2 AS (
                        SELECT
                            emp.codigoempleado as codigoempleado,
                            x.descripcion AS descripcion,
                            ''P'' AS tipoConcepto,
                            x.valor AS valor,
                            emp.ccampoextranumerico2 AS pagoPorDia,
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
                            (''Bono'',                      ngid.bono),
                            (''Comisiones'',                ngid.comision),
                            (''Dia Festivo cantidad'',      ngid.cantidad_dias_festivos),
                            (''Dia Festivo monto'',         ISNULL(ngid.cantidad_dias_festivos, 0) * emp.ccampoextranumerico2 * 2),
                            (''Horas Extra Doble cantidad'', ngid.horas_extra_doble_cantidad),
                            (''Horas Extra Doble monto'',    ISNULL(ngid.horas_extra_doble_cantidad, 0) * (emp.ccampoextranumerico2 / 8.0) * 2),
                            (''Horas Extra Triple cantidad'', ngid.horas_extra_triple_cantidad),
                            (''Horas Extra Triple monto'',    ISNULL(ngid.horas_extra_triple_cantidad, 0) * (emp.ccampoextranumerico2 / 8.0) * 3),
                            (''Pago adicional'',             ngid.pago_adicional),
                            (''Premio puntualidad'',         ngid.premio_puntualidad),
                            (''Prima Dominical cantidad'',   ngid.cantidad_prima_dominical),
                            (''Prima Dominical monto'',      ISNULL(ngid.cantidad_prima_dominical, 0) * emp.ccampoextranumerico2 * 0.25)
                        ) AS x(descripcion, valor)
                        WHERE
                            (@idNominaGapeIncidencia IS NULL OR ngi.id = @idNominaGapeIncidencia)
                            AND ngi.id_nomina_gape_empresa = @idNominaGapeEmpresa
                            AND ngid.id_empleado BETWEEN @idEmpleadoInicial AND @idEmpleadoFinal
                            AND emp.TipoRegimen IN (''05'', ''06'', ''07'', ''08'', ''09'', ''10'', ''11'')
                    ), ' + '
                    PreTotalesPorEmpleadoGeneralQ2 AS (
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
                            WHERE descripcion NOT LIKE ''%cantidad%''
                        ) AS x
                        GROUP BY
                            codigoempleado
                            , pension
                    ), ' + '
					PensionGeneralQ2 AS (
                        SELECT
                            codigoempleado,
                            (CASE WHEN porcentajePension > 0 THEN (sueldo + total_percepciones_sin_sueldo) * (porcentajePension / 100) ELSE 0 END) pension_q2
                        FROM PreTotalesPorEmpleadoGeneralQ2
                        WHERE
                            porcentajePension > 0
                    ), ' + '
                    TotalesPorEmpleadoGeneralQ2 AS (
                        SELECT
                            codigoempleado,
                            sueldo,
                            sueldo + total_percepciones_sin_sueldo AS total_percepciones,
                            total_deducciones + (CASE WHEN porcentajePension > 0 THEN (sueldo + total_percepciones_sin_sueldo) * (porcentajePension / 100) ELSE 0 END) total_deducciones,
                            (sueldo + total_percepciones_sin_sueldo) - (total_deducciones + CASE WHEN porcentajePension > 0 THEN (sueldo + total_percepciones_sin_sueldo) * (porcentajePension / 100) ELSE 0 END) AS neto_a_pagar
                        FROM PreTotalesPorEmpleadoGeneralQ2
                    ), ' + '
                    Previsiones AS (
                        SELECT
                            ngcp.id_concepto AS idconcepto
                        FROM [becma-core2].dbo.nomina_gape_empresa_periodo_combinacion_parametrizacion AS ngepcp
                        INNER JOIN [becma-core2].dbo.nomina_gape_combinacion_prevision AS ngcp
                            ON ngepcp.id = ngcp.nomina_gape_empresa_periodo_combinacion_parametrizacion
                        WHERE
                            ngepcp.id_nomina_gape_cliente = @idNominaGapeCliente
                            AND ngepcp.id_nomina_gape_empresa = @idNominaGapeEmpresa
                            AND ngepcp.id_nomina_gape_cliente_esquema_combinacion = @idEsquemaCombinacion
                            AND (
                                    ngepcp.id_nomina_gape_tipo_periodo = @idTipoPeriodo
                                    OR ngepcp.idtipoperiodo = @idTipoPeriodo
                                )
                    ), ' + '
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
                            LEFT JOIN Movimientos pdo
                                ON empPeriodo.cidperiodo = pdo.idperiodo
                                AND emp.idempleado = pdo.idempleado
                                AND (
                                    -- (Sueldo, Vacaciones, Prima vacacional, Retroactivos)
                                        (pdo.tipoconcepto = ''P''
                                        AND (
                                            pdo.numeroconcepto IN (19, 20, 16)
                                            OR pdo.descripcion LIKE ''%asimilados%''
                                            OR EXISTS (
                                                    SELECT 1
                                                    FROM Previsiones p
                                                    WHERE p.idconcepto = pdo.idconcepto
                                                )
                                            )
                                        )
                                    OR
                                        (
                                            pdo.tipoconcepto = ''D''
                                            AND (
                                                pdo.descripcion LIKE ''%ahorro%'' -- caja de ahorro
                                                OR pdo.descripcion LIKE ''%infonavit%''
                                                OR pdo.descripcion LIKE ''%fonacot%''
                                                OR pdo.descripcion LIKE ''%alimenticia%''
                                                OR pdo.descripcion LIKE ''%sindical%''
                                                OR pdo.numeroconcepto IN (52,35,45) -- Imss, isr
                                                )
                                        )
                                    )
                            WHERE
                                empPeriodo.idtipoperiodo = @idTipoPeriodo
                                AND empPeriodo.estadoempleado IN (''A'', ''R'')
                                AND emp.TipoRegimen IN (''05'', ''06'', ''07'', ''08'', ''09'', ''10'', ''11'')
                                AND empPeriodo.idempleado BETWEEN @idEmpleadoInicial AND @idEmpleadoFinal
                            GROUP BY
                                emp.codigoempleado
                                , empPeriodo.sueldodiario
                                , empPeriodo.sueldointegrado
                                , pdo.descripcion
                                , pdo.tipoconcepto
                                , emp.ccampoextranumerico3
                    ), ' + '
					PensionGeneralQ3 AS (
                        SELECT
                            codigoempleado,
                            SUM(CASE WHEN tipoConcepto = ''D'' AND descripcion LIKE ''%alimenticia%'' THEN monto ELSE 0 END) AS pension_q3
                        FROM MovimientosSumaQ3
                        WHERE
                            pension > 0
                        GROUP BY
                            codigoempleado
                            , pension
                    ), ' + '
					PensionResultado AS (
                        SELECT
                            q2.codigoempleado,
                            ISNULL(q2.pension_q2, 0) - ISNULL(q3.pension_q3, 0) AS pension_restante
                        FROM PensionGeneralQ2 q2
                        LEFT JOIN PensionGeneralQ3 q3
                            ON q2.codigoempleado = q3.codigoempleado
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
                            GROUP BY
                                codigoempleado
                                , pension
                                , sdi
                                , sd
                    ), ' + '
                    preTotalesProrrateo AS (
                        SELECT
                            q2.codigoempleado,
                            (q2.neto_a_pagar - (q3.total_percepciones -  q3.total_deducciones)) + ISNULL(pr.pension_restante, 0) AS excedente
                        FROM TotalesPorEmpleadoGeneralQ2 q2
                        LEFT JOIN TotalesPorEmpleadoQ3 q3
                            ON q2.codigoempleado = q3.codigoempleado
                        LEFT JOIN PensionResultado pr
                            ON q3.codigoempleado = pr.codigoempleado
                    ), ' + '
                    ParamConfig AS (
                        SELECT
                            nge.esquema AS concepto
                            , ngce.tope
                            , ngce.orden
                        FROM [becma-core2].[dbo].nomina_gape_cliente_esquema_combinacion AS ngce
                        INNER JOIN [becma-core2].[dbo].nomina_gape_esquema AS nge
                            ON ngce.id_nomina_gape_esquema = nge.id
                        WHERE
                            ngce.id_nomina_gape_cliente = @idNominaGapeCliente
                            AND ngce.id_nomina_gape_empresa = @idNominaGapeEmpresa
                            AND ngce.combinacion = @idEsquemaCombinacion
                            AND ngce.orden > 1
                    ), ' + '
                    BaseProrrateo AS (
                        SELECT
                            p.codigoempleado,
                            CAST(p.excedente AS DECIMAL(18,2)) AS netoAPagar,
                            c.concepto,
                            c.tope,
                            c.orden,
                            ROW_NUMBER() OVER (PARTITION BY p.codigoempleado ORDER BY c.orden) AS rn
                        FROM preTotalesProrrateo p
                        CROSS JOIN ParamConfig c
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

                        UNION ALL ' + '

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
                        GROUP BY
                            codigoempleado
                    ), ' + '
                    PensionExcedentes AS (
                        SELECT
                            q3.codigoempleado,
                            ''Pension Excedente'' AS concepto,
                                ISNULL(pr.pension_restante, 0) AS pension_excedente
                        FROM TotalesPorEmpleadoQ3 q3
                        LEFT JOIN PensionResultado pr
                            ON pr.codigoempleado = q3.codigoempleado
                    ), ' + '
                    DeduccionExcedentes AS (
                        SELECT
                            pe.codigoempleado,
                            ISNULL(SUM(pe.pension_excedente) , 0) AS total_deducciones_excedente
                        FROM TotalesPorEmpleadoQ3 q3
                        LEFT JOIN PensionExcedentes pe
                            ON pe.codigoempleado = q3.codigoempleado
                        GROUP BY
                            pe.codigoempleado
                    ), ' + '
                    NetoExcedentes AS (
                        SELECT
                            pe.codigoempleado,
                            (pe.total_percepciones_excedente - de.total_deducciones_excedente) AS neto_excedente
                        FROM PercepcionesExcedentes pe
                        LEFT JOIN DeduccionExcedentes de
                            ON pe.codigoempleado = de.codigoempleado
                    ), ' + '
                    NetoTotalAPagar AS (
                        SELECT
                            ne.codigoempleado,
                            (q3.total_percepciones - q3.total_deducciones) + (ne.neto_excedente) AS neto_total_a_pagar
                        FROM NetoExcedentes ne
                        LEFT JOIN TotalesPorEmpleadoQ3 AS q3
                            ON ne.codigoempleado = q3.codigoempleado
                    ), ' + '
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
                        WHERE
                            empPeriodo.cidperiodo = @idPeriodo
                            AND empPeriodo.estadoempleado IN (''A'', ''R'')
                            AND emp.TipoRegimen IN (''05'', ''06'', ''07'', ''08'', ''09'', ''10'', ''11'')
                            AND empPeriodo.idempleado BETWEEN @idEmpleadoInicial AND @idEmpleadoFinal
                    ), ' + '
                    nomGapeEmpresa AS (
                        SELECT
                            provisiones
                            , fee
                        FROM [becma-core2].dbo.nomina_gape_empresa_periodo_combinacion_parametrizacion
                        WHERE
                            id_nomina_gape_empresa = @idNominaGapeEmpresa
                            AND id_nomina_gape_cliente = @idNominaGapeCliente
                            AND id_nomina_gape_cliente_esquema_combinacion = @idEsquemaCombinacion
                            AND (
                                    id_nomina_gape_tipo_periodo = @idTipoPeriodo
                                    OR idtipoperiodo = @idTipoPeriodo
                                )
                    ), ' + '
                    totalCompensacion AS (
                        SELECT
                            nta.codigoempleado,
                            nge.fee AS  fee,
                            (nta.neto_total_a_pagar + q3.total_deducciones + q2.total_deducciones_excedente)
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
                            INNER JOIN TotalesPorEmpleadoQ3 AS q3
                                ON nta.codigoempleado = q3.codigoempleado
                            INNER JOIN DeduccionExcedentes AS q2
                                ON nta.codigoempleado = q2.codigoempleado
                            CROSS JOIN nomGapeEmpresa nge
                    ), ' + '
                    MovimientosObligaciones AS (
						SELECT
							emp.codigoempleado AS codigoempleado,
                            pdo.descripcion AS concepto,
							''O'' AS tipoConcepto,
							pdo.importetotal AS monto
						FROM nom10001 emp
						INNER JOIN nom10034 empPeriodo
							ON emp.idempleado = empPeriodo.idempleado
							    AND empPeriodo.cidperiodo = @idPeriodo
						LEFT JOIN Movimientos pdo
							ON empPeriodo.cidperiodo = pdo.idperiodo
                                AND emp.idempleado = pdo.idempleado
                                AND pdo.tipoconcepto = ''O''
                                AND pdo.numeroconcepto IN (90, 89, 93, 96, 97, 98)
						WHERE
                            empPeriodo.idtipoperiodo = @idTipoPeriodo
							AND empPeriodo.idempleado BETWEEN @idEmpleadoInicial AND @idEmpleadoFinal
                            AND emp.TipoRegimen IN (''05'', ''06'', ''07'', ''08'', ''09'', ''10'', ''11'')
					), ' + '
                    TotalesPorEmpleadoObligacion AS (
                        SELECT
                            codigoempleado AS codigoempleado,
                            SUM(monto) AS monto
                        FROM MovimientosObligaciones
                        GROUP BY
                            codigoempleado
                    ), ' + '
                    Comision AS (
                        SELECT
                            t.codigoempleado AS codigoempleado,
                            (com.monto_asignado + t.monto) * (com.fee / 100) AS total_comision
                        FROM TotalesPorEmpleadoObligacion AS t
                        INNER JOIN totalCompensacion com
                            ON t.codigoempleado = com.codigoempleado
                    ), ' + '
                    CostoTotal AS (
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
                    ) ' + '
                    SELECT codigoempleado, ' + @cols + '
                    FROM CostoTotal
                    PIVOT (
                        SUM(monto)
                        FOR concepto IN (' + @cols + ')
                    ) p
                    ORDER BY codigoempleado;
                    ';

                    EXEC(@sql);
            ";

            //return $sql;
            $result = DB::connection('sqlsrv_dynamic')->select($sql);

            return $result;
        } catch (\Exception $e) {
            return [];
        }
    }

    private function totalAsimilados_01(Request $request)
    {
        try {
            $idNominaGapeCliente = $request->id_nomina_gape_cliente;
            $idNominaGapeEmpresa = $request->id_nomina_gape_empresa;

            $idNominaGapeEsquema = $request->id_nomina_gape_esquema;
            $idEsquemaCombinacion = $request->id_esquema;

            $idTipoPeriodo = $request->id_tipo_periodo;
            $idPeriodo = $request->periodo_inicial;

            $idEmpleadoInicial = $request->empleado_inicial;
            $idEmpleadoFinal = $request->empleado_final;

            $idNominaGapeIncidencia = $request->id_nomina_gape_incidencia ?? 0;

            $sql = "
                DECLARE @sql  NVARCHAR(MAX);


                SET @sql = CAST('' AS NVARCHAR(MAX)) + '
                DECLARE @idNominaGapeCliente INT;
                DECLARE @idNominaGapeEmpresa INT;

                DECLARE @idNominaGapeEsquema INT;
                DECLARE @idEsquemaCombinacion INT;

                DECLARE @idPeriodo INT;
                DECLARE @idTipoPeriodo INT;

                DECLARE @idEmpleadoInicial INT;
                DECLARE @idEmpleadoFinal INT;

				DECLARE @idNominaGapeIncidencia INT;

                SET @idNominaGapeCliente = $idNominaGapeCliente;
                SET @idNominaGapeEmpresa = $idNominaGapeEmpresa;

                SET @idNominaGapeEsquema = $idNominaGapeEsquema;
                SET @idEsquemaCombinacion = $idEsquemaCombinacion;

                SET @idPeriodo = $idPeriodo;
                SET @idTipoPeriodo = $idTipoPeriodo;

                SET @idEmpleadoInicial = $idEmpleadoInicial;
                SET @idEmpleadoFinal = $idEmpleadoFinal;

				SET @idNominaGapeIncidencia = $idNominaGapeIncidencia;

                ' + '
                ;WITH MovimientosRetro AS (
                    SELECT idempleado, idperiodo, his.idconcepto, valor, importetotal, con.descripcion, con.tipoconcepto
                    FROM Nom10007 AS his
                        INNER JOIN nom10004 con
                            ON his.idconcepto = con.idconcepto
                    WHERE
                        idperiodo = @idPeriodo
                        AND con.tipoconcepto IN (''P'', ''N'')
                        AND (con.descripcion LIKE ''%retroactivo%''
                            OR con.descripcion LIKE ''%Neto%'')
                    UNION ALL
                    SELECT idempleado, idperiodo, actual.idconcepto, valor, importetotal, con.descripcion, con.tipoconcepto
                    FROM Nom10008 AS actual
                    INNER JOIN nom10004 con
                        ON actual.idconcepto = con.idconcepto
                    WHERE
                        idperiodo = @idPeriodo
                        AND con.tipoconcepto IN (''P'', ''N'')
                        AND (con.descripcion LIKE ''%retroactivo%''
                            OR con.descripcion LIKE ''%Neto%'')
                    ), ' + '
                    tarjetaControl AS (
                        SELECT idempleado, idperiodo, valor, idtarjetaincapacidad, ti.mnemonico AS mnemonico
                        FROM nom10009 AS fi
                        INNER JOIN nom10022 ti
                            ON fi.idtipoincidencia = ti.idtipoincidencia
                        WHERE
                            ti.mnemonico IN (''INC'', ''FINJ'')
                            AND idperiodo = @idPeriodo
                        UNION ALL
                        SELECT idempleado, idperiodo, valor, idtarjetaincapacidad, ti.mnemonico AS mnemonico
                        FROM NOM10010 AS fi
                        INNER JOIN nom10022 ti
                            ON fi.idtipoincidencia = ti.idtipoincidencia
                        WHERE
                            ti.mnemonico IN (''INC'', ''FINJ'')
                            AND idperiodo = @idPeriodo
                    ), ' + '
                    TarjetaControlAgrupado AS (
                        SELECT
                            idempleado,
                            SUM(CASE WHEN mnemonico = ''INC'' THEN valor ELSE 0 END) AS incapacidad,
                            SUM(CASE WHEN mnemonico = ''FINJ'' THEN valor ELSE 0 END) AS faltas
                        FROM tarjetaControl
                        GROUP BY
                            idempleado
                    ), ' + '
                    Movimientos AS (
                        SELECT idempleado, idperiodo, his.idconcepto, valor, importetotal, con.descripcion, con.tipoconcepto, con.numeroconcepto
                        FROM Nom10007 AS his
                        INNER JOIN nom10004 con
                            ON his.idconcepto = con.idconcepto
                        WHERE
                            importetotal > 0
                            AND idperiodo = @idPeriodo
                            AND con.tipoconcepto IN (''P'',''D'')
                        UNION ALL
                        SELECT idempleado, idperiodo, actual.idconcepto, valor, importetotal, con.descripcion, con.tipoconcepto, con.numeroconcepto
                        FROM Nom10008 AS actual
                        INNER JOIN nom10004 con
                            ON actual.idconcepto = con.idconcepto
                        WHERE
                            importetotal > 0
                            AND idperiodo = @idPeriodo
                            AND con.tipoconcepto IN (''P'',''D'')
                    ), ' + '
                    MovimientosSumaQ2 AS (
                        SELECT
                            emp.codigoempleado,
                            ISNULL(
                                CASE WHEN movP.tipoconcepto = ''N'' THEN movP.importetotal ELSE 0 END
                            ,0) AS sueldo,
                            pdo.descripcion,
                            pdo.tipoconcepto AS tipoConcepto,
                            SUM(pdo.valor) AS cantidad,
                            SUM(pdo.importetotal) AS monto,
                            emp.ccampoextranumerico3 AS pension
                        FROM nom10001 emp
                        INNER JOIN nom10034 empPeriodo
                            ON emp.idempleado = empPeriodo.idempleado
                                AND empPeriodo.cidperiodo = @idPeriodo
                        INNER JOIN nom10002 AS periodo
                            ON empPeriodo.cidperiodo = periodo.idperiodo
                        LEFT JOIN Movimientos pdo
                            ON empPeriodo.cidperiodo = pdo.idperiodo
                                AND emp.idempleado = pdo.idempleado
                                AND (
                                    pdo.descripcion LIKE ''%ahorro%'' -- caja de ahorro
                                    OR pdo.descripcion LIKE ''%infonavit%''
                                    OR pdo.descripcion LIKE ''%fonacot%''
                                    OR pdo.numeroconcepto IN (20) -- prima vacacional
                                )
                        LEFT JOIN MovimientosRetro AS movP
                            ON empPeriodo.idempleado = movP.idempleado
                        LEFT JOIN TarjetaControlAgrupado AS tc
                            ON empPeriodo.idempleado = tc.idempleado
                        WHERE
                            empPeriodo.idtipoperiodo = @idTipoPeriodo
                            AND empPeriodo.estadoempleado IN (''A'', ''R'')
                            AND emp.TipoRegimen IN (''05'', ''06'', ''07'', ''08'', ''09'', ''10'', ''11'')
                            AND empPeriodo.idempleado BETWEEN @idEmpleadoInicial AND @idEmpleadoFinal
                        GROUP BY
                            emp.codigoempleado
                            , emp.ccampoextranumerico2
                            , empPeriodo.cdiaspagados
                            , pdo.descripcion
                            , pdo.tipoconcepto
                            , emp.ccampoextranumerico3
                            , periodo.diasdepago
                            , empPeriodo.fechaalta
                            , periodo.fechainicio
                            , periodo.fechafin
                            , tc.faltas
                            , tc.incapacidad
                            , movP.tipoconcepto
                            , movP.importetotal
                    ), ' + '
                    IncidenciasNormalizadasQ2 AS (
                        SELECT
                            emp.codigoempleado as codigoempleado,
                            x.descripcion AS descripcion,
                            ''P'' AS tipoConcepto,
                            x.valor AS valor,
                            emp.ccampoextranumerico2 AS pagoPorDia,
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
                            (''Bono'',                      ngid.bono),
                            (''Comisiones'',                ngid.comision),
                            (''Dia Festivo cantidad'',      ngid.cantidad_dias_festivos),
                            (''Dia Festivo monto'',         ISNULL(ngid.cantidad_dias_festivos, 0) * emp.ccampoextranumerico2 * 2),
                            (''Horas Extra Doble cantidad'', ngid.horas_extra_doble_cantidad),
                            (''Horas Extra Doble monto'',    ISNULL(ngid.horas_extra_doble_cantidad, 0) * (emp.ccampoextranumerico2 / 8.0) * 2),
                            (''Horas Extra Triple cantidad'', ngid.horas_extra_triple_cantidad),
                            (''Horas Extra Triple monto'',    ISNULL(ngid.horas_extra_triple_cantidad, 0) * (emp.ccampoextranumerico2 / 8.0) * 3),
                            (''Pago adicional'',             ngid.pago_adicional),
                            (''Premio puntualidad'',         ngid.premio_puntualidad),
                            (''Prima Dominical cantidad'',   ngid.cantidad_prima_dominical),
                            (''Prima Dominical monto'',      ISNULL(ngid.cantidad_prima_dominical, 0) * emp.ccampoextranumerico2 * 0.25)
                        ) AS x(descripcion, valor)
                        WHERE
                            (@idNominaGapeIncidencia IS NULL OR ngi.id = @idNominaGapeIncidencia)
                            AND ngi.id_nomina_gape_empresa = @idNominaGapeEmpresa
                            AND ngid.id_empleado BETWEEN @idEmpleadoInicial AND @idEmpleadoFinal
                            AND emp.TipoRegimen IN (''05'', ''06'', ''07'', ''08'', ''09'', ''10'', ''11'')
                    ), ' + '
                    PreTotalesPorEmpleadoGeneralQ2 AS (
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
                            WHERE
                                descripcion NOT LIKE ''%cantidad%''
                        ) AS x
                        GROUP BY
                            codigoempleado
                            , pension
                    ), ' + '
                    TotalesPorEmpleadoGeneralQ2 AS (
                        SELECT
                            codigoempleado,
                            sueldo,
                            sueldo + total_percepciones_sin_sueldo AS total_percepciones,
                            total_deducciones + (CASE WHEN porcentajePension > 0 THEN (sueldo + total_percepciones_sin_sueldo) * (porcentajePension / 100) ELSE 0 END) total_deducciones,
                            (sueldo + total_percepciones_sin_sueldo) - (total_deducciones + CASE WHEN porcentajePension > 0 THEN (sueldo + total_percepciones_sin_sueldo) * (porcentajePension / 100) ELSE 0 END) AS neto_a_pagar
                        FROM PreTotalesPorEmpleadoGeneralQ2
                    ), ' + '
                    parametrizacion AS (
                        SELECT
                            provisiones
                            , fee
                        FROM [becma-core2].dbo.nomina_gape_empresa_periodo_combinacion_parametrizacion
                        WHERE
                            id_nomina_gape_empresa = @idNominaGapeEmpresa
                            AND id_nomina_gape_cliente = @idNominaGapeCliente
                            AND id_nomina_gape_cliente_esquema_combinacion = @idEsquemaCombinacion
                            AND (
                                    id_nomina_gape_tipo_periodo = @idTipoPeriodo
                                    OR idtipoperiodo = @idTipoPeriodo
                                )
                    ), ' + '
                    totales AS (
                        SELECT
                            SUM(total_percepciones) AS percepcion_bruta
                        FROM TotalesPorEmpleadoGeneralQ2
                    ) ' + '
                    SELECT
                        t.percepcion_bruta,
                        0 AS costo_social,
                        t.percepcion_bruta AS base,
                        p.fee / 100.0 AS fee_porcentaje,
                        t.percepcion_bruta * (p.fee / 100.0) AS fee,
                        t.percepcion_bruta * (1 + p.fee / 100.0) AS subtotal,
                        t.percepcion_bruta * (1 + p.fee / 100.0) * 0.16 AS iva,
                        t.percepcion_bruta * (1 + p.fee / 100.0) * 1.16 AS total
                    FROM totales t
                    CROSS JOIN parametrizacion p;
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

    private function datosTotalesAsimilados_7_01(Request $request)
    {
        try {
            $idNominaGapeCliente = $request->id_nomina_gape_cliente;
            $idNominaGapeEmpresa = $request->id_nomina_gape_empresa;

            $idNominaGapeEsquema = $request->id_nomina_gape_esquema;
            $idEsquemaCombinacion = $request->id_esquema;

            $idTipoPeriodo = $request->id_tipo_periodo;
            $idPeriodo = $request->periodo_inicial;

            $idEmpleadoInicial = $request->empleado_inicial;
            $idEmpleadoFinal = $request->empleado_final;

            $idNominaGapeIncidencia = $request->id_nomina_gape_incidencia ?? 0;

            $sql = "
                DECLARE @sql  NVARCHAR(MAX);


                SET @sql = CAST('' AS NVARCHAR(MAX)) + '
                DECLARE @idNominaGapeCliente INT;
                DECLARE @idNominaGapeEmpresa INT;

                DECLARE @idNominaGapeEsquema INT;
                DECLARE @idEsquemaCombinacion INT;

                DECLARE @idPeriodo INT;
                DECLARE @idTipoPeriodo INT;

                DECLARE @idEmpleadoInicial INT;
                DECLARE @idEmpleadoFinal INT;

				DECLARE @idNominaGapeIncidencia INT;

                SET @idNominaGapeCliente = $idNominaGapeCliente;
                SET @idNominaGapeEmpresa = $idNominaGapeEmpresa;

                SET @idNominaGapeEsquema = $idNominaGapeEsquema;
                SET @idEsquemaCombinacion = $idEsquemaCombinacion;

                SET @idPeriodo = $idPeriodo;
                SET @idTipoPeriodo = $idTipoPeriodo;

                SET @idEmpleadoInicial = $idEmpleadoInicial;
                SET @idEmpleadoFinal = $idEmpleadoFinal;

				SET @idNominaGapeIncidencia = $idNominaGapeIncidencia;

                ' + '
                ;WITH MovimientosRetro AS (
                        SELECT idempleado, idperiodo, his.idconcepto, valor, importetotal, con.descripcion, con.tipoconcepto
                        FROM Nom10007 AS his
                            INNER JOIN nom10004 con
                                ON his.idconcepto = con.idconcepto
                        WHERE
                            idperiodo = @idPeriodo
                            AND con.tipoconcepto IN (''P'', ''N'')
                            AND (con.descripcion LIKE ''%retroactivo%''
                                OR con.descripcion LIKE ''%Neto%'')
                        UNION ALL
                        SELECT idempleado, idperiodo, actual.idconcepto, valor, importetotal, con.descripcion, con.tipoconcepto
                        FROM Nom10008 AS actual
                        INNER JOIN nom10004 con
                            ON actual.idconcepto = con.idconcepto
                        WHERE
                            idperiodo = @idPeriodo
                            AND con.tipoconcepto IN (''P'', ''N'')
                            AND (con.descripcion LIKE ''%retroactivo%''
                                OR con.descripcion LIKE ''%Neto%'')
                    ), ' + '
                    tarjetaControl AS (
                        SELECT idempleado, idperiodo, valor, idtarjetaincapacidad, ti.mnemonico AS mnemonico
                        FROM nom10009 AS fi
                        INNER JOIN nom10022 ti
                            ON fi.idtipoincidencia = ti.idtipoincidencia
                        WHERE
                            ti.mnemonico IN (''INC'', ''FINJ'')
                            AND idperiodo = @idPeriodo
                        UNION ALL
                        SELECT idempleado, idperiodo, valor, idtarjetaincapacidad, ti.mnemonico AS mnemonico
                        FROM NOM10010 AS fi
                        INNER JOIN nom10022 ti
                            ON fi.idtipoincidencia = ti.idtipoincidencia
                        WHERE
                            ti.mnemonico IN (''INC'', ''FINJ'')
                            AND idperiodo = @idPeriodo
                    ), ' + '
                    TarjetaControlAgrupado AS (
                        SELECT
                            idempleado,
                            SUM(CASE WHEN mnemonico = ''INC'' THEN valor ELSE 0 END) AS incapacidad,
                            SUM(CASE WHEN mnemonico = ''FINJ'' THEN valor ELSE 0 END) AS faltas
                        FROM tarjetaControl
                        GROUP BY
                            idempleado
                    ), ' + '
                    Movimientos AS (
                        SELECT idempleado, idperiodo, his.idconcepto, valor, importetotal, con.descripcion, con.tipoconcepto, con.numeroconcepto
                        FROM Nom10007 AS his
                        INNER JOIN nom10004 con
                            ON his.idconcepto = con.idconcepto
                        WHERE
                            importetotal > 0
                            AND idperiodo = @idPeriodo
                            AND con.tipoconcepto IN (''P'',''D'')
                        UNION ALL
                        SELECT idempleado, idperiodo, actual.idconcepto, valor, importetotal, con.descripcion, con.tipoconcepto, con.numeroconcepto
                        FROM Nom10008 AS actual
                        INNER JOIN nom10004 con
                            ON actual.idconcepto = con.idconcepto
                        WHERE
                            importetotal > 0
                            AND idperiodo = @idPeriodo
                            AND con.tipoconcepto IN (''P'',''D'')
                    ), ' + '
                    MovimientosSumaQ2 AS (
                        SELECT
                            emp.codigoempleado,
                            ISNULL(
                                CASE WHEN movP.tipoconcepto = ''N'' THEN movP.importetotal ELSE 0 END
                            ,0) AS sueldo,
                            pdo.descripcion,
                            pdo.tipoconcepto AS tipoConcepto,
                            SUM(pdo.valor) AS cantidad,
                            SUM(pdo.importetotal) AS monto,
                            emp.ccampoextranumerico3 AS pension
                        FROM nom10001 emp
                        INNER JOIN nom10034 empPeriodo
                            ON emp.idempleado = empPeriodo.idempleado
                                AND empPeriodo.cidperiodo = @idPeriodo
                        INNER JOIN nom10002 AS periodo
                            ON empPeriodo.cidperiodo = periodo.idperiodo
                        LEFT JOIN Movimientos pdo
                            ON empPeriodo.cidperiodo = pdo.idperiodo
                                AND emp.idempleado = pdo.idempleado
                                AND (
                                    pdo.descripcion LIKE ''%ahorro%'' -- caja de ahorro
                                    OR pdo.descripcion LIKE ''%infonavit%''
                                    OR pdo.descripcion LIKE ''%fonacot%''
                                    OR pdo.numeroconcepto IN (20) -- prima vacacional
                                )
                        LEFT JOIN MovimientosRetro AS movP
                            ON empPeriodo.idempleado = movP.idempleado
                        LEFT JOIN TarjetaControlAgrupado AS tc
                            ON empPeriodo.idempleado = tc.idempleado
                        WHERE
                            empPeriodo.idtipoperiodo = @idTipoPeriodo
                            AND empPeriodo.estadoempleado IN (''A'', ''R'')
                            AND emp.TipoRegimen IN (''05'', ''06'', ''07'', ''08'', ''09'', ''10'', ''11'')
                            AND empPeriodo.idempleado BETWEEN @idEmpleadoInicial AND @idEmpleadoFinal
                        GROUP BY
                            emp.codigoempleado
                            , emp.ccampoextranumerico2
                            , empPeriodo.cdiaspagados
                            , pdo.descripcion
                            , pdo.tipoconcepto
                            , emp.ccampoextranumerico3
                            , periodo.diasdepago
                            , empPeriodo.fechaalta
                            , periodo.fechainicio
                            , periodo.fechafin
                            , tc.faltas
                            , tc.incapacidad
                            , movP.tipoconcepto
                            , movP.importetotal
                    ), ' + '
                    IncidenciasNormalizadasQ2 AS (
                        SELECT
                            emp.codigoempleado as codigoempleado,
                            x.descripcion AS descripcion,
                            ''P'' AS tipoConcepto,
                            x.valor AS valor,
                            emp.ccampoextranumerico2 AS pagoPorDia,
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
                            (''Bono'',                      ngid.bono),
                            (''Comisiones'',                ngid.comision),
                            (''Dia Festivo cantidad'',      ngid.cantidad_dias_festivos),
                            (''Dia Festivo monto'',         ISNULL(ngid.cantidad_dias_festivos, 0) * emp.ccampoextranumerico2 * 2),
                            (''Horas Extra Doble cantidad'', ngid.horas_extra_doble_cantidad),
                            (''Horas Extra Doble monto'',    ISNULL(ngid.horas_extra_doble_cantidad, 0) * (emp.ccampoextranumerico2 / 8.0) * 2),
                            (''Horas Extra Triple cantidad'', ngid.horas_extra_triple_cantidad),
                            (''Horas Extra Triple monto'',    ISNULL(ngid.horas_extra_triple_cantidad, 0) * (emp.ccampoextranumerico2 / 8.0) * 3),
                            (''Pago adicional'',             ngid.pago_adicional),
                            (''Premio puntualidad'',         ngid.premio_puntualidad),
                            (''Prima Dominical cantidad'',   ngid.cantidad_prima_dominical),
                            (''Prima Dominical monto'',      ISNULL(ngid.cantidad_prima_dominical, 0) * emp.ccampoextranumerico2 * 0.25)
                        ) AS x(descripcion, valor)
                        WHERE
                            (@idNominaGapeIncidencia IS NULL OR ngi.id = @idNominaGapeIncidencia)
                            AND ngi.id_nomina_gape_empresa = @idNominaGapeEmpresa
                            AND ngid.id_empleado BETWEEN @idEmpleadoInicial AND @idEmpleadoFinal
                            AND emp.TipoRegimen IN (''05'', ''06'', ''07'', ''08'', ''09'', ''10'', ''11'')
                    ), ' + '
                    PreTotalesPorEmpleadoGeneralQ2 AS (
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
                            WHERE
                                descripcion NOT LIKE ''%cantidad%''
                        ) AS x
                        GROUP BY
                            codigoempleado
                            , pension
                    ), ' + '
					PensionGeneralQ2 AS (
                        SELECT
                            codigoempleado,
                            (CASE WHEN porcentajePension > 0 THEN (sueldo + total_percepciones_sin_sueldo) * (porcentajePension / 100) ELSE 0 END) pension_q2
                        FROM PreTotalesPorEmpleadoGeneralQ2
                        WHERE
                            porcentajePension > 0
                    ), ' + '
                    TotalesPorEmpleadoGeneralQ2 AS (
                        SELECT
                            codigoempleado,
                            sueldo,
                            sueldo + total_percepciones_sin_sueldo AS total_percepciones,
                            total_deducciones + (CASE WHEN porcentajePension > 0 THEN (sueldo + total_percepciones_sin_sueldo) * (porcentajePension / 100) ELSE 0 END) total_deducciones,
                            (sueldo + total_percepciones_sin_sueldo) - (total_deducciones + CASE WHEN porcentajePension > 0 THEN (sueldo + total_percepciones_sin_sueldo) * (porcentajePension / 100) ELSE 0 END) AS neto_a_pagar
                        FROM PreTotalesPorEmpleadoGeneralQ2
                    ), ' + '
                    Previsiones AS (
                        SELECT
                            ngcp.id_concepto AS idconcepto
                        FROM [becma-core2].dbo.nomina_gape_empresa_periodo_combinacion_parametrizacion AS ngepcp
                        INNER JOIN [becma-core2].dbo.nomina_gape_combinacion_prevision AS ngcp
                            ON ngepcp.id = ngcp.nomina_gape_empresa_periodo_combinacion_parametrizacion
                        WHERE
                            ngepcp.id_nomina_gape_cliente = @idNominaGapeCliente
                            AND ngepcp.id_nomina_gape_empresa = @idNominaGapeEmpresa
                            AND ngepcp.id_nomina_gape_cliente_esquema_combinacion = @idEsquemaCombinacion
                            AND (
                                    ngepcp.id_nomina_gape_tipo_periodo = @idTipoPeriodo
                                    OR ngepcp.idtipoperiodo = @idTipoPeriodo
                                )
                    ), ' + '
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
                            LEFT JOIN Movimientos pdo
                                ON empPeriodo.cidperiodo = pdo.idperiodo
                                AND emp.idempleado = pdo.idempleado
                                AND (
                                    -- (Sueldo, Vacaciones, Prima vacacional, Retroactivos)
                                        (pdo.tipoconcepto = ''P''
                                        AND (
                                            pdo.numeroconcepto IN (19, 20, 16)
                                            OR pdo.descripcion LIKE ''%asimilados%''
                                            OR EXISTS (
                                                    SELECT 1
                                                    FROM Previsiones p
                                                    WHERE p.idconcepto = pdo.idconcepto
                                                )
                                            )
                                        )
                                    OR
                                        (
                                            pdo.tipoconcepto = ''D''
                                            AND (
                                                pdo.descripcion LIKE ''%ahorro%'' -- caja de ahorro
                                                OR pdo.descripcion LIKE ''%infonavit%''
                                                OR pdo.descripcion LIKE ''%fonacot%''
                                                OR pdo.descripcion LIKE ''%alimenticia%''
                                                OR pdo.descripcion LIKE ''%sindical%''
                                                OR pdo.numeroconcepto IN (52,35,45) -- Imss, isr
                                                )
                                        )
                                    )
                            WHERE
                                empPeriodo.idtipoperiodo = @idTipoPeriodo
                                AND empPeriodo.estadoempleado IN (''A'', ''R'')
                                AND emp.TipoRegimen IN (''05'', ''06'', ''07'', ''08'', ''09'', ''10'', ''11'')
                                AND empPeriodo.idempleado BETWEEN @idEmpleadoInicial AND @idEmpleadoFinal
                            GROUP BY
                                emp.codigoempleado
                                , empPeriodo.sueldodiario
                                , empPeriodo.sueldointegrado
                                , pdo.descripcion
                                , pdo.tipoconcepto
                                , emp.ccampoextranumerico3
                    ), ' + '
					PensionGeneralQ3 AS (
                        SELECT
                            codigoempleado,
                            SUM(CASE WHEN tipoConcepto = ''D'' AND descripcion LIKE ''%alimenticia%'' THEN monto ELSE 0 END) AS pension_q3
                        FROM MovimientosSumaQ3
                        WHERE
                            pension > 0
                        GROUP BY
                            codigoempleado
                            , pension
                    ), ' + '
                    PensionResultado AS (
                        SELECT
                            q2.codigoempleado,
                            ISNULL(q2.pension_q2, 0) - ISNULL(q3.pension_q3, 0) AS pension_restante
                        FROM PensionGeneralQ2 q2
                        LEFT JOIN PensionGeneralQ3 q3
                            ON q2.codigoempleado = q3.codigoempleado
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
                            GROUP BY
                                codigoempleado
                                , pension
                                , sdi
                                , sd
                    ), ' + '
                    preTotalesProrrateo AS (
                        SELECT
                            q2.codigoempleado,
                            (q2.neto_a_pagar - (q3.total_percepciones -  q3.total_deducciones)) + ISNULL(pr.pension_restante, 0) AS excedente
                        FROM TotalesPorEmpleadoGeneralQ2 q2
                        LEFT JOIN TotalesPorEmpleadoQ3 q3
                            ON q2.codigoempleado = q3.codigoempleado
                        LEFT JOIN PensionResultado pr
                            ON q3.codigoempleado = pr.codigoempleado
                    ), ' + '
                    ParamConfig AS (
                        SELECT
                            nge.esquema AS concepto
                            , ngce.tope
                            , ngce.orden
                        FROM [becma-core2].[dbo].nomina_gape_cliente_esquema_combinacion AS ngce
                        INNER JOIN [becma-core2].[dbo].nomina_gape_esquema AS nge
                            ON ngce.id_nomina_gape_esquema = nge.id
                        WHERE
                            ngce.id_nomina_gape_cliente = @idNominaGapeCliente
                            AND ngce.id_nomina_gape_empresa = @idNominaGapeEmpresa
                            AND ngce.combinacion = @idEsquemaCombinacion
                            AND ngce.orden > 1
                    ), ' + '
                    BaseProrrateo AS (
                        SELECT
                            p.codigoempleado,
                            CAST(p.excedente AS DECIMAL(18,2)) AS netoAPagar,
                            c.concepto,
                            c.tope,
                            c.orden,
                            ROW_NUMBER() OVER (PARTITION BY p.codigoempleado ORDER BY c.orden) AS rn
                        FROM preTotalesProrrateo p
                        CROSS JOIN ParamConfig c
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

                        UNION ALL ' + '

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
                        GROUP BY
                            codigoempleado
                    ), ' + '
                    PensionExcedentes AS (
                        SELECT
                            q3.codigoempleado,
                            ''Pension Excedente'' AS concepto,
                                ISNULL(pr.pension_restante, 0) AS pension_excedente
                        FROM TotalesPorEmpleadoQ3 q3
                        LEFT JOIN PensionResultado pr
                            ON pr.codigoempleado = q3.codigoempleado
                    ), ' + '
                    DeduccionExcedentes AS (
                        SELECT
                            pe.codigoempleado,
                            ISNULL(SUM(pe.pension_excedente) , 0) AS total_deducciones_excedente
                        FROM TotalesPorEmpleadoQ3 q3
                        LEFT JOIN PensionExcedentes pe
                            ON pe.codigoempleado = q3.codigoempleado
                        GROUP BY
                            pe.codigoempleado
                    ), ' + '
                    NetoExcedentes AS (
                        SELECT
                            pe.codigoempleado,
                            (pe.total_percepciones_excedente - de.total_deducciones_excedente) AS neto_excedente
                        FROM PercepcionesExcedentes pe
                        LEFT JOIN DeduccionExcedentes de
                            ON pe.codigoempleado = de.codigoempleado
                    ), ' + '
                    NetoTotalAPagar AS (
                        SELECT
                            ne.codigoempleado,
                            (q3.total_percepciones - q3.total_deducciones) + (ne.neto_excedente) AS neto_total_a_pagar
                        FROM NetoExcedentes ne
                        LEFT JOIN TotalesPorEmpleadoQ3 AS q3
                            ON ne.codigoempleado = q3.codigoempleado
                    ), ' + '
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
                        WHERE
                            empPeriodo.cidperiodo = @idPeriodo
                            AND empPeriodo.estadoempleado IN (''A'', ''R'')
                            AND emp.TipoRegimen IN (''05'', ''06'', ''07'', ''08'', ''09'', ''10'', ''11'')
                            AND empPeriodo.idempleado BETWEEN @idEmpleadoInicial AND @idEmpleadoFinal
                    ), ' + '
                    nomGapeEmpresa AS (
                        SELECT
                            provisiones
                            , fee
                        FROM [becma-core2].dbo.nomina_gape_empresa_periodo_combinacion_parametrizacion
                        WHERE
                            id_nomina_gape_empresa = @idNominaGapeEmpresa
                            AND id_nomina_gape_cliente = @idNominaGapeCliente
                            AND id_nomina_gape_cliente_esquema_combinacion = @idEsquemaCombinacion
                            AND (
                                    id_nomina_gape_tipo_periodo = @idTipoPeriodo
                                    OR idtipoperiodo = @idTipoPeriodo
                                )
                    ), ' + '
                    totalCompensacion AS (
                        SELECT
                            nta.codigoempleado,
                            nge.fee AS  fee,
                            (nta.neto_total_a_pagar + q3.total_deducciones + q2.total_deducciones_excedente)
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
                            INNER JOIN TotalesPorEmpleadoQ3 AS q3
                                ON nta.codigoempleado = q3.codigoempleado
                            INNER JOIN DeduccionExcedentes AS q2
                                ON nta.codigoempleado = q2.codigoempleado
                            CROSS JOIN nomGapeEmpresa nge
                    ), ' + '
                    MovimientosO AS (
                        SELECT idempleado, idperiodo, his.idconcepto, valor, importetotal, con.descripcion, con.tipoconcepto, con.numeroconcepto
                        FROM Nom10007 AS his
                        INNER JOIN nom10004 con
                            ON his.idconcepto = con.idconcepto
                        WHERE
                            importetotal > 0
                            AND idperiodo = @idPeriodo
                            AND con.tipoconcepto IN (''O'')
                            AND con.numeroconcepto IN (90,89, 93, 96, 97, 98)
                        UNION ALL
                        SELECT idempleado, idperiodo, actual.idconcepto, valor, importetotal, con.descripcion, con.tipoconcepto, con.numeroconcepto
                        FROM Nom10008 AS actual
                        INNER JOIN nom10004 con
                            ON actual.idconcepto = con.idconcepto
                        WHERE
                            importetotal > 0
                            AND idperiodo = @idPeriodo
                            AND con.tipoconcepto IN (''O'')
                            AND con.numeroconcepto IN (90,89, 93, 96, 97, 98)
                    ), ' + '
                    MovimientosObligaciones AS (
						SELECT
							emp.codigoempleado AS codigoempleado,
							CASE
								WHEN pdo.numeroconcepto = 90
									THEN ''ISN''
								WHEN pdo.numeroconcepto IN (89, 93, 96, 97, 98)
									THEN ''IMSS Patronal''
							END AS concepto,
							''O'' AS tipoConcepto,
							SUM(pdo.importetotal) AS monto
						FROM nom10001 emp
						INNER JOIN nom10034 empPeriodo
							ON emp.idempleado = empPeriodo.idempleado
							    AND empPeriodo.cidperiodo = @idPeriodo
						LEFT JOIN MovimientosO pdo
							ON empPeriodo.cidperiodo = pdo.idperiodo
							    AND emp.idempleado = pdo.idempleado
						WHERE
                            empPeriodo.idtipoperiodo = @idTipoPeriodo
							AND empPeriodo.idempleado BETWEEN @idEmpleadoInicial AND @idEmpleadoFinal
							AND empPeriodo.estadoempleado IN (''A'', ''R'')
                            AND emp.TipoRegimen IN (''05'', ''06'', ''07'', ''08'', ''09'', ''10'', ''11'')
						GROUP BY
							emp.codigoempleado,
							CASE
								WHEN pdo.numeroconcepto = 90
									THEN ''ISN''
								WHEN pdo.numeroconcepto IN (89, 93, 96, 97, 98)
									THEN ''IMSS Patronal''
							END
					), ' + '
                    TotalesPorEmpleadoObligacion AS (
                        SELECT
                            codigoempleado AS codigoempleado,
                            SUM(monto) AS monto
                        FROM MovimientosObligaciones
                        GROUP BY
                            codigoempleado
                    ), ' + '
                    Comision AS (
                        SELECT
                            t.codigoempleado AS codigoempleado,
                            (com.monto_asignado + t.monto) * (com.fee / 100) AS total_comision
                        FROM TotalesPorEmpleadoObligacion AS t
                        INNER JOIN totalCompensacion com
                            ON t.codigoempleado = com.codigoempleado
                    ), ' + '
                    CostoTotal AS (
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
                    ) , ' + '
                    ResumenFinal AS (
                        SELECT
                            SUM(CASE WHEN concepto = ''Compensacion'' THEN monto ELSE 0 END) AS percepcion_bruta,
                            SUM(CASE WHEN concepto = ''Total cs'' THEN monto ELSE 0 END) AS costo_social
                        FROM CostoTotal
                    ), ' + '
                    TotalesCalculados AS (
                        SELECT
                            percepcion_bruta,
                            costo_social,
                            (percepcion_bruta + costo_social) AS base,
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

    /**
     * Consulta para formato Gastos por comprobar
     */

    private function datosGastosPorComprobar_1(Request $request)
    {
        try {
            $idNominaGapeCliente = $request->id_nomina_gape_cliente;
            $idNominaGapeEmpresa = $request->id_nomina_gape_empresa;
            $idNominaGapeEsquema = $request->id_nomina_gape_esquema;
            $idEsquemaCombinacion = $request->id_esquema;

            $idEmpleadoInicial = $request->empleado_inicial;
            $idEmpleadoFinal = $request->empleado_final;

            $idNominaGapeIncidencia = $request->id_nomina_gape_incidencia ?? 0;

            $sql = "
                DECLARE @idNominaGapeCliente INT;
                DECLARE @idNominaGapeEmpresa INT;
                DECLARE @idNominaGapeEsquema INT;
                DECLARE @idEsquemaCombinacion INT;

                DECLARE @idNominaGapeIncidencia INT;

                DECLARE @idEmpleadoInicial INT;
                DECLARE @idEmpleadoFinal INT;

                SET @idNominaGapeCliente = $idNominaGapeCliente;
                SET @idNominaGapeEmpresa = $idNominaGapeEmpresa;
                SET @idNominaGapeEsquema = $idNominaGapeEsquema;
                SET @idEsquemaCombinacion = $idEsquemaCombinacion;

                SET @idNominaGapeIncidencia = $idNominaGapeIncidencia;

                SET @idEmpleadoInicial = $idEmpleadoInicial;
                SET @idEmpleadoFinal = $idEmpleadoFinal;

                SELECT
                    codigoempleado
                    , apellidopaterno + ' ' + apellidomaterno + ' ' + nombre AS nombrelargo
                    , '' AS puesto
                    , FORMAT(fechaalta, 'dd-MM-yyyy') as fechaAlta
                    , ISNULL(campoextra1, '') AS fechaAltaGape
                    , ClabeInterbancaria AS nss
                    , cuentacw AS rfc
                    , '' AS curp
                    , ISNULL(emp.ccampoextranumerico1, 0) AS sueldoMensual
                    , ISNULL(emp.ccampoextranumerico2, 0) AS sueldoDiario
                    , 0 AS diasPeriodo
                    , 0 AS diasRetroactivos
                    , 0 AS incapacidad
                    , 0 AS faltas
                    , 0 AS diasPagados
                    , ngid.pago_simple AS sueldo
                FROM nomina_gape_empleado AS emp
                INNER JOIN nomina_gape_esquema AS esquema
                    on emp.id_nomina_gape_esquema = esquema.id
                LEFT JOIN nomina_gape_incidencia AS ngi
                    ON emp.id_nomina_gape_cliente = ngi.id_nomina_gape_cliente
                        AND emp.id_nomina_gape_empresa = ngi.id_nomina_gape_empresa
                        AND ngi.id = @idNominaGapeIncidencia
                LEFT JOIN nomina_gape_incidencia_detalle AS ngid
                    ON ngi.id = ngid.id_nomina_gape_incidencia
                        AND emp.codigoempleado = ngid.codigo_empleado
                        AND ngid.id_nomina_gape_esquema = @idNominaGapeEsquema
                        AND ngid.id_nomina_gape_combinacion = @idEsquemaCombinacion
                WHERE esquema.esquema = 'Gastos por comprobar'
                    AND emp.estado_empleado = 1
                    AND emp.id_nomina_gape_cliente = @idNominaGapeCliente
                    AND emp.id_nomina_gape_empresa = @idNominaGapeEmpresa
                    AND emp.id BETWEEN @idEmpleadoInicial AND @idEmpleadoFinal
                ORDER BY
                    emp.codigoempleado
            ";
            $result = DB::select($sql);


            return $result;
        } catch (\Exception $e) {
            return [];
        }
    }

    private function datosGastosPorComprobar_2(Request $request)
    {
        try {
            $idNominaGapeCliente = $request->id_nomina_gape_cliente;
            $idNominaGapeEmpresa = $request->id_nomina_gape_empresa;
            $idNominaGapeEsquema = $request->id_nomina_gape_esquema;
            $idEsquemaCombinacion = $request->id_esquema;

            $idEmpleadoInicial = $request->empleado_inicial;
            $idEmpleadoFinal = $request->empleado_final;

            $idNominaGapeIncidencia = $request->id_nomina_gape_incidencia ?? 0;

            $sql = "
                DECLARE @cols NVARCHAR(MAX),
                        @query NVARCHAR(MAX);

                ;WITH
                Incidencias AS (
					SELECT * FROM (VALUES
						('Prima Dominical cantidad', 'P', 500, 1),
						('Prima Dominical monto', 'P', 500, 1),
						('Dia Festivo cantidad', 'P', 500, 1),
						('Dia Festivo monto', 'P', 500, 1),
						('Comisiones', 'P', 500, 1),
						('Bono', 'P', 500, 1),
						('Horas Extra Doble cantidad', 'P', 500, 1),
						('Horas Extra Doble monto', 'P', 500, 1),
						('Horas Extra Triple cantidad', 'P', 500, 1),
						('Horas Extra Triple monto', 'P', 500, 1),
						('Pago adicional', 'P', 500, 1),
						('Premio puntualidad', 'P', 500, 1),
                        ('Descuentos', 'D', 500, 2),
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
                    SELECT descripcion, orden, numeroconcepto FROM Encabezados
                )

                SELECT @cols = STUFF((
                    SELECT ', ' + QUOTENAME(descripcion)
                    FROM titulos
                    ORDER BY orden, numeroconcepto, descripcion
                    FOR XML PATH(''), TYPE
                ).value('.', 'NVARCHAR(MAX)'), 1, 2, '');

                SET @query = CAST('' AS NVARCHAR(MAX)) + '

                DECLARE @idNominaGapeCliente INT;
                DECLARE @idNominaGapeEmpresa INT;
                DECLARE @idNominaGapeEsquema INT;
                DECLARE @idEsquemaCombinacion INT;

                DECLARE @idNominaGapeIncidencia INT;

                DECLARE @idEmpleadoInicial INT;
                DECLARE @idEmpleadoFinal INT;

                SET @idNominaGapeCliente = $idNominaGapeCliente;
                SET @idNominaGapeEmpresa = $idNominaGapeEmpresa;
                SET @idNominaGapeEsquema = $idNominaGapeEsquema;
                SET @idEsquemaCombinacion = $idEsquemaCombinacion;

                SET @idNominaGapeIncidencia = $idNominaGapeIncidencia;

                SET @idEmpleadoInicial = $idEmpleadoInicial;
                SET @idEmpleadoFinal = $idEmpleadoFinal;

                ;WITH
                    IncidenciasNormalizadas AS (
                        SELECT
                            emp.codigoempleado as codigoempleado,
                            x.descripcion AS descripcion,
                            ''P'' AS tipoConcepto,
                            x.valor AS valor,
                            ngid.pago_simple AS sueldo
                        FROM nomina_gape_empleado AS emp
                        INNER JOIN nomina_gape_esquema AS esquema
                            on emp.id_nomina_gape_esquema = esquema.id
                        LEFT JOIN nomina_gape_incidencia AS ngi
                            ON emp.id_nomina_gape_cliente = ngi.id_nomina_gape_cliente
                                AND emp.id_nomina_gape_empresa = ngi.id_nomina_gape_empresa
                                AND ngi.id = @idNominaGapeIncidencia
                        LEFT JOIN nomina_gape_incidencia_detalle AS ngid
                            ON ngi.id = ngid.id_nomina_gape_incidencia
                                AND emp.codigoempleado = ngid.codigo_empleado
                                AND ngid.id_nomina_gape_esquema = @idNominaGapeEsquema
                                AND ngid.id_nomina_gape_combinacion = @idEsquemaCombinacion
                        CROSS APPLY (VALUES
                            (''Bono'',                      ngid.bono),
                            (''Comisiones'',                ngid.comision),
                            (''Dia Festivo cantidad'',      ngid.cantidad_dias_festivos),
                            (''Dia Festivo monto'',         ISNULL(ngid.cantidad_dias_festivos, 0) * emp.ccampoextranumerico2 * 2),
                            (''Horas Extra Doble cantidad'', ngid.horas_extra_doble_cantidad),
                            (''Horas Extra Doble monto'',    ISNULL(ngid.horas_extra_doble_cantidad, 0) * (emp.ccampoextranumerico2 / 8.0) * 2),
                            (''Horas Extra Triple cantidad'', ngid.horas_extra_triple_cantidad),
                            (''Horas Extra Triple monto'',    ISNULL(ngid.horas_extra_triple_cantidad, 0) * (emp.ccampoextranumerico2 / 8.0) * 3),
                            (''Pago adicional'',             ngid.pago_adicional),
                            (''Premio puntualidad'',         ngid.premio_puntualidad),
                            (''Prima Dominical cantidad'',   ngid.cantidad_prima_dominical),
                            (''Prima Dominical monto'',      ISNULL(ngid.cantidad_prima_dominical, 0) * emp.ccampoextranumerico2 * 0.25)
                        ) AS x(descripcion, valor)
                        WHERE esquema.esquema = ''Gastos por comprobar''
                            AND emp.estado_empleado = 1
                            AND emp.id_nomina_gape_cliente = @idNominaGapeCliente
                            AND emp.id_nomina_gape_empresa = @idNominaGapeEmpresa
                            AND emp.id BETWEEN @idEmpleadoInicial AND @idEmpleadoFinal
                    ), ' + '
                    TotalesPorEmpleado AS (
                        SELECT
                            codigoempleado,
                            MAX(sueldo) AS sueldo,
                            SUM(CASE WHEN tipoConcepto = ''P'' THEN monto ELSE 0 END)
                            +
                            SUM(CASE WHEN tipoConcepto = ''P'' THEN valor ELSE 0 END) AS total_percepciones_sin_sueldo,
                            SUM(CASE WHEN tipoConcepto = ''D'' THEN monto ELSE 0 END) AS total_deducciones
                        FROM (
                            SELECT
                                codigoempleado,
                                sueldo AS sueldo,
                                ''P'' AS tipoConcepto,
                                0 AS monto,
                                valor AS valor
                            FROM IncidenciasNormalizadas
                            WHERE descripcion NOT LIKE ''%cantidad%''
                        ) AS x
                        GROUP BY codigoempleado
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
                                ''TOTAL PERCEPCIONES'',
                                sueldo + total_percepciones_sin_sueldo
                            FROM TotalesPorEmpleado
                            UNION ALL
                            SELECT
                                codigoempleado,
                                sueldo,
                                ''TOTAL DEDUCCIONES'',
                                total_deducciones
                            FROM TotalesPorEmpleado
                            UNION ALL
                            SELECT
                                codigoempleado,
                                sueldo,
                                ''NETO'',
                                (sueldo + total_percepciones_sin_sueldo) - (total_deducciones )
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

            //return $sql;

            /*
            dd([
                'row' => $sql,
            ]);
            */

            $result = DB::select($sql);

            return $result;
        } catch (\Exception $e) {
            return [];
        }
    }

    private function datosGastosPorComprobar_4(Request $request)
    {
        try {
            $idNominaGapeCliente = $request->id_nomina_gape_cliente;
            $idNominaGapeEmpresa = $request->id_nomina_gape_empresa;
            $idNominaGapeEsquema = $request->id_nomina_gape_esquema;
            $idEsquemaCombinacion = $request->id_esquema;

            $idEmpleadoInicial = $request->empleado_inicial;
            $idEmpleadoFinal = $request->empleado_final;

            $idNominaGapeIncidencia = $request->id_nomina_gape_incidencia ?? 0;

            $sql = "
                DECLARE @idNominaGapeCliente INT;
                DECLARE @idNominaGapeEmpresa INT;
                DECLARE @idNominaGapeEsquema INT;
                DECLARE @idEsquemaCombinacion INT;

                DECLARE @idNominaGapeIncidencia INT;

                DECLARE @idEmpleadoInicial INT;
                DECLARE @idEmpleadoFinal INT;

                SET @idNominaGapeCliente = $idNominaGapeCliente;
                SET @idNominaGapeEmpresa = $idNominaGapeEmpresa;
                SET @idEsquemaCombinacion = $idEsquemaCombinacion;

                DECLARE @cols NVARCHAR(MAX),
                        @query  NVARCHAR(MAX);
                ;WITH ParamConfig AS (
                    SELECT
                        nge.esquema AS descripcion
                        , ngce.tope
                        , ngce.orden
                    FROM [becma-core2].[dbo].nomina_gape_cliente_esquema_combinacion AS ngce
                    INNER JOIN [becma-core2].[dbo].nomina_gape_esquema AS nge
                        ON ngce.id_nomina_gape_esquema = nge.id
                    WHERE ngce.id_nomina_gape_cliente = @idNominaGapeCliente
                        AND ngce.id_nomina_gape_empresa = @idNominaGapeEmpresa
                        AND ngce.combinacion = @idEsquemaCombinacion
                        AND ngce.orden > 0
                ),
                Encabezados AS (
                    SELECT * FROM (VALUES
                        ('TOTAL ESQUEMAS DE PAGO', 'P', 1000, 100)
                    ) AS X(descripcion, tipoconcepto, numeroconcepto, orden)
                ),
                titulos AS (
                    SELECT descripcion AS descripcion, orden FROM Encabezados
                    UNION ALL
                    SELECT descripcion, orden FROM ParamConfig
                )
                SELECT @cols = STUFF((
                    SELECT ', ' + QUOTENAME(descripcion)
                    FROM titulos
                    ORDER BY orden
                    FOR XML PATH(''), TYPE
                ).value('.', 'NVARCHAR(MAX)'), 1, 2, '');

                SET @query = CAST('' AS NVARCHAR(MAX)) + '

                DECLARE @idNominaGapeCliente INT;
                DECLARE @idNominaGapeEmpresa INT;
                DECLARE @idNominaGapeEsquema INT;
                DECLARE @idEsquemaCombinacion INT;

                DECLARE @idNominaGapeIncidencia INT;

                DECLARE @idEmpleadoInicial INT;
                DECLARE @idEmpleadoFinal INT;

                SET @idNominaGapeCliente = $idNominaGapeCliente;
                SET @idNominaGapeEmpresa = $idNominaGapeEmpresa;
                SET @idNominaGapeEsquema = $idNominaGapeEsquema;
                SET @idEsquemaCombinacion = $idEsquemaCombinacion;

                SET @idNominaGapeIncidencia = $idNominaGapeIncidencia;

                SET @idEmpleadoInicial = $idEmpleadoInicial;
                SET @idEmpleadoFinal = $idEmpleadoFinal;

                ;WITH
                    IncidenciasNormalizadasQ2 AS (
                        SELECT
                            emp.codigoempleado as codigoempleado,
                            x.descripcion AS descripcion,
                            ''P'' AS tipoConcepto,
                            x.valor AS valor,
                            ngid.pago_simple AS sueldo
                        FROM nomina_gape_empleado AS emp
                        INNER JOIN nomina_gape_esquema AS esquema
                            on emp.id_nomina_gape_esquema = esquema.id
                        LEFT JOIN nomina_gape_incidencia AS ngi
                            ON emp.id_nomina_gape_cliente = ngi.id_nomina_gape_cliente
                                AND emp.id_nomina_gape_empresa = ngi.id_nomina_gape_empresa
                                AND ngi.id = @idNominaGapeIncidencia
                        LEFT JOIN nomina_gape_incidencia_detalle AS ngid
                            ON ngi.id = ngid.id_nomina_gape_incidencia
                                AND emp.codigoempleado = ngid.codigo_empleado
                                AND ngid.id_nomina_gape_esquema = @idNominaGapeEsquema
                                AND ngid.id_nomina_gape_combinacion = @idEsquemaCombinacion
                        CROSS APPLY (VALUES
                            (''Bono'',                      ngid.bono),
                            (''Comisiones'',                ngid.comision),
                            (''Dia Festivo cantidad'',      ngid.cantidad_dias_festivos),
                            (''Dia Festivo monto'',         ISNULL(ngid.cantidad_dias_festivos, 0) * emp.ccampoextranumerico2 * 2),
                            (''Horas Extra Doble cantidad'', ngid.horas_extra_doble_cantidad),
                            (''Horas Extra Doble monto'',    ISNULL(ngid.horas_extra_doble_cantidad, 0) * (emp.ccampoextranumerico2 / 8.0) * 2),
                            (''Horas Extra Triple cantidad'', ngid.horas_extra_triple_cantidad),
                            (''Horas Extra Triple monto'',    ISNULL(ngid.horas_extra_triple_cantidad, 0) * (emp.ccampoextranumerico2 / 8.0) * 3),
                            (''Pago adicional'',             ngid.pago_adicional),
                            (''Premio puntualidad'',         ngid.premio_puntualidad),
                            (''Prima Dominical cantidad'',   ngid.cantidad_prima_dominical),
                            (''Prima Dominical monto'',      ISNULL(ngid.cantidad_prima_dominical, 0) * emp.ccampoextranumerico2 * 0.25)
                        ) AS x(descripcion, valor)
                        WHERE esquema.esquema = ''Gastos por comprobar''
                            AND emp.estado_empleado = 1
                            AND emp.id_nomina_gape_cliente = @idNominaGapeCliente
                            AND emp.id_nomina_gape_empresa = @idNominaGapeEmpresa
                        AND emp.id BETWEEN @idEmpleadoInicial AND @idEmpleadoFinal
                    ), ' + '
                    PreTotalesPorEmpleadoGeneralQ2 AS (
                        SELECT
                            codigoempleado,
                            MAX(sueldo) AS sueldo,
                            SUM(CASE WHEN tipoConcepto = ''P'' THEN monto ELSE 0 END)
                            +
                            SUM(CASE WHEN tipoConcepto = ''P'' THEN valor ELSE 0 END) AS total_percepciones_sin_sueldo,
                            SUM(CASE WHEN tipoConcepto = ''D'' THEN monto ELSE 0 END) AS total_deducciones
                        FROM (
                            SELECT
                                codigoempleado,
                                sueldo AS sueldo,
                                ''P'' AS tipoConcepto,
                                0 AS monto,
                                valor AS valor
                            FROM IncidenciasNormalizadasQ2
                            WHERE descripcion NOT LIKE ''%cantidad%''
                        ) AS x
                        GROUP BY codigoempleado
                    ), ' + '
                    TotalesPorEmpleadoGeneralQ2 AS (
                        SELECT
                            codigoempleado,
                            sueldo,
                            sueldo + total_percepciones_sin_sueldo AS total_percepciones,
                            total_deducciones total_deducciones,
                            (sueldo + total_percepciones_sin_sueldo) - (total_deducciones) AS netoAPagar
                        FROM PreTotalesPorEmpleadoGeneralQ2
                    ), ' + '
                    ParamConfig AS (
                        SELECT
                            nge.esquema AS concepto
                            , ngce.tope
                            , ngce.orden
                        FROM [becma-core2].[dbo].nomina_gape_cliente_esquema_combinacion AS ngce
                        INNER JOIN [becma-core2].[dbo].nomina_gape_esquema AS nge
                            ON ngce.id_nomina_gape_esquema = nge.id
                        WHERE ngce.id_nomina_gape_cliente = @idNominaGapeCliente
                            AND ngce.id_nomina_gape_empresa = @idNominaGapeEmpresa
                            AND ngce.combinacion = @idEsquemaCombinacion
                            AND ngce.orden > 0
                    ),
                    BaseProrrateo AS (
                        SELECT
                            p.codigoempleado,
                            CAST(p.netoAPagar AS DECIMAL(18,2)) AS netoAPagar,
                            c.concepto,
                            c.tope,
                            c.orden,
                            ROW_NUMBER() OVER (PARTITION BY p.codigoempleado ORDER BY c.orden) AS rn
                        FROM TotalesPorEmpleadoGeneralQ2 p
                        CROSS JOIN ParamConfig c
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

                    ProrrateoFinal AS (
                        -- conceptos del prorrateo (columnas dinámicas)
                        SELECT
                            codigoempleado,
                            concepto,
                            monto_asignado
                        FROM ProrrateoRecursivo
                        UNION ALL

                        SELECT
                            codigoempleado,
                            ''TOTAL ESQUEMAS DE PAGO'' AS concepto,
                            total_percepciones_excedente AS monto_asignado
                        FROM PercepcionesExcedentes
                    ) ' + '

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

            //return $sql;
            /*
            dd([
                'row' => $sql,
            ]);
            */
            $result = DB::select($sql);

            return $result;
        } catch (\Exception $e) {
            return [];
        }
    }

    private function totalGastosPorComprobar_01(Request $request)
    {
        try {
            $idNominaGapeCliente = $request->id_nomina_gape_cliente;
            $idNominaGapeEmpresa = $request->id_nomina_gape_empresa;
            $idNominaGapeEsquema = $request->id_nomina_gape_esquema;
            $idEsquemaCombinacion = $request->id_esquema;

            $idEmpleadoInicial = $request->empleado_inicial;
            $idEmpleadoFinal = $request->empleado_final;

            $idNominaGapeIncidencia = $request->id_nomina_gape_incidencia ?? 0;

            $sql = "
                DECLARE @query NVARCHAR(MAX);

                SET @query = CAST('' AS NVARCHAR(MAX)) + '

                DECLARE @idNominaGapeCliente INT;
                DECLARE @idNominaGapeEmpresa INT;
                DECLARE @idNominaGapeEsquema INT;
                DECLARE @idEsquemaCombinacion INT;

                DECLARE @idNominaGapeIncidencia INT;

                DECLARE @idEmpleadoInicial INT;
                DECLARE @idEmpleadoFinal INT;

                SET @idNominaGapeCliente = $idNominaGapeCliente;
                SET @idNominaGapeEmpresa = $idNominaGapeEmpresa;
                SET @idNominaGapeEsquema = $idNominaGapeEsquema;
                SET @idEsquemaCombinacion = $idEsquemaCombinacion;

                SET @idNominaGapeIncidencia = $idNominaGapeIncidencia;

                SET @idEmpleadoInicial = $idEmpleadoInicial;
                SET @idEmpleadoFinal = $idEmpleadoFinal;

                ;WITH
                    IncidenciasNormalizadasQ2 AS (
                        SELECT
                            emp.codigoempleado as codigoempleado,
                            x.descripcion AS descripcion,
                            ''P'' AS tipoConcepto,
                            x.valor AS valor,
                            ngid.pago_simple AS sueldo
                        FROM nomina_gape_empleado AS emp
                        INNER JOIN nomina_gape_esquema AS esquema
                            on emp.id_nomina_gape_esquema = esquema.id
                        LEFT JOIN nomina_gape_incidencia AS ngi
                            ON emp.id_nomina_gape_cliente = ngi.id_nomina_gape_cliente
                                AND emp.id_nomina_gape_empresa = ngi.id_nomina_gape_empresa
                                AND ngi.id = @idNominaGapeIncidencia
                        LEFT JOIN nomina_gape_incidencia_detalle AS ngid
                            ON ngi.id = ngid.id_nomina_gape_incidencia
                                AND emp.codigoempleado = ngid.codigo_empleado
                                AND ngid.id_nomina_gape_esquema = @idNominaGapeEsquema
                                AND ngid.id_nomina_gape_combinacion = @idEsquemaCombinacion
                        CROSS APPLY (VALUES
                            (''Bono'',                      ngid.bono),
                            (''Comisiones'',                ngid.comision),
                            (''Dia Festivo cantidad'',      ngid.cantidad_dias_festivos),
                            (''Dia Festivo monto'',         ISNULL(ngid.cantidad_dias_festivos, 0) * emp.ccampoextranumerico2 * 2),
                            (''Horas Extra Doble cantidad'', ngid.horas_extra_doble_cantidad),
                            (''Horas Extra Doble monto'',    ISNULL(ngid.horas_extra_doble_cantidad, 0) * (emp.ccampoextranumerico2 / 8.0) * 2),
                            (''Horas Extra Triple cantidad'', ngid.horas_extra_triple_cantidad),
                            (''Horas Extra Triple monto'',    ISNULL(ngid.horas_extra_triple_cantidad, 0) * (emp.ccampoextranumerico2 / 8.0) * 3),
                            (''Pago adicional'',             ngid.pago_adicional),
                            (''Premio puntualidad'',         ngid.premio_puntualidad),
                            (''Prima Dominical cantidad'',   ngid.cantidad_prima_dominical),
                            (''Prima Dominical monto'',      ISNULL(ngid.cantidad_prima_dominical, 0) * emp.ccampoextranumerico2 * 0.25)
                        ) AS x(descripcion, valor)
                        WHERE esquema.esquema = ''Gastos por comprobar''
                            AND emp.estado_empleado = 1
                            AND emp.id_nomina_gape_cliente = @idNominaGapeCliente
                            AND emp.id_nomina_gape_empresa = @idNominaGapeEmpresa
                        AND emp.id BETWEEN @idEmpleadoInicial AND @idEmpleadoFinal
                    ), ' + '
                    PreTotalesPorEmpleadoGeneralQ2 AS (
                        SELECT
                            codigoempleado,
                            MAX(sueldo) AS sueldo,
                            SUM(CASE WHEN tipoConcepto = ''P'' THEN monto ELSE 0 END)
                            +
                            SUM(CASE WHEN tipoConcepto = ''P'' THEN valor ELSE 0 END) AS total_percepciones_sin_sueldo,
                            SUM(CASE WHEN tipoConcepto = ''D'' THEN monto ELSE 0 END) AS total_deducciones
                        FROM (
                            SELECT
                                codigoempleado,
                                sueldo AS sueldo,
                                ''P'' AS tipoConcepto,
                                0 AS monto,
                                valor AS valor
                            FROM IncidenciasNormalizadasQ2
                            WHERE descripcion NOT LIKE ''%cantidad%''
                        ) AS x
                        GROUP BY codigoempleado
                    ), ' + '
                    TotalesPorEmpleadoGeneralQ2 AS (
                        SELECT
                            codigoempleado,
                            sueldo,
                            sueldo + total_percepciones_sin_sueldo AS total_percepciones,
                            total_deducciones total_deducciones,
                            (sueldo + total_percepciones_sin_sueldo) - (total_deducciones) AS netoAPagar
                        FROM PreTotalesPorEmpleadoGeneralQ2
                    ), ' + '
                    ParamConfig AS (
                        SELECT
                            nge.esquema AS concepto
                            , ngce.tope
                            , ngce.orden
                        FROM [becma-core2].[dbo].nomina_gape_cliente_esquema_combinacion AS ngce
                        INNER JOIN [becma-core2].[dbo].nomina_gape_esquema AS nge
                            ON ngce.id_nomina_gape_esquema = nge.id
                        WHERE ngce.id_nomina_gape_cliente = @idNominaGapeCliente
                            AND ngce.id_nomina_gape_empresa = @idNominaGapeEmpresa
                            AND ngce.combinacion = @idEsquemaCombinacion
                            AND ngce.orden > 0
                    ),
                    BaseProrrateo AS (
                        SELECT
                            p.codigoempleado,
                            CAST(p.netoAPagar AS DECIMAL(18,2)) AS netoAPagar,
                            c.concepto,
                            c.tope,
                            c.orden,
                            ROW_NUMBER() OVER (PARTITION BY p.codigoempleado ORDER BY c.orden) AS rn
                        FROM TotalesPorEmpleadoGeneralQ2 p
                        CROSS JOIN ParamConfig c
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
                    parametrizacion AS (
                        SELECT DISTINCT
                            ngepcp.fee
                            , ngepcp.base_fee
                        FROM nomina_gape_cliente_esquema_combinacion AS ngcec
                        INNER JOIN nomina_gape_empresa_periodo_combinacion_parametrizacion AS ngepcp
                            ON ngcec.combinacion = ngepcp.id_nomina_gape_cliente_esquema_combinacion
                        INNER JOIN nomina_gape_esquema AS nge
                            ON ngcec.id_nomina_gape_esquema = nge.id
                        WHERE ngepcp.id_nomina_gape_cliente = @idNominaGapeCliente
                            AND ngepcp.id_nomina_gape_empresa = @idNominaGapeEmpresa
                            AND ngcec.id_nomina_gape_cliente = @idNominaGapeCliente
                            AND ngcec.combinacion = @idEsquemaCombinacion
                            AND ngcec.orden = 1
                    ), ' + '
                    totales AS (
                        SELECT
                            SUM(total_percepciones_excedente) AS percepcion_bruta
                        FROM PercepcionesExcedentes
                    )
                    SELECT
                        t.percepcion_bruta,
                        0 AS costo_social,
                        t.percepcion_bruta AS base,
                        p.fee / 100.0 AS fee_porcentaje,
                        t.percepcion_bruta * (p.fee / 100.0) AS fee,
                        t.percepcion_bruta * (1 + p.fee / 100.0) AS subtotal,
                        t.percepcion_bruta * (1 + p.fee / 100.0) * 0.16 AS iva,
                        t.percepcion_bruta * (1 + p.fee / 100.0) * 1.16 AS total
                    FROM totales t
                    CROSS JOIN parametrizacion p;
                    ';

                EXEC(@query);
            ";

            /*
            dd([
                'row' => $sql,
            ]);
            */

            //return $sql;
            $result = collect(DB::select($sql))->first();

            return $result;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Consulta para formato Tarjeta Fácil
     */

    private function datosTarjetaFacil_1(Request $request)
    {
        try {
            $idNominaGapeCliente = $request->id_nomina_gape_cliente;
            $idNominaGapeEmpresa = $request->id_nomina_gape_empresa;
            $idNominaGapeEsquema = $request->id_nomina_gape_esquema;
            $idEsquemaCombinacion = $request->id_esquema;

            $idEmpleadoInicial = $request->empleado_inicial;
            $idEmpleadoFinal = $request->empleado_final;

            $idNominaGapeIncidencia = $request->id_nomina_gape_incidencia ?? 0;

            $sql = "
                DECLARE @idNominaGapeCliente INT;
                DECLARE @idNominaGapeEmpresa INT;
                DECLARE @idNominaGapeEsquema INT;
                DECLARE @idEsquemaCombinacion INT;

                DECLARE @idNominaGapeIncidencia INT;

                DECLARE @idEmpleadoInicial INT;
                DECLARE @idEmpleadoFinal INT;

                SET @idNominaGapeCliente = $idNominaGapeCliente;
                SET @idNominaGapeEmpresa = $idNominaGapeEmpresa;
                SET @idNominaGapeEsquema = $idNominaGapeEsquema;
                SET @idEsquemaCombinacion = $idEsquemaCombinacion;

                SET @idNominaGapeIncidencia = $idNominaGapeIncidencia;

                SET @idEmpleadoInicial = $idEmpleadoInicial;
                SET @idEmpleadoFinal = $idEmpleadoFinal;

                SELECT
                    codigoempleado
                    , apellidopaterno + ' ' + apellidomaterno + ' ' + nombre AS nombrelargo
                    , '' AS puesto
                    , FORMAT(fechaalta, 'dd-MM-yyyy') as fechaAlta
                    , ISNULL(campoextra1, '') AS fechaAltaGape
                    , ClabeInterbancaria AS nss
                    , cuentacw AS rfc
                    , '' AS curp
                    , ISNULL(emp.ccampoextranumerico1, 0) AS sueldoMensual
                    , ISNULL(emp.ccampoextranumerico2, 0) AS sueldoDiario
                    , 0 AS diasPeriodo
                    , 0 AS diasRetroactivos
                    , 0 AS incapacidad
                    , 0 AS faltas
                    , 0 AS diasPagados
                    , ngid.pago_simple AS sueldo
                FROM nomina_gape_empleado AS emp
                INNER JOIN nomina_gape_esquema AS esquema
                    on emp.id_nomina_gape_esquema = esquema.id
                LEFT JOIN nomina_gape_incidencia AS ngi
                    ON emp.id_nomina_gape_cliente = ngi.id_nomina_gape_cliente
                        AND emp.id_nomina_gape_empresa = ngi.id_nomina_gape_empresa
                        AND ngi.id = @idNominaGapeIncidencia
                LEFT JOIN nomina_gape_incidencia_detalle AS ngid
                    ON ngi.id = ngid.id_nomina_gape_incidencia
                        AND emp.codigoempleado = ngid.codigo_empleado
                        AND ngid.id_nomina_gape_esquema = @idNominaGapeEsquema
                        AND ngid.id_nomina_gape_combinacion = @idEsquemaCombinacion
                WHERE
                    esquema.esquema = 'Tarjeta facil'
                    AND emp.estado_empleado = 1
                    AND emp.id_nomina_gape_cliente = @idNominaGapeCliente
                    AND emp.id_nomina_gape_empresa = @idNominaGapeEmpresa
                    AND emp.id BETWEEN @idEmpleadoInicial AND @idEmpleadoFinal
                ORDER BY
                    emp.codigoempleado
            ";
            $result = DB::select($sql);


            return $result;
        } catch (\Exception $e) {
            return [];
        }
    }

    private function datosTarjetaFacil_2(Request $request)
    {
        try {
            $idNominaGapeCliente = $request->id_nomina_gape_cliente;
            $idNominaGapeEmpresa = $request->id_nomina_gape_empresa;
            $idNominaGapeEsquema = $request->id_nomina_gape_esquema;
            $idEsquemaCombinacion = $request->id_esquema;

            $idEmpleadoInicial = $request->empleado_inicial;
            $idEmpleadoFinal = $request->empleado_final;

            $idNominaGapeIncidencia = $request->id_nomina_gape_incidencia ?? 0;

            $sql = "
                DECLARE @cols NVARCHAR(MAX),
                        @query NVARCHAR(MAX);

                ;WITH
                Incidencias AS (
					SELECT * FROM (VALUES
						('Prima Dominical cantidad', 'P', 500, 1),
						('Prima Dominical monto', 'P', 500, 1),
						('Dia Festivo cantidad', 'P', 500, 1),
						('Dia Festivo monto', 'P', 500, 1),
						('Comisiones', 'P', 500, 1),
						('Bono', 'P', 500, 1),
						('Horas Extra Doble cantidad', 'P', 500, 1),
						('Horas Extra Doble monto', 'P', 500, 1),
						('Horas Extra Triple cantidad', 'P', 500, 1),
						('Horas Extra Triple monto', 'P', 500, 1),
						('Pago adicional', 'P', 500, 1),
						('Premio puntualidad', 'P', 500, 1),
                        ('Descuentos', 'D', 500, 2),
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
                    SELECT descripcion, orden, numeroconcepto FROM Encabezados
                )

                SELECT @cols = STUFF((
                    SELECT ', ' + QUOTENAME(descripcion)
                    FROM titulos
                    ORDER BY orden, numeroconcepto, descripcion
                    FOR XML PATH(''), TYPE
                ).value('.', 'NVARCHAR(MAX)'), 1, 2, '');

                SET @query = CAST('' AS NVARCHAR(MAX)) + '

                DECLARE @idNominaGapeCliente INT;
                DECLARE @idNominaGapeEmpresa INT;
                DECLARE @idNominaGapeEsquema INT;
                DECLARE @idEsquemaCombinacion INT;

                DECLARE @idNominaGapeIncidencia INT;

                DECLARE @idEmpleadoInicial INT;
                DECLARE @idEmpleadoFinal INT;

                SET @idNominaGapeCliente = $idNominaGapeCliente;
                SET @idNominaGapeEmpresa = $idNominaGapeEmpresa;
                SET @idNominaGapeEsquema = $idNominaGapeEsquema;
                SET @idEsquemaCombinacion = $idEsquemaCombinacion;

                SET @idNominaGapeIncidencia = $idNominaGapeIncidencia;

                SET @idEmpleadoInicial = $idEmpleadoInicial;
                SET @idEmpleadoFinal = $idEmpleadoFinal;

                ;WITH
                    IncidenciasNormalizadas AS (
                        SELECT
                            emp.codigoempleado as codigoempleado,
                            x.descripcion AS descripcion,
                            ''P'' AS tipoConcepto,
                            x.valor AS valor,
                            ngid.pago_simple AS sueldo
                        FROM nomina_gape_empleado AS emp
                        INNER JOIN nomina_gape_esquema AS esquema
                            on emp.id_nomina_gape_esquema = esquema.id
                        LEFT JOIN nomina_gape_incidencia AS ngi
                            ON emp.id_nomina_gape_cliente = ngi.id_nomina_gape_cliente
                                AND emp.id_nomina_gape_empresa = ngi.id_nomina_gape_empresa
                                AND ngi.id = @idNominaGapeIncidencia
                        LEFT JOIN nomina_gape_incidencia_detalle AS ngid
                            ON ngi.id = ngid.id_nomina_gape_incidencia
                                AND emp.codigoempleado = ngid.codigo_empleado
                                AND ngid.id_nomina_gape_esquema = @idNominaGapeEsquema
                                AND ngid.id_nomina_gape_combinacion = @idEsquemaCombinacion
                        CROSS APPLY (VALUES
                            (''Bono'',                      ngid.bono),
                            (''Comisiones'',                ngid.comision),
                            (''Dia Festivo cantidad'',      ngid.cantidad_dias_festivos),
                            (''Dia Festivo monto'',         ISNULL(ngid.cantidad_dias_festivos, 0) * emp.ccampoextranumerico2 * 2),
                            (''Horas Extra Doble cantidad'', ngid.horas_extra_doble_cantidad),
                            (''Horas Extra Doble monto'',    ISNULL(ngid.horas_extra_doble_cantidad, 0) * (emp.ccampoextranumerico2 / 8.0) * 2),
                            (''Horas Extra Triple cantidad'', ngid.horas_extra_triple_cantidad),
                            (''Horas Extra Triple monto'',    ISNULL(ngid.horas_extra_triple_cantidad, 0) * (emp.ccampoextranumerico2 / 8.0) * 3),
                            (''Pago adicional'',             ngid.pago_adicional),
                            (''Premio puntualidad'',         ngid.premio_puntualidad),
                            (''Prima Dominical cantidad'',   ngid.cantidad_prima_dominical),
                            (''Prima Dominical monto'',      ISNULL(ngid.cantidad_prima_dominical, 0) * emp.ccampoextranumerico2 * 0.25)
                        ) AS x(descripcion, valor)
                        WHERE
                            esquema.esquema = ''Tarjeta facil''
                            AND emp.estado_empleado = 1
                            AND emp.id_nomina_gape_cliente = @idNominaGapeCliente
                            AND emp.id_nomina_gape_empresa = @idNominaGapeEmpresa
                            AND emp.id BETWEEN @idEmpleadoInicial AND @idEmpleadoFinal
                    ), ' + '
                    TotalesPorEmpleado AS (
                        SELECT
                            codigoempleado,
                            MAX(sueldo) AS sueldo,
                            SUM(CASE WHEN tipoConcepto = ''P'' THEN monto ELSE 0 END)
                            +
                            SUM(CASE WHEN tipoConcepto = ''P'' THEN valor ELSE 0 END) AS total_percepciones_sin_sueldo,
                            SUM(CASE WHEN tipoConcepto = ''D'' THEN monto ELSE 0 END) AS total_deducciones
                        FROM (
                            SELECT
                                codigoempleado,
                                sueldo AS sueldo,
                                ''P'' AS tipoConcepto,
                                0 AS monto,
                                valor AS valor
                            FROM IncidenciasNormalizadas
                            WHERE descripcion NOT LIKE ''%cantidad%''
                        ) AS x
                        GROUP BY
                            codigoempleado
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
                                ''TOTAL PERCEPCIONES'',
                                sueldo + total_percepciones_sin_sueldo
                            FROM TotalesPorEmpleado
                            UNION ALL
                            SELECT
                                codigoempleado,
                                sueldo,
                                ''TOTAL DEDUCCIONES'',
                                total_deducciones
                            FROM TotalesPorEmpleado
                            UNION ALL
                            SELECT
                                codigoempleado,
                                sueldo,
                                ''NETO'',
                                (sueldo + total_percepciones_sin_sueldo) - (total_deducciones )
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

            //return $sql;

            /*
            dd([
                'row' => $sql,
            ]);
            */

            $result = DB::select($sql);

            return $result;
        } catch (\Exception $e) {
            return [];
        }
    }

    private function datosTarjetaFacil_4(Request $request)
    {
        try {
            $idNominaGapeCliente = $request->id_nomina_gape_cliente;
            $idNominaGapeEmpresa = $request->id_nomina_gape_empresa;
            $idNominaGapeEsquema = $request->id_nomina_gape_esquema;
            $idEsquemaCombinacion = $request->id_esquema;

            $idEmpleadoInicial = $request->empleado_inicial;
            $idEmpleadoFinal = $request->empleado_final;

            $idNominaGapeIncidencia = $request->id_nomina_gape_incidencia ?? 0;

            $sql = "
                DECLARE @idNominaGapeCliente INT;
                DECLARE @idNominaGapeEmpresa INT;
                DECLARE @idNominaGapeEsquema INT;
                DECLARE @idEsquemaCombinacion INT;

                DECLARE @idNominaGapeIncidencia INT;

                DECLARE @idEmpleadoInicial INT;
                DECLARE @idEmpleadoFinal INT;

                SET @idNominaGapeCliente = $idNominaGapeCliente;
                SET @idEsquemaCombinacion = $idEsquemaCombinacion;

                DECLARE @cols NVARCHAR(MAX),
                        @query  NVARCHAR(MAX);
                ;WITH ParamConfig AS (
                    SELECT
                        nge.esquema AS descripcion
                        , ngce.tope
                        , ngce.orden
                    FROM [becma-core2].[dbo].nomina_gape_cliente_esquema_combinacion AS ngce
                    INNER JOIN [becma-core2].[dbo].nomina_gape_esquema AS nge
                        ON ngce.id_nomina_gape_esquema = nge.id
                    WHERE
                        ngce.id_nomina_gape_cliente = @idNominaGapeCliente
                        AND ngce.combinacion = @idEsquemaCombinacion
                        AND ngce.orden > 0
                ),
                Encabezados AS (
                    SELECT * FROM (VALUES
                        ('TOTAL ESQUEMAS DE PAGO', 'P', 1000, 100)
                    ) AS X(descripcion, tipoconcepto, numeroconcepto, orden)
                ),
                titulos AS (
                    SELECT descripcion AS descripcion, orden FROM Encabezados
                    UNION ALL
                    SELECT descripcion, orden FROM ParamConfig
                )
                SELECT @cols = STUFF((
                    SELECT ', ' + QUOTENAME(descripcion)
                    FROM titulos
                    ORDER BY orden
                    FOR XML PATH(''), TYPE
                ).value('.', 'NVARCHAR(MAX)'), 1, 2, '');

                SET @query = CAST('' AS NVARCHAR(MAX)) + '

                DECLARE @idNominaGapeCliente INT;
                DECLARE @idNominaGapeEmpresa INT;
                DECLARE @idNominaGapeEsquema INT;
                DECLARE @idEsquemaCombinacion INT;

                DECLARE @idNominaGapeIncidencia INT;

                DECLARE @idEmpleadoInicial INT;
                DECLARE @idEmpleadoFinal INT;

                SET @idNominaGapeCliente = $idNominaGapeCliente;
                SET @idNominaGapeEmpresa = $idNominaGapeEmpresa;
                SET @idNominaGapeEsquema = $idNominaGapeEsquema;
                SET @idEsquemaCombinacion = $idEsquemaCombinacion;

                SET @idNominaGapeIncidencia = $idNominaGapeIncidencia;

                SET @idEmpleadoInicial = $idEmpleadoInicial;
                SET @idEmpleadoFinal = $idEmpleadoFinal;

                ;WITH
                    IncidenciasNormalizadasQ2 AS (
                        SELECT
                            emp.codigoempleado as codigoempleado,
                            x.descripcion AS descripcion,
                            ''P'' AS tipoConcepto,
                            x.valor AS valor,
                            ngid.pago_simple AS sueldo
                        FROM nomina_gape_empleado AS emp
                        INNER JOIN nomina_gape_esquema AS esquema
                            on emp.id_nomina_gape_esquema = esquema.id
                        LEFT JOIN nomina_gape_incidencia AS ngi
                            ON emp.id_nomina_gape_cliente = ngi.id_nomina_gape_cliente
                                AND emp.id_nomina_gape_empresa = ngi.id_nomina_gape_empresa
                                AND ngi.id = @idNominaGapeIncidencia
                        LEFT JOIN nomina_gape_incidencia_detalle AS ngid
                            ON ngi.id = ngid.id_nomina_gape_incidencia
                                AND emp.codigoempleado = ngid.codigo_empleado
                                AND ngid.id_nomina_gape_esquema = @idNominaGapeEsquema
                                AND ngid.id_nomina_gape_combinacion = @idEsquemaCombinacion
                        CROSS APPLY (VALUES
                            (''Bono'',                      ngid.bono),
                            (''Comisiones'',                ngid.comision),
                            (''Dia Festivo cantidad'',      ngid.cantidad_dias_festivos),
                            (''Dia Festivo monto'',         ISNULL(ngid.cantidad_dias_festivos, 0) * emp.ccampoextranumerico2 * 2),
                            (''Horas Extra Doble cantidad'', ngid.horas_extra_doble_cantidad),
                            (''Horas Extra Doble monto'',    ISNULL(ngid.horas_extra_doble_cantidad, 0) * (emp.ccampoextranumerico2 / 8.0) * 2),
                            (''Horas Extra Triple cantidad'', ngid.horas_extra_triple_cantidad),
                            (''Horas Extra Triple monto'',    ISNULL(ngid.horas_extra_triple_cantidad, 0) * (emp.ccampoextranumerico2 / 8.0) * 3),
                            (''Pago adicional'',             ngid.pago_adicional),
                            (''Premio puntualidad'',         ngid.premio_puntualidad),
                            (''Prima Dominical cantidad'',   ngid.cantidad_prima_dominical),
                            (''Prima Dominical monto'',      ISNULL(ngid.cantidad_prima_dominical, 0) * emp.ccampoextranumerico2 * 0.25)
                        ) AS x(descripcion, valor)
                        WHERE
                            esquema.esquema = ''Tarjeta facil''
                            AND emp.estado_empleado = 1
                            AND emp.id_nomina_gape_cliente = @idNominaGapeCliente
                            AND emp.id_nomina_gape_empresa = @idNominaGapeEmpresa
                        AND emp.id BETWEEN @idEmpleadoInicial AND @idEmpleadoFinal
                    ), ' + '
                    PreTotalesPorEmpleadoGeneralQ2 AS (
                        SELECT
                            codigoempleado,
                            MAX(sueldo) AS sueldo,
                            SUM(CASE WHEN tipoConcepto = ''P'' THEN monto ELSE 0 END)
                            +
                            SUM(CASE WHEN tipoConcepto = ''P'' THEN valor ELSE 0 END) AS total_percepciones_sin_sueldo,
                            SUM(CASE WHEN tipoConcepto = ''D'' THEN monto ELSE 0 END) AS total_deducciones
                        FROM (
                            SELECT
                                codigoempleado,
                                sueldo AS sueldo,
                                ''P'' AS tipoConcepto,
                                0 AS monto,
                                valor AS valor
                            FROM IncidenciasNormalizadasQ2
                            WHERE
                                descripcion NOT LIKE ''%cantidad%''
                        ) AS x
                        GROUP BY codigoempleado
                    ), ' + '
                    TotalesPorEmpleadoGeneralQ2 AS (
                        SELECT
                            codigoempleado,
                            sueldo,
                            sueldo + total_percepciones_sin_sueldo AS total_percepciones,
                            total_deducciones total_deducciones,
                            (sueldo + total_percepciones_sin_sueldo) - (total_deducciones) AS netoAPagar
                        FROM PreTotalesPorEmpleadoGeneralQ2
                    ), ' + '
                    ParamConfig AS (
                        SELECT
                            nge.esquema AS concepto
                            , ngce.tope
                            , ngce.orden
                        FROM [becma-core2].[dbo].nomina_gape_cliente_esquema_combinacion AS ngce
                        INNER JOIN [becma-core2].[dbo].nomina_gape_esquema AS nge
                            ON ngce.id_nomina_gape_esquema = nge.id
                        WHERE
                            ngce.id_nomina_gape_cliente = @idNominaGapeCliente
                            AND ngce.id_nomina_gape_empresa = @idNominaGapeEmpresa
                            AND ngce.combinacion = @idEsquemaCombinacion
                            AND ngce.orden > 0
                    ),
                    BaseProrrateo AS (
                        SELECT
                            p.codigoempleado,
                            CAST(p.netoAPagar AS DECIMAL(18,2)) AS netoAPagar,
                            c.concepto,
                            c.tope,
                            c.orden,
                            ROW_NUMBER() OVER (PARTITION BY p.codigoempleado ORDER BY c.orden) AS rn
                        FROM TotalesPorEmpleadoGeneralQ2 p
                        CROSS JOIN ParamConfig c
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
                        GROUP BY
                            codigoempleado
                    ), ' + '
                    ProrrateoFinal AS (
                        -- conceptos del prorrateo (columnas dinámicas)
                        SELECT
                            codigoempleado,
                            concepto,
                            monto_asignado
                        FROM ProrrateoRecursivo
                        UNION ALL

                        SELECT
                            codigoempleado,
                            ''TOTAL ESQUEMAS DE PAGO'' AS concepto,
                            total_percepciones_excedente AS monto_asignado
                        FROM PercepcionesExcedentes
                    ) ' + '

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

            //return $sql;

            /*
            dd([
                'row' => $sql,
            ]);
            */

            $result = DB::select($sql);

            return $result;
        } catch (\Exception $e) {
            return [];
        }
    }

    private function totalTarjetaFacil_01(Request $request)
    {
        try {
            $idNominaGapeCliente = $request->id_nomina_gape_cliente;
            $idNominaGapeEmpresa = $request->id_nomina_gape_empresa;
            $idNominaGapeEsquema = $request->id_nomina_gape_esquema;
            $idEsquemaCombinacion = $request->id_esquema;

            $idEmpleadoInicial = $request->empleado_inicial;
            $idEmpleadoFinal = $request->empleado_final;

            $idNominaGapeIncidencia = $request->id_nomina_gape_incidencia ?? 0;

            $sql = "
                DECLARE @query NVARCHAR(MAX);

                SET @query = CAST('' AS NVARCHAR(MAX)) + '

                DECLARE @idNominaGapeCliente INT;
                DECLARE @idNominaGapeEmpresa INT;
                DECLARE @idNominaGapeEsquema INT;
                DECLARE @idEsquemaCombinacion INT;

                DECLARE @idNominaGapeIncidencia INT;

                DECLARE @idEmpleadoInicial INT;
                DECLARE @idEmpleadoFinal INT;

                SET @idNominaGapeCliente = $idNominaGapeCliente;
                SET @idNominaGapeEmpresa = $idNominaGapeEmpresa;
                SET @idNominaGapeEsquema = $idNominaGapeEsquema;
                SET @idEsquemaCombinacion = $idEsquemaCombinacion;

                SET @idNominaGapeIncidencia = $idNominaGapeIncidencia;

                SET @idEmpleadoInicial = $idEmpleadoInicial;
                SET @idEmpleadoFinal = $idEmpleadoFinal;

                ;WITH
                    IncidenciasNormalizadasQ2 AS (
                        SELECT
                            emp.codigoempleado as codigoempleado,
                            x.descripcion AS descripcion,
                            ''P'' AS tipoConcepto,
                            x.valor AS valor,
                            ngid.pago_simple AS sueldo
                        FROM nomina_gape_empleado AS emp
                        INNER JOIN nomina_gape_esquema AS esquema
                            on emp.id_nomina_gape_esquema = esquema.id
                        LEFT JOIN nomina_gape_incidencia AS ngi
                            ON emp.id_nomina_gape_cliente = ngi.id_nomina_gape_cliente
                                AND emp.id_nomina_gape_empresa = ngi.id_nomina_gape_empresa
                                AND ngi.id = @idNominaGapeIncidencia
                        LEFT JOIN nomina_gape_incidencia_detalle AS ngid
                            ON ngi.id = ngid.id_nomina_gape_incidencia
                                AND emp.codigoempleado = ngid.codigo_empleado
                                AND ngid.id_nomina_gape_esquema = @idNominaGapeEsquema
                                AND ngid.id_nomina_gape_combinacion = @idEsquemaCombinacion
                        CROSS APPLY (VALUES
                            (''Bono'',                      ngid.bono),
                            (''Comisiones'',                ngid.comision),
                            (''Dia Festivo cantidad'',      ngid.cantidad_dias_festivos),
                            (''Dia Festivo monto'',         ISNULL(ngid.cantidad_dias_festivos, 0) * emp.ccampoextranumerico2 * 2),
                            (''Horas Extra Doble cantidad'', ngid.horas_extra_doble_cantidad),
                            (''Horas Extra Doble monto'',    ISNULL(ngid.horas_extra_doble_cantidad, 0) * (emp.ccampoextranumerico2 / 8.0) * 2),
                            (''Horas Extra Triple cantidad'', ngid.horas_extra_triple_cantidad),
                            (''Horas Extra Triple monto'',    ISNULL(ngid.horas_extra_triple_cantidad, 0) * (emp.ccampoextranumerico2 / 8.0) * 3),
                            (''Pago adicional'',             ngid.pago_adicional),
                            (''Premio puntualidad'',         ngid.premio_puntualidad),
                            (''Prima Dominical cantidad'',   ngid.cantidad_prima_dominical),
                            (''Prima Dominical monto'',      ISNULL(ngid.cantidad_prima_dominical, 0) * emp.ccampoextranumerico2 * 0.25)
                        ) AS x(descripcion, valor)
                        WHERE
                            esquema.esquema = ''Tarjeta facil''
                            AND emp.estado_empleado = 1
                            AND emp.id_nomina_gape_cliente = @idNominaGapeCliente
                            AND emp.id_nomina_gape_empresa = @idNominaGapeEmpresa
                            AND emp.id BETWEEN @idEmpleadoInicial AND @idEmpleadoFinal
                    ), ' + '
                    PreTotalesPorEmpleadoGeneralQ2 AS (
                        SELECT
                            codigoempleado,
                            MAX(sueldo) AS sueldo,
                            SUM(CASE WHEN tipoConcepto = ''P'' THEN monto ELSE 0 END)
                            +
                            SUM(CASE WHEN tipoConcepto = ''P'' THEN valor ELSE 0 END) AS total_percepciones_sin_sueldo,
                            SUM(CASE WHEN tipoConcepto = ''D'' THEN monto ELSE 0 END) AS total_deducciones
                        FROM (
                            SELECT
                                codigoempleado,
                                sueldo AS sueldo,
                                ''P'' AS tipoConcepto,
                                0 AS monto,
                                valor AS valor
                            FROM IncidenciasNormalizadasQ2
                            WHERE
                                descripcion NOT LIKE ''%cantidad%''
                        ) AS x
                        GROUP BY codigoempleado
                    ), ' + '
                    TotalesPorEmpleadoGeneralQ2 AS (
                        SELECT
                            codigoempleado,
                            sueldo,
                            sueldo + total_percepciones_sin_sueldo AS total_percepciones,
                            total_deducciones total_deducciones,
                            (sueldo + total_percepciones_sin_sueldo) - (total_deducciones) AS netoAPagar
                        FROM PreTotalesPorEmpleadoGeneralQ2
                    ), ' + '
                    ParamConfig AS (
                        SELECT
                            nge.esquema AS concepto
                            , ngce.tope
                            , ngce.orden
                        FROM [becma-core2].[dbo].nomina_gape_cliente_esquema_combinacion AS ngce
                        INNER JOIN [becma-core2].[dbo].nomina_gape_esquema AS nge
                            ON ngce.id_nomina_gape_esquema = nge.id
                        WHERE
                            ngce.id_nomina_gape_cliente = @idNominaGapeCliente
                            AND ngce.id_nomina_gape_empresa = @idNominaGapeEmpresa
                            AND ngce.combinacion = @idEsquemaCombinacion
                            AND ngce.orden > 0
                    ),
                    BaseProrrateo AS (
                        SELECT
                            p.codigoempleado,
                            CAST(p.netoAPagar AS DECIMAL(18,2)) AS netoAPagar,
                            c.concepto,
                            c.tope,
                            c.orden,
                            ROW_NUMBER() OVER (PARTITION BY p.codigoempleado ORDER BY c.orden) AS rn
                        FROM TotalesPorEmpleadoGeneralQ2 p
                        CROSS JOIN ParamConfig c
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
                        GROUP BY
                            codigoempleado
                    ), ' + '
                    parametrizacion AS (
                        SELECT DISTINCT
                            ngepcp.fee
                            , ngepcp.base_fee
                        FROM nomina_gape_cliente_esquema_combinacion AS ngcec
                        INNER JOIN nomina_gape_empresa_periodo_combinacion_parametrizacion AS ngepcp
                            ON ngcec.combinacion = ngepcp.id_nomina_gape_cliente_esquema_combinacion
                        INNER JOIN nomina_gape_esquema AS nge
                            ON ngcec.id_nomina_gape_esquema = nge.id
                        WHERE
                            ngepcp.id_nomina_gape_cliente = @idNominaGapeCliente
                            AND ngepcp.id_nomina_gape_empresa = @idNominaGapeEmpresa
                            AND ngcec.id_nomina_gape_cliente = @idNominaGapeCliente
                            AND ngcec.combinacion = @idEsquemaCombinacion
                            AND ngcec.orden = 1
                    ), ' + '
                    totales AS (
                        SELECT
                            SUM(total_percepciones_excedente) AS percepcion_bruta
                        FROM PercepcionesExcedentes
                    )
                    SELECT
                        t.percepcion_bruta,
                        0 AS costo_social,
                        t.percepcion_bruta AS base,
                        p.fee / 100.0 AS fee_porcentaje,
                        t.percepcion_bruta * (p.fee / 100.0) AS fee,
                        t.percepcion_bruta * (1 + p.fee / 100.0) AS subtotal,
                        t.percepcion_bruta * (1 + p.fee / 100.0) * 0.16 AS iva,
                        t.percepcion_bruta * (1 + p.fee / 100.0) * 1.16 AS total
                    FROM totales t
                    CROSS JOIN parametrizacion p;
                    ';

                EXEC(@query);
            ";

            //return $sql;
            $result = collect(DB::select($sql))->first();

            return $result;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Consulta para formato Sindicato
     */

    private function datosSindicato_1(Request $request)
    {
        try {
            $idNominaGapeCliente = $request->id_nomina_gape_cliente;
            $idNominaGapeEmpresa = $request->id_nomina_gape_empresa;
            $idNominaGapeEsquema = $request->id_nomina_gape_esquema;
            $idEsquemaCombinacion = $request->id_esquema;

            $idEmpleadoInicial = $request->empleado_inicial;
            $idEmpleadoFinal = $request->empleado_final;

            $idNominaGapeIncidencia = $request->id_nomina_gape_incidencia ?? 0;

            $sql = "
                DECLARE @idNominaGapeCliente INT;
                DECLARE @idNominaGapeEmpresa INT;
                DECLARE @idNominaGapeEsquema INT;
                DECLARE @idEsquemaCombinacion INT;

                DECLARE @idNominaGapeIncidencia INT;

                DECLARE @idEmpleadoInicial INT;
                DECLARE @idEmpleadoFinal INT;

                SET @idNominaGapeCliente = $idNominaGapeCliente;
                SET @idNominaGapeEmpresa = $idNominaGapeEmpresa;
                SET @idNominaGapeEsquema = $idNominaGapeEsquema;
                SET @idEsquemaCombinacion = $idEsquemaCombinacion;

                SET @idNominaGapeIncidencia = $idNominaGapeIncidencia;

                SET @idEmpleadoInicial = $idEmpleadoInicial;
                SET @idEmpleadoFinal = $idEmpleadoFinal;

                SELECT
                    codigoempleado
                    , apellidopaterno + ' ' + apellidomaterno + ' ' + nombre AS nombrelargo
                    , '' AS puesto
                    , FORMAT(fechaalta, 'dd-MM-yyyy') as fechaAlta
                    , ISNULL(campoextra1, '') AS fechaAltaGape
                    , ClabeInterbancaria AS nss
                    , cuentacw AS rfc
                    , '' AS curp
                    , ISNULL(emp.ccampoextranumerico1, 0) AS sueldoMensual
                    , ISNULL(emp.ccampoextranumerico2, 0) AS sueldoDiario
                    , 0 AS diasPeriodo
                    , 0 AS diasRetroactivos
                    , 0 AS incapacidad
                    , 0 AS faltas
                    , 0 AS diasPagados
                    , ngid.pago_simple AS sueldo
                FROM nomina_gape_empleado AS emp
                INNER JOIN nomina_gape_esquema AS esquema
                    on emp.id_nomina_gape_esquema = esquema.id
                LEFT JOIN nomina_gape_incidencia AS ngi
                    ON emp.id_nomina_gape_cliente = ngi.id_nomina_gape_cliente
                        AND emp.id_nomina_gape_empresa = ngi.id_nomina_gape_empresa
                        AND ngi.id = @idNominaGapeIncidencia
                LEFT JOIN nomina_gape_incidencia_detalle AS ngid
                    ON ngi.id = ngid.id_nomina_gape_incidencia
                        AND emp.codigoempleado = ngid.codigo_empleado
                        AND ngid.id_nomina_gape_esquema = @idNominaGapeEsquema
                        AND ngid.id_nomina_gape_combinacion = @idEsquemaCombinacion
                WHERE
                    esquema.esquema = 'Sindicato'
                    AND emp.estado_empleado = 1
                    AND emp.id_nomina_gape_cliente = @idNominaGapeCliente
                    AND emp.id_nomina_gape_empresa = @idNominaGapeEmpresa
                    AND emp.id BETWEEN @idEmpleadoInicial AND @idEmpleadoFinal
                ORDER BY
                    emp.codigoempleado
            ";
            $result = DB::select($sql);


            return $result;
        } catch (\Exception $e) {
            return [];
        }
    }

    private function datosSindicato_2(Request $request)
    {
        try {
            $idNominaGapeCliente = $request->id_nomina_gape_cliente;
            $idNominaGapeEmpresa = $request->id_nomina_gape_empresa;
            $idNominaGapeEsquema = $request->id_nomina_gape_esquema;
            $idEsquemaCombinacion = $request->id_esquema;

            $idEmpleadoInicial = $request->empleado_inicial;
            $idEmpleadoFinal = $request->empleado_final;

            $idNominaGapeIncidencia = $request->id_nomina_gape_incidencia ?? 0;

            $sql = "
                DECLARE @cols NVARCHAR(MAX),
                        @query NVARCHAR(MAX);

                ;WITH
                Incidencias AS (
					SELECT * FROM (VALUES
						('Prima Dominical cantidad', 'P', 500, 1),
						('Prima Dominical monto', 'P', 500, 1),
						('Dia Festivo cantidad', 'P', 500, 1),
						('Dia Festivo monto', 'P', 500, 1),
						('Comisiones', 'P', 500, 1),
						('Bono', 'P', 500, 1),
						('Horas Extra Doble cantidad', 'P', 500, 1),
						('Horas Extra Doble monto', 'P', 500, 1),
						('Horas Extra Triple cantidad', 'P', 500, 1),
						('Horas Extra Triple monto', 'P', 500, 1),
						('Pago adicional', 'P', 500, 1),
						('Premio puntualidad', 'P', 500, 1),
                        ('Descuentos', 'D', 500, 2),
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
                    SELECT descripcion, orden, numeroconcepto FROM Encabezados
                )

                SELECT @cols = STUFF((
                    SELECT ', ' + QUOTENAME(descripcion)
                    FROM titulos
                    ORDER BY orden, numeroconcepto, descripcion
                    FOR XML PATH(''), TYPE
                ).value('.', 'NVARCHAR(MAX)'), 1, 2, '');

                SET @query = CAST('' AS NVARCHAR(MAX)) + '

                DECLARE @idNominaGapeCliente INT;
                DECLARE @idNominaGapeEmpresa INT;
                DECLARE @idNominaGapeEsquema INT;
                DECLARE @idEsquemaCombinacion INT;

                DECLARE @idNominaGapeIncidencia INT;

                DECLARE @idEmpleadoInicial INT;
                DECLARE @idEmpleadoFinal INT;

                SET @idNominaGapeCliente = $idNominaGapeCliente;
                SET @idNominaGapeEmpresa = $idNominaGapeEmpresa;
                SET @idNominaGapeEsquema = $idNominaGapeEsquema;
                SET @idEsquemaCombinacion = $idEsquemaCombinacion;

                SET @idNominaGapeIncidencia = $idNominaGapeIncidencia;

                SET @idEmpleadoInicial = $idEmpleadoInicial;
                SET @idEmpleadoFinal = $idEmpleadoFinal;

                ;WITH
                    IncidenciasNormalizadas AS (
                        SELECT
                            emp.codigoempleado as codigoempleado,
                            x.descripcion AS descripcion,
                            ''P'' AS tipoConcepto,
                            x.valor AS valor,
                            ngid.pago_simple AS sueldo
                        FROM nomina_gape_empleado AS emp
                        INNER JOIN nomina_gape_esquema AS esquema
                            on emp.id_nomina_gape_esquema = esquema.id
                        LEFT JOIN nomina_gape_incidencia AS ngi
                            ON emp.id_nomina_gape_cliente = ngi.id_nomina_gape_cliente
                                AND emp.id_nomina_gape_empresa = ngi.id_nomina_gape_empresa
                                AND ngi.id = @idNominaGapeIncidencia
                        LEFT JOIN nomina_gape_incidencia_detalle AS ngid
                            ON ngi.id = ngid.id_nomina_gape_incidencia
                                AND emp.codigoempleado = ngid.codigo_empleado
                                AND ngid.id_nomina_gape_esquema = @idNominaGapeEsquema
                                AND ngid.id_nomina_gape_combinacion = @idEsquemaCombinacion
                        CROSS APPLY (VALUES
                            (''Bono'',                      ngid.bono),
                            (''Comisiones'',                ngid.comision),
                            (''Dia Festivo cantidad'',      ngid.cantidad_dias_festivos),
                            (''Dia Festivo monto'',         ISNULL(ngid.cantidad_dias_festivos, 0) * emp.ccampoextranumerico2 * 2),
                            (''Horas Extra Doble cantidad'', ngid.horas_extra_doble_cantidad),
                            (''Horas Extra Doble monto'',    ISNULL(ngid.horas_extra_doble_cantidad, 0) * (emp.ccampoextranumerico2 / 8.0) * 2),
                            (''Horas Extra Triple cantidad'', ngid.horas_extra_triple_cantidad),
                            (''Horas Extra Triple monto'',    ISNULL(ngid.horas_extra_triple_cantidad, 0) * (emp.ccampoextranumerico2 / 8.0) * 3),
                            (''Pago adicional'',             ngid.pago_adicional),
                            (''Premio puntualidad'',         ngid.premio_puntualidad),
                            (''Prima Dominical cantidad'',   ngid.cantidad_prima_dominical),
                            (''Prima Dominical monto'',      ISNULL(ngid.cantidad_prima_dominical, 0) * emp.ccampoextranumerico2 * 0.25)
                        ) AS x(descripcion, valor)
                        WHERE
                            esquema.esquema = ''Sindicato''
                            AND emp.estado_empleado = 1
                            AND emp.id_nomina_gape_cliente = @idNominaGapeCliente
                            AND emp.id_nomina_gape_empresa = @idNominaGapeEmpresa
                            AND emp.id BETWEEN @idEmpleadoInicial AND @idEmpleadoFinal
                    ), ' + '
                    TotalesPorEmpleado AS (
                        SELECT
                            codigoempleado,
                            MAX(sueldo) AS sueldo,
                            SUM(CASE WHEN tipoConcepto = ''P'' THEN monto ELSE 0 END)
                            +
                            SUM(CASE WHEN tipoConcepto = ''P'' THEN valor ELSE 0 END) AS total_percepciones_sin_sueldo,
                            SUM(CASE WHEN tipoConcepto = ''D'' THEN monto ELSE 0 END) AS total_deducciones
                        FROM (
                            SELECT
                                codigoempleado,
                                sueldo AS sueldo,
                                ''P'' AS tipoConcepto,
                                0 AS monto,
                                valor AS valor
                            FROM IncidenciasNormalizadas
                            WHERE
                                descripcion NOT LIKE ''%cantidad%''
                        ) AS x
                        GROUP BY
                            codigoempleado
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
                                ''TOTAL PERCEPCIONES'',
                                sueldo + total_percepciones_sin_sueldo
                            FROM TotalesPorEmpleado
                            UNION ALL
                            SELECT
                                codigoempleado,
                                sueldo,
                                ''TOTAL DEDUCCIONES'',
                                total_deducciones
                            FROM TotalesPorEmpleado
                            UNION ALL
                            SELECT
                                codigoempleado,
                                sueldo,
                                ''NETO'',
                                (sueldo + total_percepciones_sin_sueldo) - (total_deducciones )
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

            //return $sql;

            /*
            dd([
                'row' => $sql,
            ]);
            */

            $result = DB::select($sql);

            return $result;
        } catch (\Exception $e) {
            return [];
        }
    }

    private function datosSindicato_4(Request $request)
    {
        try {
            $idNominaGapeCliente = $request->id_nomina_gape_cliente;
            $idNominaGapeEmpresa = $request->id_nomina_gape_empresa;
            $idNominaGapeEsquema = $request->id_nomina_gape_esquema;
            $idEsquemaCombinacion = $request->id_esquema;

            $idEmpleadoInicial = $request->empleado_inicial;
            $idEmpleadoFinal = $request->empleado_final;

            $idNominaGapeIncidencia = $request->id_nomina_gape_incidencia ?? 0;

            $sql = "
                DECLARE @idNominaGapeCliente INT;
                DECLARE @idNominaGapeEmpresa INT;
                DECLARE @idNominaGapeEsquema INT;
                DECLARE @idEsquemaCombinacion INT;

                DECLARE @idNominaGapeIncidencia INT;

                DECLARE @idEmpleadoInicial INT;
                DECLARE @idEmpleadoFinal INT;

                SET @idNominaGapeCliente = $idNominaGapeCliente;
                SET @idEsquemaCombinacion = $idEsquemaCombinacion;

                DECLARE @cols NVARCHAR(MAX),
                        @query  NVARCHAR(MAX);
                ;WITH ParamConfig AS (
                    SELECT
                        nge.esquema AS descripcion
                        , ngce.tope
                        , ngce.orden
                    FROM [becma-core2].[dbo].nomina_gape_cliente_esquema_combinacion AS ngce
                    INNER JOIN [becma-core2].[dbo].nomina_gape_esquema AS nge
                        ON ngce.id_nomina_gape_esquema = nge.id
                    WHERE
                        ngce.id_nomina_gape_cliente = @idNominaGapeCliente
                        AND ngce.id_nomina_gape_empresa = @idNominaGapeEmpresa
                        AND ngce.combinacion = @idEsquemaCombinacion
                        AND ngce.orden > 0
                ),
                Encabezados AS (
                    SELECT * FROM (VALUES
                        ('TOTAL ESQUEMAS DE PAGO', 'P', 1000, 100)
                    ) AS X(descripcion, tipoconcepto, numeroconcepto, orden)
                ),
                titulos AS (
                    SELECT descripcion AS descripcion, orden FROM Encabezados
                    UNION ALL
                    SELECT descripcion, orden FROM ParamConfig
                )
                SELECT @cols = STUFF((
                    SELECT ', ' + QUOTENAME(descripcion)
                    FROM titulos
                    ORDER BY orden
                    FOR XML PATH(''), TYPE
                ).value('.', 'NVARCHAR(MAX)'), 1, 2, '');

                SET @query = CAST('' AS NVARCHAR(MAX)) + '

                DECLARE @idNominaGapeCliente INT;
                DECLARE @idNominaGapeEmpresa INT;
                DECLARE @idNominaGapeEsquema INT;
                DECLARE @idEsquemaCombinacion INT;

                DECLARE @idNominaGapeIncidencia INT;

                DECLARE @idEmpleadoInicial INT;
                DECLARE @idEmpleadoFinal INT;

                SET @idNominaGapeCliente = $idNominaGapeCliente;
                SET @idNominaGapeEmpresa = $idNominaGapeEmpresa;
                SET @idNominaGapeEsquema = $idNominaGapeEsquema;
                SET @idEsquemaCombinacion = $idEsquemaCombinacion;

                SET @idNominaGapeIncidencia = $idNominaGapeIncidencia;

                SET @idEmpleadoInicial = $idEmpleadoInicial;
                SET @idEmpleadoFinal = $idEmpleadoFinal;

                ;WITH
                    IncidenciasNormalizadasQ2 AS (
                        SELECT
                            emp.codigoempleado as codigoempleado,
                            x.descripcion AS descripcion,
                            ''P'' AS tipoConcepto,
                            x.valor AS valor,
                            ngid.pago_simple AS sueldo
                        FROM nomina_gape_empleado AS emp
                        INNER JOIN nomina_gape_esquema AS esquema
                            on emp.id_nomina_gape_esquema = esquema.id
                        LEFT JOIN nomina_gape_incidencia AS ngi
                            ON emp.id_nomina_gape_cliente = ngi.id_nomina_gape_cliente
                                AND emp.id_nomina_gape_empresa = ngi.id_nomina_gape_empresa
                                AND ngi.id = @idNominaGapeIncidencia
                        LEFT JOIN nomina_gape_incidencia_detalle AS ngid
                            ON ngi.id = ngid.id_nomina_gape_incidencia
                                AND emp.codigoempleado = ngid.codigo_empleado
                                AND ngid.id_nomina_gape_esquema = @idNominaGapeEsquema
                                AND ngid.id_nomina_gape_combinacion = @idEsquemaCombinacion
                        CROSS APPLY (VALUES
                            (''Bono'',                      ngid.bono),
                            (''Comisiones'',                ngid.comision),
                            (''Dia Festivo cantidad'',      ngid.cantidad_dias_festivos),
                            (''Dia Festivo monto'',         ISNULL(ngid.cantidad_dias_festivos, 0) * emp.ccampoextranumerico2 * 2),
                            (''Horas Extra Doble cantidad'', ngid.horas_extra_doble_cantidad),
                            (''Horas Extra Doble monto'',    ISNULL(ngid.horas_extra_doble_cantidad, 0) * (emp.ccampoextranumerico2 / 8.0) * 2),
                            (''Horas Extra Triple cantidad'', ngid.horas_extra_triple_cantidad),
                            (''Horas Extra Triple monto'',    ISNULL(ngid.horas_extra_triple_cantidad, 0) * (emp.ccampoextranumerico2 / 8.0) * 3),
                            (''Pago adicional'',             ngid.pago_adicional),
                            (''Premio puntualidad'',         ngid.premio_puntualidad),
                            (''Prima Dominical cantidad'',   ngid.cantidad_prima_dominical),
                            (''Prima Dominical monto'',      ISNULL(ngid.cantidad_prima_dominical, 0) * emp.ccampoextranumerico2 * 0.25)
                        ) AS x(descripcion, valor)
                        WHERE
                            esquema.esquema = ''Sindicato''
                            AND emp.estado_empleado = 1
                            AND emp.id_nomina_gape_cliente = @idNominaGapeCliente
                            AND emp.id_nomina_gape_empresa = @idNominaGapeEmpresa
                            AND emp.id BETWEEN @idEmpleadoInicial AND @idEmpleadoFinal
                    ), ' + '
                    PreTotalesPorEmpleadoGeneralQ2 AS (
                        SELECT
                            codigoempleado,
                            MAX(sueldo) AS sueldo,
                            SUM(CASE WHEN tipoConcepto = ''P'' THEN monto ELSE 0 END)
                            +
                            SUM(CASE WHEN tipoConcepto = ''P'' THEN valor ELSE 0 END) AS total_percepciones_sin_sueldo,
                            SUM(CASE WHEN tipoConcepto = ''D'' THEN monto ELSE 0 END) AS total_deducciones
                        FROM (
                            SELECT
                                codigoempleado,
                                sueldo AS sueldo,
                                ''P'' AS tipoConcepto,
                                0 AS monto,
                                valor AS valor
                            FROM IncidenciasNormalizadasQ2
                            WHERE
                                descripcion NOT LIKE ''%cantidad%''
                        ) AS x
                        GROUP BY
                            codigoempleado
                    ), ' + '
                    TotalesPorEmpleadoGeneralQ2 AS (
                        SELECT
                            codigoempleado,
                            sueldo,
                            sueldo + total_percepciones_sin_sueldo AS total_percepciones,
                            total_deducciones total_deducciones,
                            (sueldo + total_percepciones_sin_sueldo) - (total_deducciones) AS netoAPagar
                        FROM PreTotalesPorEmpleadoGeneralQ2
                    ), ' + '
                    ParamConfig AS (
                        SELECT
                            nge.esquema AS concepto
                            , ngce.tope
                            , ngce.orden
                        FROM [becma-core2].[dbo].nomina_gape_cliente_esquema_combinacion AS ngce
                        INNER JOIN [becma-core2].[dbo].nomina_gape_esquema AS nge
                            ON ngce.id_nomina_gape_esquema = nge.id
                        WHERE
                            ngce.id_nomina_gape_cliente = @idNominaGapeCliente
                            AND ngce.id_nomina_gape_empresa = @idNominaGapeEmpresa
                            AND ngce.combinacion = @idEsquemaCombinacion
                            AND ngce.orden > 0
                    ),
                    BaseProrrateo AS (
                        SELECT
                            p.codigoempleado,
                            CAST(p.netoAPagar AS DECIMAL(18,2)) AS netoAPagar,
                            c.concepto,
                            c.tope,
                            c.orden,
                            ROW_NUMBER() OVER (PARTITION BY p.codigoempleado ORDER BY c.orden) AS rn
                        FROM TotalesPorEmpleadoGeneralQ2 p
                        CROSS JOIN ParamConfig c
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

                        UNION ALL ' + '

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

                    ProrrateoFinal AS (
                        -- conceptos del prorrateo (columnas dinámicas)
                        SELECT
                            codigoempleado,
                            concepto,
                            monto_asignado
                        FROM ProrrateoRecursivo
                        UNION ALL

                        SELECT
                            codigoempleado,
                            ''TOTAL ESQUEMAS DE PAGO'' AS concepto,
                            total_percepciones_excedente AS monto_asignado
                        FROM PercepcionesExcedentes
                    ) ' + '

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

            //return $sql;

            /*
            dd([
                'row' => $sql,
            ]);
            */

            $result = DB::select($sql);

            return $result;
        } catch (\Exception $e) {
            return [];
        }
    }

    private function totalSindicato_01(Request $request)
    {
        try {
            $idNominaGapeCliente = $request->id_nomina_gape_cliente;
            $idNominaGapeEmpresa = $request->id_nomina_gape_empresa;
            $idNominaGapeEsquema = $request->id_nomina_gape_esquema;
            $idEsquemaCombinacion = $request->id_esquema;

            $idEmpleadoInicial = $request->empleado_inicial;
            $idEmpleadoFinal = $request->empleado_final;

            $idNominaGapeIncidencia = $request->id_nomina_gape_incidencia ?? 0;

            $sql = "
                DECLARE @query NVARCHAR(MAX);

                SET @query = CAST('' AS NVARCHAR(MAX)) + '

                DECLARE @idNominaGapeCliente INT;
                DECLARE @idNominaGapeEmpresa INT;
                DECLARE @idNominaGapeEsquema INT;
                DECLARE @idEsquemaCombinacion INT;

                DECLARE @idNominaGapeIncidencia INT;

                DECLARE @idEmpleadoInicial INT;
                DECLARE @idEmpleadoFinal INT;

                SET @idNominaGapeCliente = $idNominaGapeCliente;
                SET @idNominaGapeEmpresa = $idNominaGapeEmpresa;
                SET @idNominaGapeEsquema = $idNominaGapeEsquema;
                SET @idEsquemaCombinacion = $idEsquemaCombinacion;

                SET @idNominaGapeIncidencia = $idNominaGapeIncidencia;

                SET @idEmpleadoInicial = $idEmpleadoInicial;
                SET @idEmpleadoFinal = $idEmpleadoFinal;

                ;WITH
                    IncidenciasNormalizadasQ2 AS (
                        SELECT
                            emp.codigoempleado as codigoempleado,
                            x.descripcion AS descripcion,
                            ''P'' AS tipoConcepto,
                            x.valor AS valor,
                            ngid.pago_simple AS sueldo
                        FROM nomina_gape_empleado AS emp
                        INNER JOIN nomina_gape_esquema AS esquema
                            on emp.id_nomina_gape_esquema = esquema.id
                        LEFT JOIN nomina_gape_incidencia AS ngi
                            ON emp.id_nomina_gape_cliente = ngi.id_nomina_gape_cliente
                                AND emp.id_nomina_gape_empresa = ngi.id_nomina_gape_empresa
                                AND ngi.id = @idNominaGapeIncidencia
                        LEFT JOIN nomina_gape_incidencia_detalle AS ngid
                            ON ngi.id = ngid.id_nomina_gape_incidencia
                                AND emp.codigoempleado = ngid.codigo_empleado
                                AND ngid.id_nomina_gape_esquema = @idNominaGapeEsquema
                                AND ngid.id_nomina_gape_combinacion = @idEsquemaCombinacion
                        CROSS APPLY (VALUES
                            (''Bono'',                      ngid.bono),
                            (''Comisiones'',                ngid.comision),
                            (''Dia Festivo cantidad'',      ngid.cantidad_dias_festivos),
                            (''Dia Festivo monto'',         ISNULL(ngid.cantidad_dias_festivos, 0) * emp.ccampoextranumerico2 * 2),
                            (''Horas Extra Doble cantidad'', ngid.horas_extra_doble_cantidad),
                            (''Horas Extra Doble monto'',    ISNULL(ngid.horas_extra_doble_cantidad, 0) * (emp.ccampoextranumerico2 / 8.0) * 2),
                            (''Horas Extra Triple cantidad'', ngid.horas_extra_triple_cantidad),
                            (''Horas Extra Triple monto'',    ISNULL(ngid.horas_extra_triple_cantidad, 0) * (emp.ccampoextranumerico2 / 8.0) * 3),
                            (''Pago adicional'',             ngid.pago_adicional),
                            (''Premio puntualidad'',         ngid.premio_puntualidad),
                            (''Prima Dominical cantidad'',   ngid.cantidad_prima_dominical),
                            (''Prima Dominical monto'',      ISNULL(ngid.cantidad_prima_dominical, 0) * emp.ccampoextranumerico2 * 0.25)
                        ) AS x(descripcion, valor)
                        WHERE
                            esquema.esquema = ''Sindicato''
                            AND emp.estado_empleado = 1
                            AND emp.id_nomina_gape_cliente = @idNominaGapeCliente
                            AND emp.id_nomina_gape_empresa = @idNominaGapeEmpresa
                            AND emp.id BETWEEN @idEmpleadoInicial AND @idEmpleadoFinal
                    ), ' + '
                    PreTotalesPorEmpleadoGeneralQ2 AS (
                        SELECT
                            codigoempleado,
                            MAX(sueldo) AS sueldo,
                            SUM(CASE WHEN tipoConcepto = ''P'' THEN monto ELSE 0 END)
                            +
                            SUM(CASE WHEN tipoConcepto = ''P'' THEN valor ELSE 0 END) AS total_percepciones_sin_sueldo,
                            SUM(CASE WHEN tipoConcepto = ''D'' THEN monto ELSE 0 END) AS total_deducciones
                        FROM (
                            SELECT
                                codigoempleado,
                                sueldo AS sueldo,
                                ''P'' AS tipoConcepto,
                                0 AS monto,
                                valor AS valor
                            FROM IncidenciasNormalizadasQ2
                            WHERE descripcion NOT LIKE ''%cantidad%''
                        ) AS x
                        GROUP BY codigoempleado
                    ), ' + '
                    TotalesPorEmpleadoGeneralQ2 AS (
                        SELECT
                            codigoempleado,
                            sueldo,
                            sueldo + total_percepciones_sin_sueldo AS total_percepciones,
                            total_deducciones total_deducciones,
                            (sueldo + total_percepciones_sin_sueldo) - (total_deducciones) AS netoAPagar
                        FROM PreTotalesPorEmpleadoGeneralQ2
                    ), ' + '
                    ParamConfig AS (
                        SELECT
                            nge.esquema AS concepto
                            , ngce.tope
                            , ngce.orden
                        FROM [becma-core2].[dbo].nomina_gape_cliente_esquema_combinacion AS ngce
                        INNER JOIN [becma-core2].[dbo].nomina_gape_esquema AS nge
                            ON ngce.id_nomina_gape_esquema = nge.id
                        WHERE
                            ngce.id_nomina_gape_cliente = @idNominaGapeCliente
                            AND ngce.id_nomina_gape_empresa = @idNominaGapeEmpresa
                            AND ngce.combinacion = @idEsquemaCombinacion
                            AND ngce.orden > 0
                    ), ' + '
                    BaseProrrateo AS (
                        SELECT
                            p.codigoempleado,
                            CAST(p.netoAPagar AS DECIMAL(18,2)) AS netoAPagar,
                            c.concepto,
                            c.tope,
                            c.orden,
                            ROW_NUMBER() OVER (PARTITION BY p.codigoempleado ORDER BY c.orden) AS rn
                        FROM TotalesPorEmpleadoGeneralQ2 p
                        CROSS JOIN ParamConfig c
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

                        UNION ALL ' + '

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
                        GROUP BY
                            codigoempleado
                    ), ' + '
                    parametrizacion AS (
                        SELECT DISTINCT
                            ngepcp.fee
                            , ngepcp.base_fee
                        FROM nomina_gape_cliente_esquema_combinacion AS ngcec
                        INNER JOIN nomina_gape_empresa_periodo_combinacion_parametrizacion AS ngepcp
                            ON ngcec.combinacion = ngepcp.id_nomina_gape_cliente_esquema_combinacion
                        INNER JOIN nomina_gape_esquema AS nge
                            ON ngcec.id_nomina_gape_esquema = nge.id
                        WHERE
                            ngepcp.id_nomina_gape_cliente = @idNominaGapeCliente
                            AND ngepcp.id_nomina_gape_empresa = @idNominaGapeEmpresa
                            AND ngcec.id_nomina_gape_cliente = @idNominaGapeCliente
                            AND ngcec.combinacion = @idEsquemaCombinacion
                            AND ngcec.orden = 1
                    ), ' + '
                    totales AS (
                        SELECT
                            SUM(total_percepciones_excedente) AS percepcion_bruta
                        FROM PercepcionesExcedentes                    )
                    SELECT
                        t.percepcion_bruta,
                        0 AS costo_social,
                        t.percepcion_bruta AS base,
                        p.fee / 100.0 AS fee_porcentaje,
                        t.percepcion_bruta * (p.fee / 100.0) AS fee,
                        t.percepcion_bruta * (1 + p.fee / 100.0) AS subtotal,
                        t.percepcion_bruta * (1 + p.fee / 100.0) * 0.16 AS iva,
                        t.percepcion_bruta * (1 + p.fee / 100.0) * 1.16 AS total
                    FROM totales t
                    CROSS JOIN parametrizacion p;
                    ';

                EXEC(@query);
            ";

            //return $sql;
            $result = collect(DB::select($sql))->first();

            return $result;
        } catch (\Exception $e) {
            return [];
        }
    }
}
