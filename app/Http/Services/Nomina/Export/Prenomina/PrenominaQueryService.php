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
     * Consulta para formato fiscal/mixto.
     */
    private function datosQuery1(Request $request)
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
                    , ngid.cantidad_dias_retroactivos AS diasRetroactivos
                    , ngid.cantidad_incapacidad AS incapacidad
                    , ngid.cantidad_faltas AS faltas
                    , (periodo.diasdepago + ngid.cantidad_dias_retroactivos - ngid.cantidad_incapacidad - ngid.cantidad_faltas) AS diasPagados
                    , ISNULL((emp.ccampoextranumerico2 * ((periodo.diasdepago + ngid.cantidad_dias_retroactivos - ngid.cantidad_incapacidad - ngid.cantidad_faltas))), 0) AS sueldo
                FROM nom10001 emp
                    INNER JOIN nom10034 AS empPeriodo
                        ON emp.idempleado = empPeriodo.idempleado
                            AND empPeriodo.cidperiodo = @idPeriodo
                    INNER JOIN nom10002 AS periodo
                        ON empPeriodo.cidperiodo = periodo.idperiodo
                    LEFT JOIN nom10006 AS puesto
                        ON emp.idpuesto = puesto.idpuesto
                    LEFT JOIN [becma-core2].dbo.nomina_gape_incidencia AS ngi
                        ON periodo.idperiodo = ngi.id_periodo
                    INNER JOIN [becma-core2].dbo.nomina_gape_incidencia_detalle AS ngid
                        ON ngi.id = ngid.id_nomina_gape_incidencia
                        AND empPeriodo.idempleado = ngid.id_empleado
                WHERE empPeriodo.idtipoperiodo = @idTipoPeriodo
                    AND empPeriodo.idempleado BETWEEN @idEmpleadoInicial AND @idEmpleadoFinal
                    AND empPeriodo.estadoempleado IN ('A', 'R')
                    AND ngi.id_nomina_gape_empresa = @idNominaGapeEmpresa
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
                    , ngid.cantidad_dias_retroactivos
                    , ngid.cantidad_incapacidad
                    , ngid.cantidad_faltas
                ORDER BY
                        emp.codigoempleado
            ";
            $result = DB::connection('sqlsrv_dynamic')->select($sql);


            return $result;
        } catch (\Exception $e) {
            return [];
        }
    }

    private function datosQuery2(Request $request)
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

             return $sql;

            $result = DB::connection('sqlsrv_dynamic')->select($sql);

            return $result;
        } catch (\Exception $e) {
            return [];
        }
    }

    private function datosQuery3(Request $request)
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

            $result = DB::connection('sqlsrv_dynamic')->select($sql);

            return $result;
        } catch (\Exception $e) {
            return [];
        }
    }

    private function datosQuery4(Request $request)
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

    private function datosQuery5(Request $request)
    {
        try {
            $idNominaGapeEmpresa = $request->id_nomina_gape_empresa;
            $idTipoPeriodo = $request->id_tipo_periodo;
            $idPeriodo = $request->periodo_inicial;

            $idEmpleadoInicial = $request->empleado_inicial;
            $idEmpleadoFinal = $request->empleado_final;

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


    private function datosQuery6(Request $request)
    {
        try {
            $idNominaGapeEmpresa = $request->id_nomina_gape_empresa;
            $idTipoPeriodo = $request->id_tipo_periodo;
            $idPeriodo = $request->periodo_inicial;

            $idEmpleadoInicial = $request->empleado_inicial;
            $idEmpleadoFinal = $request->empleado_final;

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


    private function datosTotales7(Request $request)
    {
        try {
            $idNominaGapeEmpresa = $request->id_nomina_gape_empresa;
            $idTipoPeriodo = $request->id_tipo_periodo;
            $idPeriodo = $request->periodo_inicial;

            $idEmpleadoInicial = $request->empleado_inicial;
            $idEmpleadoFinal = $request->empleado_final;

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

            $result = DB::connection('sqlsrv_dynamic')->select($sql);

            return $result;
        } catch (\Exception $e) {
            return [];
        }
    }
}
