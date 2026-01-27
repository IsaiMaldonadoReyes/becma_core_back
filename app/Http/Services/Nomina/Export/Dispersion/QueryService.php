<?php

namespace App\Http\Services\Nomina\Export\Dispersion;

use Illuminate\Http\Request;

use App\Http\Services\Core\HelperService;

use Illuminate\Support\Facades\DB;

class QueryService
{
    public function getData(string $queryName, Request $request)
    {
        if (!method_exists($this, $queryName)) {
            throw new \Exception("La consulta '$queryName' no está definida en QueryService.");
        }

        return $this->{$queryName}($request);
    }

    private function detalle_sueldo_imss(Request $request)
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
                        ('Sueldo IMSS', 'P', 10, 0)
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

                ;WITH
                    MovimientosRetro AS (
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
                                emp.ccampoextranumerico3 AS pension,
                                emp.nombre + emp.apellidopaterno + emp.apellidomaterno AS nombre,
                                emp.bancopagoelectronico AS claveBanco,
                                emp.cuentapagoelectronico AS cuentaPagoElectronico,
                                emp.ClabeInterbancaria AS clabeInterbancaria,
                                ISNULL(CAST(emp.campoextra3 AS NVARCHAR(50)), '''') AS campoextra3,
                                ISNULL(CAST(emp.ccampoextranumerico3 AS NVARCHAR(50)), '''') AS tarjetafacil,
                                emp.rfc +
                                SUBSTRING(CONVERT(char(10), emp.fechanacimiento, 126), 3, 2) +
                                SUBSTRING(CONVERT(char(10), emp.fechanacimiento, 126), 6, 2) +
                                SUBSTRING(CONVERT(char(10), emp.fechanacimiento, 126), 9, 2) +
                                emp.homoclave as rfc
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
                            WHERE ' + '
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
                                emp.rfc,
                                emp.fechanacimiento,
                                emp.homoclave,
                                emp.nombre,
                                emp.apellidopaterno,
                                emp.apellidomaterno,
                                emp.bancopagoelectronico,
                                emp.cuentapagoelectronico,
                                emp.ClabeInterbancaria,
                                emp.campoextra3,
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
                                nombre,
                                rfc,
                                claveBanco,
                                cuentaPagoElectronico,
                                clabeInterbancaria,
                                campoextra3,
                                tarjetafacil,
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
                                sd,
                                nombre,
                                rfc,
                                claveBanco,
                                cuentaPagoElectronico,
                                clabeInterbancaria,
                                campoextra3,
                                tarjetafacil
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
                    DatosEmpleadoQ3 AS (
                        SELECT
                            codigoempleado,
                            MAX(nombre)  AS nombre,
                            MAX(rfc)     AS rfc,
                            MAX(claveBanco)  AS claveBanco,
                            MAX(cuentaPagoElectronico)  AS cuentaPagoElectronico,
                            MAX(clabeInterbancaria)  AS clabeInterbancaria,
                            MAX(campoextra3) AS campoextra3,
                            MAX(tarjetafacil) AS tarjetafacil
                        FROM TotalesPorEmpleadoQ3
                        GROUP BY
                            codigoempleado
                    ),
                    ProrrateoFinal AS (
                        SELECT
                            codigoempleado,
                            nombre,
                            rfc,
                            claveBanco,
                            cuentaPagoElectronico,
                            clabeInterbancaria,
                            campoextra3,
                            tarjetafacil,
                            ''Sueldo IMSS'' AS concepto,
                            (total_percepciones - total_deducciones) AS monto_asignado
                        FROM TotalesPorEmpleadoQ3

                        UNION ALL
                        -- conceptos del prorrateo (columnas dinámicas)
                        SELECT
                            r.codigoempleado,
                            d.nombre,
                            d.rfc,
                            d.claveBanco,
                            d.cuentaPagoElectronico,
                            d.clabeInterbancaria,
                            d.campoextra3,
                            d.tarjetafacil,
                            r.concepto,
                            r.monto_asignado
                        FROM ProrrateoRecursivo r
                        INNER JOIN DatosEmpleadoQ3 d
                            ON d.codigoempleado = r.codigoempleado
                    ) ' + '

                SELECT p.codigoempleado, p.nombre, p.rfc, p.claveBanco, p.cuentaPagoElectronico, p.clabeInterbancaria, p.campoextra3, p.tarjetafacil, ' + @cols + '
                FROM ProrrateoFinal
                PIVOT (
                    SUM(monto_asignado)
                    FOR concepto IN (' + @cols + ')
                ) p
                ORDER BY p.codigoempleado;
                ';

                EXEC(@sql);
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

    private function detalle_asimilados(Request $request)
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
                        ('Asimilados', 'P', 10, 0)
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

                ;WITH
                    MovimientosRetro AS (
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
                            AND emp.TipoRegimen IN (''05'', ''06'', ''07'', ''08'', ''09'', ''10'', ''11'')
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
                                emp.ccampoextranumerico3 AS pension,
                                emp.nombre + emp.apellidopaterno + emp.apellidomaterno AS nombre,
                                emp.bancopagoelectronico AS claveBanco,
                                emp.cuentapagoelectronico AS cuentaPagoElectronico,
                                emp.ClabeInterbancaria AS clabeInterbancaria,
                                ISNULL(CAST(emp.campoextra3 AS NVARCHAR(50)), '''') AS campoextra3,
                                ISNULL(CAST(emp.ccampoextranumerico3 AS NVARCHAR(50)), '''') AS tarjetafacil,
                                emp.rfc +
                                SUBSTRING(CONVERT(char(10), emp.fechanacimiento, 126), 3, 2) +
                                SUBSTRING(CONVERT(char(10), emp.fechanacimiento, 126), 6, 2) +
                                SUBSTRING(CONVERT(char(10), emp.fechanacimiento, 126), 9, 2) +
                                emp.homoclave as rfc
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
                                                AND pdo.numeroconcepto IN (19, 20, 16)
                                                OR pdo.descripcion LIKE ''%asimilados%''
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
                                                    OR pdo.numeroconcepto IN (52,35,45) -- Imss, isr
                                                    )
                                            )
                                        )
                            WHERE ' + '
                                empPeriodo.idtipoperiodo = @idTipoPeriodo
                                AND empPeriodo.estadoempleado IN (''A'', ''R'')
                                AND emp.TipoRegimen IN (''05'', ''06'', ''07'', ''08'', ''09'', ''10'', ''11'')
                                AND empPeriodo.idempleado BETWEEN @idEmpleadoInicial AND @idEmpleadoFinal

                            GROUP BY
                                emp.codigoempleado,
                                empPeriodo.sueldodiario,
                                empPeriodo.sueldointegrado,
                                pdo.descripcion,
                                pdo.tipoconcepto,
                                emp.rfc,
                                emp.fechanacimiento,
                                emp.homoclave,
                                emp.nombre,
                                emp.apellidopaterno,
                                emp.apellidomaterno,
                                emp.bancopagoelectronico,
                                emp.cuentapagoelectronico,
                                emp.ClabeInterbancaria,
                                emp.campoextra3,
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
                            nombre,
                            rfc,
                            claveBanco,
                            cuentaPagoElectronico,
                            clabeInterbancaria,
                            campoextra3,
                            tarjetafacil,
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
                            sd,
                            nombre,
                            rfc,
                            claveBanco,
                            cuentaPagoElectronico,
                            clabeInterbancaria,
                            campoextra3,
                            tarjetafacil
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
                    DatosEmpleadoQ3 AS (
                        SELECT
                            codigoempleado,
                            MAX(nombre)  AS nombre,
                            MAX(rfc)     AS rfc,
                            MAX(claveBanco)  AS claveBanco,
                            MAX(cuentaPagoElectronico)  AS cuentaPagoElectronico,
                            MAX(clabeInterbancaria)  AS clabeInterbancaria,
                            MAX(campoextra3) AS campoextra3,
                            MAX(tarjetafacil) AS tarjetafacil
                        FROM TotalesPorEmpleadoQ3
                        GROUP BY
                            codigoempleado
                    ),
                    ProrrateoFinal AS (
                        SELECT
                            codigoempleado,
                            nombre,
                            rfc,
                            claveBanco,
                            cuentaPagoElectronico,
                            clabeInterbancaria,
                            campoextra3,
                            tarjetafacil,
                            ''Sueldo IMSS'' AS concepto,
                            (total_percepciones - total_deducciones) AS monto_asignado
                        FROM TotalesPorEmpleadoQ3

                        UNION ALL
                        -- conceptos del prorrateo (columnas dinámicas)
                        SELECT
                            r.codigoempleado,
                            d.nombre,
                            d.rfc,
                            d.claveBanco,
                            d.cuentaPagoElectronico,
                            d.clabeInterbancaria,
                            d.campoextra3,
                            d.tarjetafacil,
                            r.concepto,
                            r.monto_asignado
                        FROM ProrrateoRecursivo r
                        INNER JOIN DatosEmpleadoQ3 d
                            ON d.codigoempleado = r.codigoempleado
                    ) ' + '

                SELECT p.codigoempleado, p.nombre, p.rfc, p.claveBanco, p.cuentaPagoElectronico, p.clabeInterbancaria, p.campoextra3, p.tarjetafacil, ' + @cols + '
                FROM ProrrateoFinal
                PIVOT (
                    SUM(monto_asignado)
                    FOR concepto IN (' + @cols + ')
                ) p
                ORDER BY p.codigoempleado;
                ';

                EXEC(@sql);
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

    private function detalle_asimilados2(Request $request)
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
}
