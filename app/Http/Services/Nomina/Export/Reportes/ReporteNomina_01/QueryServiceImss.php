<?php

namespace App\Http\Services\Nomina\Export\Reportes\ReporteNomina_01;

use Illuminate\Http\Request;

use App\Http\Services\Core\HelperService;

use Illuminate\Support\Facades\DB;

class QueryServiceImss
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
    public function getData(string $queryName, array $request)
    {

        if (!method_exists($this, $queryName)) {
            throw new \Exception("La consulta '$queryName' no está definida en QueryServiceImss.");
        }

        return $this->{$queryName}($request);
    }

    private function data_01(array $data)
    {

        try {

            $idNominaGapeCliente = $data['id_nomina_gape_cliente'];
            $idNominaGapeEmpresa = $data['id_nomina_gape_empresa'];

            $idTipoPeriodo = $data['idtipoperiodo'];
            $idPeriodo = $data['id_periodo'];

            $idNominaGapeParametrizacion = $data['id_nomina_gape_parametrizacion'];

            $idEsquemaCombinacion = $data['id_nomina_gape_combinacion'];

            $sql = "
                -- FISCAL 01
                DECLARE @cols NVARCHAR(MAX),
                    @sql  NVARCHAR(MAX);
                ;WITH
                Obligaciones AS (
                    SELECT
                        descripcion,
                        10 AS orden
                    FROM nom10004
                    WHERE
                        numeroconcepto IN (4002, 8000, 8001, 8002)
                ),
                titulos AS (
                    SELECT * FROM (VALUES
                        ('Total Empleados', 1),
                        ('Salarios Brutos', 2),
                        ('Sindicato', 3),
                        ('Asimilados Neto', 4),
                        ('Asimilados Bruto', 5),
                        ('Tarjeta facil', 6),
                        ('Gastos por comprobar', 7),
                        ('Base de facturacion', 8),

                        ('ISN', 10),

                        ('Total de cuotas patronales', 20),
                        ('Fee', 30),

                        ('Subtotal', 31),
                        ('IVA', 32),
                        ('Total Factura', 40)
                    ) AS X(descripcion, orden)
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

                DECLARE @idNominaGapeParametrizacion INT;

                DECLARE @idEsquemaCombinacion INT;

                DECLARE @idPeriodo INT;
                DECLARE @idTipoPeriodo INT;

                SET @idNominaGapeCliente = $idNominaGapeCliente;
                SET @idNominaGapeEmpresa = $idNominaGapeEmpresa;

                SET @idNominaGapeParametrizacion = $idNominaGapeParametrizacion;

                SET @idEsquemaCombinacion = $idEsquemaCombinacion;

                SET @idPeriodo = $idPeriodo;
                SET @idTipoPeriodo = $idTipoPeriodo;

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
                    SELECT idempleado, idperiodo, valor, idtarjetaincapacidad, ti.mnemonico AS mnemonico, ti.TipoImss AS TipoImss
                    FROM nom10009 AS fi
                    INNER JOIN nom10022 ti
                        ON fi.idtipoincidencia = ti.idtipoincidencia
                    WHERE
                        ti.TipoImss IN (''I'', ''A'')
                        AND idperiodo = @idPeriodo
                    UNION ALL
                    SELECT idempleado, idperiodo, valor, idtarjetaincapacidad, ti.mnemonico AS mnemonico, ti.TipoImss AS TipoImss
                    FROM NOM10010 AS fi
                    INNER JOIN nom10022 ti
                        ON fi.idtipoincidencia = ti.idtipoincidencia
                    WHERE
                        ti.TipoImss IN (''I'', ''A'')
                        AND idperiodo = @idPeriodo
                ), ' + '
                TarjetaControlAgrupado AS (
                    SELECT
                        idempleado,
                        SUM(CASE WHEN TipoImss IN (''I'') THEN valor ELSE 0 END) AS incapacidad,
                        SUM(CASE WHEN TipoImss IN (''A'') THEN valor ELSE 0 END) AS faltas
                    FROM tarjetaControl
                    GROUP BY
                        idempleado
                ), ' + '
                Movimientos AS (
                    SELECT idempleado, idperiodo, his.idconcepto, valor, importetotal, con.descripcion, con.tipoconcepto, con.numeroconcepto, con.imprimir, con.ClaveAgrupadoraSAT
                    FROM Nom10007 AS his
                    INNER JOIN nom10004 con
                        ON his.idconcepto = con.idconcepto
                    WHERE
                        importetotal > 0
                        AND idperiodo = @idPeriodo
                        AND con.tipoconcepto IN (''P'',''D'', ''O'')
                    UNION ALL
                    SELECT idempleado, idperiodo, actual.idconcepto, valor, importetotal, con.descripcion, con.tipoconcepto, con.numeroconcepto, con.imprimir, con.ClaveAgrupadoraSAT
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
                                    WHEN empPeriodo.fechaalta <= periodo.fechainicio THEN
                                        periodo.diasdepago
                                        + ISNULL(SUM(movP.valor), 0)
                                        - ISNULL(tc.incapacidad, 0)
                                        - ISNULL(tc.faltas, 0)

                                    WHEN empPeriodo.fechaalta > periodo.fechainicio AND empPeriodo.fechaalta <= periodo.fechafin THEN
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
                        CASE
                            WHEN pdo.numeroconcepto = 20
                                AND empPeriodo.sueldodiario <> 0
                            THEN
                                SUM(pdo.importetotal) * (emp.ccampoextranumerico2 / empPeriodo.sueldodiario)
                            ELSE
                                SUM(pdo.importetotal)
                        END AS monto,
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
                    WHERE ' + '
                        empPeriodo.idtipoperiodo = @idTipoPeriodo
                        AND empPeriodo.estadoempleado IN (''A'', ''R'')
                        AND emp.TipoRegimen IN (''02'', ''03'', ''04'')

                    GROUP BY
                        emp.codigoempleado,
                        emp.ccampoextranumerico2,
                        empPeriodo.cdiaspagados,
                        pdo.descripcion,
                        pdo.tipoconcepto,
                        pdo.numeroconcepto,
                        empPeriodo.sueldodiario,
                        emp.ccampoextranumerico3,
                        periodo.diasdepago,
                        empPeriodo.fechaalta,
                        periodo.fechainicio,
                        periodo.fechafin,
                        tc.faltas,
                        tc.incapacidad
                ), ' + '
                IncidenciaMasReciente AS (
                    SELECT TOP 1
                        ngi.id
                    FROM [becma-core2].dbo.nomina_gape_incidencia AS ngi
                    WHERE
                        ngi.id_nomina_gape_cliente = @idNominaGapeCliente
                        AND ngi.id_nomina_gape_empresa = @idNominaGapeEmpresa
                        AND ngi.id_tipo_periodo = @idTipoPeriodo
                        AND ngi.id_periodo = @idPeriodo
                    ORDER BY
                        ngi.created_at DESC,
                        ngi.id DESC
                ), ' + '
                IncidenciasNormalizadasQ2 AS (
                    SELECT
                        emp.codigoempleado as codigoempleado,
                        x.descripcion AS descripcion,
                        x.tipoConcepto AS tipoConcepto,
                        x.valor AS valor,
                        emp.ccampoextranumerico2 AS pagoPorDia,
                        emp.ccampoextranumerico3 AS pension
                    FROM IncidenciaMasReciente imr
                    INNER JOIN [becma-core2].dbo.nomina_gape_incidencia_detalle AS ngid
                        ON imr.id = ngid.id_nomina_gape_incidencia
                    INNER JOIN nom10034 AS empP
                        ON ngid.id_empleado = empP.idempleado
                            AND empP.cidperiodo = @idPeriodo
                            AND empP.idtipoperiodo = @idTipoPeriodo
                            AND empP.estadoempleado IN (''A'', ''R'')
                    INNER JOIN nom10001 AS emp
                        ON empP.idempleado = emp.idempleado
                    CROSS APPLY (VALUES
                        (''Bono'',                      ngid.bono,                      ''P''),
                        (''Comisiones'',                ngid.comision,                  ''P''),
                        (''Dia Festivo cantidad'',      ngid.cantidad_dias_festivos,    ''P''),
                        (''Dia Festivo monto'',         ISNULL(ngid.cantidad_dias_festivos, 0) * emp.ccampoextranumerico2, ''P''),
                        (''Horas Extra Doble cantidad'', ngid.horas_extra_doble_cantidad, ''P''),
                        (''Horas Extra Doble monto'',    ISNULL(ngid.horas_extra_doble_cantidad, 0) * (emp.ccampoextranumerico2 / 8.0) * 2, ''P''),
                        (''Horas Extra Triple cantidad'', ngid.horas_extra_triple_cantidad, ''P''),
                        (''Horas Extra Triple monto'',    ISNULL(ngid.horas_extra_triple_cantidad, 0) * (emp.ccampoextranumerico2 / 8.0) * 3, ''P''),
                        (''Pago adicional'',             ngid.pago_adicional, ''P''),
                        (''Premio puntualidad'',         ngid.premio_puntualidad, ''P''),
                        (''Prima Dominical cantidad'',   ngid.cantidad_prima_dominical, ''P''),
                        (''Prima Dominical monto'',      ISNULL(ngid.cantidad_prima_dominical, 0) * emp.ccampoextranumerico2 * 0.25, ''P''),
                        (''Descuentos'',                 ngid.descuento, ''D'')
                    ) AS x(descripcion, valor, tipoConcepto)
                    WHERE
                        emp.TipoRegimen IN (''02'', ''03'', ''04'')
                ), ' + '
                PreTotalesPorEmpleadoGeneralQ2 AS (
                    SELECT
                        codigoempleado,
                        MAX(sueldo) AS sueldo,
                        SUM(
                            CASE
                                WHEN tipoConcepto = ''P''
                                THEN ISNULL(monto,0) + ISNULL(valor,0)
                                ELSE 0
                            END
                        ) AS total_percepciones_sin_sueldo,
                        SUM(
                            CASE
                                WHEN tipoConcepto = ''D''
                                THEN ISNULL(monto,0) + ISNULL(valor,0)
                                ELSE 0
                            END
                        ) AS total_deducciones,
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
                            tipoConcepto AS tipoConcepto,
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
                        AND ngepcp.id = @idNominaGapeParametrizacion
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
                                (
                                    pdo.tipoconcepto = ''P''
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
                                        pdo.imprimir = 1
                                        AND pdo.ClaveAgrupadoraSAT != ''''
                                    )
                                )
                            )
                    WHERE
                        empPeriodo.idtipoperiodo = @idTipoPeriodo
                        AND empPeriodo.estadoempleado IN (''A'', ''R'')
                        AND emp.TipoRegimen IN (''02'', ''03'', ''04'')
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
                        q3.codigoempleado,
                        ISNULL((pe.total_percepciones_excedente - de.total_deducciones_excedente), 0) AS neto_excedente
                    FROM TotalesPorEmpleadoQ3 q3
                    LEFT JOIN PercepcionesExcedentes pe
                        ON q3.codigoempleado = pe.codigoempleado
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
                MovimientosObligaciones AS (
                    SELECT
                        emp.codigoempleado AS codigoempleado,
                        CASE WHEN pdo.numeroconcepto = ''90'' THEN ''ISN'' ELSE pdo.descripcion END AS concepto,
                        ''O'' AS tipoConcepto,
                        pdo.importetotal AS monto
                    FROM nom10001 emp
                    INNER JOIN nom10034 empPeriodo
                        ON emp.idempleado = empPeriodo.idempleado AND empPeriodo.cidperiodo = @idPeriodo
                    LEFT JOIN Movimientos pdo
                        ON empPeriodo.cidperiodo = pdo.idperiodo
                            AND emp.idempleado = pdo.idempleado
                            AND pdo.tipoconcepto = ''O''
                            AND pdo.numeroconcepto IN (90, 4002, 8000, 8001, 8002)
                    WHERE
                        empPeriodo.idtipoperiodo = @idTipoPeriodo
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
                CostoTotal AS (
                    SELECT
                        q3.codigoempleado,
                        ''Compensacion'' as concepto,
                        (q3.total_percepciones + ISNULL(exce.neto_excedente, 0)) AS monto
                    FROM TotalesPorEmpleadoQ3 AS q3
                    LEFT JOIN NetoExcedentes AS exce
                        ON q3.codigoempleado = exce.codigoempleado

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
                        rf.percepcion_bruta * (nge.fee / 100.0) AS fee,
                        rf.percepcion_bruta * (1 + (nge.fee / 100.0)) AS subtotal,
                        rf.percepcion_bruta * (1 + (nge.fee / 100.0)) * 0.16 AS iva,
                        rf.percepcion_bruta * (1 + (nge.fee / 100.0)) * 1.16 AS total
                    FROM ResumenFinal rf
                    CROSS JOIN nomGapeEmpresa nge
                ), ' + '
                preBaseFacturacion AS (
                    SELECT
                        ''Salarios Brutos'' AS concepto,
                        SUM(total_percepciones) AS monto
                    FROM TotalesPorEmpleadoQ3
                    UNION ALL
                    SELECT
                        concepto,
                        SUM(monto_asignado)
                    FROM ProrrateoRecursivo
                    GROUP BY
                        concepto
                ), ' + '
                baseFacturacion AS (
                    SELECT
                        ''Base de facturacion'' AS concepto,
                        SUM(monto) AS monto
                    FROM preBaseFacturacion
                ), ' + '
                totalCuotaPatronal AS (
                    SELECT
                        ''Total de cuotas patronales'' AS concepto,
                        SUM(monto) AS monto
                    FROM MovimientosObligaciones
                ), ' + '
                totalEmpleados AS (
                    SELECT
                        ''Total Empleados'' AS concepto,
                        COUNT(DISTINCT codigoempleado) AS monto
                    FROM TotalesPorEmpleadoQ3
                ),
                columnasGeneral AS (
                    SELECT
                        concepto,
                        monto
                    FROM totalEmpleados
                    UNION ALL
                    SELECT
                        concepto,
                        monto
                    FROM preBaseFacturacion
                    UNION ALL
                    SELECT
                        concepto,
                        monto
                    FROM baseFacturacion
                    UNION ALL
                    SELECT
                        concepto,
                        SUM(monto)
                    FROM MovimientosObligaciones
                    GROUP BY
                        concepto
                    UNION ALL
                    ' + '
                    SELECT
                        concepto,
                        monto
                    FROM totalCuotaPatronal

                    UNION ALL
                    SELECT v.concepto, v.monto
                    FROM TotalesCalculados tc
                    CROSS APPLY (VALUES
                        (''Fee'', tc.fee),
                        (''Subtotal'', tc.subtotal),
                        (''IVA'', tc.iva),
                        (''Total Factura'', tc.total)
                    ) v(concepto, monto)
                ) ' + '
                SELECT ' + @cols + '
                FROM columnasGeneral
                PIVOT (
                    SUM(monto)
                    FOR concepto IN (' + @cols + ')
                ) p;
                ';

                EXEC sp_executesql @sql;

            ";


            /*
            dd([
                'row' => $sql,
            ]);

            */


            $result = collect(DB::connection('sqlsrv_dynamic')->select($sql))->first();

            return $result;
        } catch (\Exception $e) {
            return [];
        }
    }

    private function data_02(array $data)
    {
        try {

            $idNominaGapeCliente = $data['id_nomina_gape_cliente'];
            $idNominaGapeEmpresa = $data['id_nomina_gape_empresa'];

            $idTipoPeriodo = $data['id_tipo_periodo'];
            $idPeriodo = $data['id_periodo'];

            $idNominaGapeParametrizacion = $data['id_nomina_gape_parametrizacion'];

            $idEsquemaCombinacion = $data['id_nomina_gape_combinacion'];

            $sql = "
                -- FISCAL 02
                DECLARE @cols NVARCHAR(MAX),
                    @sql  NVARCHAR(MAX);
                ;WITH
                Obligaciones AS (
                    SELECT
                        descripcion,
                        10 AS orden
                    FROM nom10004
                    WHERE
                        numeroconcepto IN (4002, 8000, 8001, 8002)
                ),
                titulos AS (
                    SELECT * FROM (VALUES
                        ('Total Empleados', 1),
                        ('Salarios Brutos', 2),
                        ('Sindicato', 3),
                        ('Asimilados Neto', 4),
                        ('Asimilados Bruto', 5),
                        ('Tarjeta facil', 6),
                        ('Gastos por comprobar', 7),
                        ('Base de facturacion', 8),

                        ('ISN', 10),

                        ('Total de cuotas patronales', 20),
                        ('Fee', 30),

                        ('Subtotal', 31),
                        ('IVA', 32),
                        ('Total Factura', 40)
                    ) AS X(descripcion, orden)
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

                DECLARE @idNominaGapeParametrizacion INT;
                DECLARE @idEsquemaCombinacion INT;

                DECLARE @idPeriodo INT;
                DECLARE @idTipoPeriodo INT;

                SET @idNominaGapeCliente = $idNominaGapeCliente;
                SET @idNominaGapeEmpresa = $idNominaGapeEmpresa;

                SET @idNominaGapeParametrizacion = $idNominaGapeParametrizacion;
                SET @idEsquemaCombinacion = $idEsquemaCombinacion;

                SET @idPeriodo = $idPeriodo;
                SET @idTipoPeriodo = $idTipoPeriodo;

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
                    SELECT idempleado, idperiodo, valor, idtarjetaincapacidad, ti.mnemonico AS mnemonico, ti.TipoImss AS TipoImss
                    FROM nom10009 AS fi
                    INNER JOIN nom10022 ti
                        ON fi.idtipoincidencia = ti.idtipoincidencia
                    WHERE
                        ti.TipoImss IN (''I'', ''A'')
                        AND idperiodo = @idPeriodo
                    UNION ALL
                    SELECT idempleado, idperiodo, valor, idtarjetaincapacidad, ti.mnemonico AS mnemonico, ti.TipoImss AS TipoImss
                    FROM NOM10010 AS fi
                    INNER JOIN nom10022 ti
                        ON fi.idtipoincidencia = ti.idtipoincidencia
                    WHERE
                        ti.TipoImss IN (''I'', ''A'')
                        AND idperiodo = @idPeriodo
                ), ' + '
                TarjetaControlAgrupado AS (
                    SELECT
                        idempleado,
                        SUM(CASE WHEN TipoImss IN (''I'') THEN valor ELSE 0 END) AS incapacidad,
                        SUM(CASE WHEN TipoImss IN (''A'') THEN valor ELSE 0 END) AS faltas
                    FROM tarjetaControl
                    GROUP BY
                        idempleado
                ), ' + '
                Movimientos AS (
                    SELECT idempleado, idperiodo, his.idconcepto, valor, importetotal, con.descripcion, con.tipoconcepto, con.numeroconcepto, con.imprimir, con.ClaveAgrupadoraSAT
                    FROM Nom10007 AS his
                    INNER JOIN nom10004 con
                        ON his.idconcepto = con.idconcepto
                    WHERE
                        importetotal > 0
                        AND idperiodo = @idPeriodo
                        AND con.tipoconcepto IN (''P'',''D'', ''O'')
                    UNION ALL
                    SELECT idempleado, idperiodo, actual.idconcepto, valor, importetotal, con.descripcion, con.tipoconcepto, con.numeroconcepto, con.imprimir, con.ClaveAgrupadoraSAT
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
                                    WHEN empPeriodo.fechaalta <= periodo.fechainicio THEN
                                        periodo.diasdepago
                                        + ISNULL(SUM(movP.valor), 0)
                                        - ISNULL(tc.incapacidad, 0)
                                        - ISNULL(tc.faltas, 0)

                                    WHEN empPeriodo.fechaalta > periodo.fechainicio AND empPeriodo.fechaalta <= periodo.fechafin THEN
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
                        CASE
                            WHEN pdo.numeroconcepto = 20
                                AND empPeriodo.sueldodiario <> 0
                            THEN
                                SUM(pdo.importetotal) * (emp.ccampoextranumerico2 / empPeriodo.sueldodiario)
                            ELSE
                                SUM(pdo.importetotal)
                        END AS monto,
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
                    WHERE ' + '
                        empPeriodo.idtipoperiodo = @idTipoPeriodo
                        AND empPeriodo.estadoempleado IN (''A'', ''R'')
                        AND emp.TipoRegimen IN (''02'', ''03'', ''04'')

                    GROUP BY
                        emp.codigoempleado,
                        emp.ccampoextranumerico2,
                        empPeriodo.cdiaspagados,
                        pdo.descripcion,
                        pdo.tipoconcepto,
                        pdo.numeroconcepto,
                        empPeriodo.sueldodiario,
                        emp.ccampoextranumerico3,
                        periodo.diasdepago,
                        empPeriodo.fechaalta,
                        periodo.fechainicio,
                        periodo.fechafin,
                        tc.faltas,
                        tc.incapacidad
                ), ' + '
                IncidenciaMasReciente AS (
                    SELECT TOP 1
                        ngi.id
                    FROM [becma-core2].dbo.nomina_gape_incidencia AS ngi
                    WHERE
                        ngi.id_nomina_gape_cliente = @idNominaGapeCliente
                        AND ngi.id_nomina_gape_empresa = @idNominaGapeEmpresa
                        AND ngi.id_tipo_periodo = @idTipoPeriodo
                        AND ngi.id_periodo = @idPeriodo
                    ORDER BY
                        ngi.created_at DESC,
                        ngi.id DESC
                ), ' + '
                IncidenciasNormalizadasQ2 AS (
                    SELECT
                        emp.codigoempleado as codigoempleado,
                        x.descripcion AS descripcion,
                        x.tipoConcepto AS tipoConcepto,
                        x.valor AS valor,
                        emp.ccampoextranumerico2 AS pagoPorDia,
                        emp.ccampoextranumerico3 AS pension
                    FROM IncidenciaMasReciente imr
                    INNER JOIN [becma-core2].dbo.nomina_gape_incidencia_detalle AS ngid
                        ON imr.id = ngid.id_nomina_gape_incidencia
                    INNER JOIN nom10034 AS empP
                        ON ngid.id_empleado = empP.idempleado
                            AND empP.cidperiodo = @idPeriodo
                            AND empP.idtipoperiodo = @idTipoPeriodo
                            AND empP.estadoempleado IN (''A'', ''R'')
                    INNER JOIN nom10001 AS emp
                        ON empP.idempleado = emp.idempleado
                    CROSS APPLY (VALUES
                        (''Bono'',                      ngid.bono,                      ''P''),
                        (''Comisiones'',                ngid.comision,                  ''P''),
                        (''Dia Festivo cantidad'',      ngid.cantidad_dias_festivos,    ''P''),
                        (''Dia Festivo monto'',         ISNULL(ngid.cantidad_dias_festivos, 0) * emp.ccampoextranumerico2, ''P''),
                        (''Horas Extra Doble cantidad'', ngid.horas_extra_doble_cantidad, ''P''),
                        (''Horas Extra Doble monto'',    ISNULL(ngid.horas_extra_doble_cantidad, 0) * (emp.ccampoextranumerico2 / 8.0) * 2, ''P''),
                        (''Horas Extra Triple cantidad'', ngid.horas_extra_triple_cantidad, ''P''),
                        (''Horas Extra Triple monto'',    ISNULL(ngid.horas_extra_triple_cantidad, 0) * (emp.ccampoextranumerico2 / 8.0) * 3, ''P''),
                        (''Pago adicional'',             ngid.pago_adicional, ''P''),
                        (''Premio puntualidad'',         ngid.premio_puntualidad, ''P''),
                        (''Prima Dominical cantidad'',   ngid.cantidad_prima_dominical, ''P''),
                        (''Prima Dominical monto'',      ISNULL(ngid.cantidad_prima_dominical, 0) * emp.ccampoextranumerico2 * 0.25, ''P''),
                        (''Descuentos'',                 ngid.descuento, ''D'')
                    ) AS x(descripcion, valor, tipoConcepto)
                    WHERE
                        emp.TipoRegimen IN (''02'', ''03'', ''04'')
                ), ' + '
                PreTotalesPorEmpleadoGeneralQ2 AS (
                    SELECT
                        codigoempleado,
                        MAX(sueldo) AS sueldo,
                        SUM(
                            CASE
                                WHEN tipoConcepto = ''P''
                                THEN ISNULL(monto,0) + ISNULL(valor,0)
                                ELSE 0
                            END
                        ) AS total_percepciones_sin_sueldo,
                        SUM(
                            CASE
                                WHEN tipoConcepto = ''D''
                                THEN ISNULL(monto,0) + ISNULL(valor,0)
                                ELSE 0
                            END
                        ) AS total_deducciones,
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
                            tipoConcepto AS tipoConcepto,
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
                        AND ngepcp.id = @idNominaGapeParametrizacion
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
                                (
                                    pdo.tipoconcepto = ''P''
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
                                        pdo.imprimir = 1
                                        AND pdo.ClaveAgrupadoraSAT != ''''
                                    )
                                )
                            )
                    WHERE
                        empPeriodo.idtipoperiodo = @idTipoPeriodo
                        AND empPeriodo.estadoempleado IN (''A'', ''R'')
                        AND emp.TipoRegimen IN (''02'', ''03'', ''04'')
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
                        q3.codigoempleado,
                        ISNULL((pe.total_percepciones_excedente - de.total_deducciones_excedente), 0) AS neto_excedente
                    FROM TotalesPorEmpleadoQ3 q3
                    LEFT JOIN PercepcionesExcedentes pe
                        ON q3.codigoempleado = pe.codigoempleado
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
                MovimientosObligaciones AS (
                    SELECT
                        emp.codigoempleado AS codigoempleado,
                        CASE WHEN pdo.numeroconcepto = ''90'' THEN ''ISN'' ELSE pdo.descripcion END AS concepto,
                        ''O'' AS tipoConcepto,
                        pdo.importetotal AS monto
                    FROM nom10001 emp
                    INNER JOIN nom10034 empPeriodo
                        ON emp.idempleado = empPeriodo.idempleado AND empPeriodo.cidperiodo = @idPeriodo
                    LEFT JOIN Movimientos pdo
                        ON empPeriodo.cidperiodo = pdo.idperiodo
                            AND emp.idempleado = pdo.idempleado
                            AND pdo.tipoconcepto = ''O''
                            AND pdo.numeroconcepto IN (90, 4002, 8000, 8001, 8002)
                    WHERE
                        empPeriodo.idtipoperiodo = @idTipoPeriodo
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
                CostoTotal AS (
                    SELECT
                        q3.codigoempleado,
                        ''Compensacion'' as concepto,
                        (q3.total_percepciones + ISNULL(exce.neto_excedente, 0)) AS monto
                    FROM TotalesPorEmpleadoQ3 AS q3
                    LEFT JOIN NetoExcedentes AS exce
                        ON q3.codigoempleado = exce.codigoempleado

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
                        (rf.percepcion_bruta + rf.costo_social) * (nge.fee / 100.0) AS fee,
                        (rf.percepcion_bruta + rf.costo_social) * (1 + (nge.fee / 100.0)) AS subtotal,
                        (rf.percepcion_bruta + rf.costo_social) * (1 + (nge.fee / 100.0)) * 0.16 AS iva,
                        (rf.percepcion_bruta + rf.costo_social) * (1 + (nge.fee / 100.0)) * 1.16 AS total
                    FROM ResumenFinal rf
                    CROSS JOIN nomGapeEmpresa nge
                ), ' + '
                preBaseFacturacion AS (
                    SELECT
                        ''Salarios Brutos'' AS concepto,
                        SUM(total_percepciones) AS monto
                    FROM TotalesPorEmpleadoQ3
                    UNION ALL
                    SELECT
                        concepto,
                        SUM(monto_asignado)
                    FROM ProrrateoRecursivo
                    GROUP BY
                        concepto
                ), ' + '
                baseFacturacion AS (
                    SELECT
                        ''Base de facturacion'' AS concepto,
                        SUM(monto) AS monto
                    FROM preBaseFacturacion
                ), ' + '
                totalCuotaPatronal AS (
                    SELECT
                        ''Total de cuotas patronales'' AS concepto,
                        SUM(monto) AS monto
                    FROM MovimientosObligaciones
                ), ' + '
                totalEmpleados AS (
                    SELECT
                        ''Total Empleados'' AS concepto,
                        COUNT(DISTINCT codigoempleado) AS monto
                    FROM TotalesPorEmpleadoQ3
                ),
                columnasGeneral AS (
                    SELECT
                        concepto,
                        monto
                    FROM totalEmpleados
                    UNION ALL
                    SELECT
                        concepto,
                        monto
                    FROM preBaseFacturacion
                    UNION ALL
                    SELECT
                        concepto,
                        monto
                    FROM baseFacturacion
                    UNION ALL
                    SELECT
                        concepto,
                        SUM(monto)
                    FROM MovimientosObligaciones
                    GROUP BY
                        concepto
                    UNION ALL
                    ' + '
                    SELECT
                        concepto,
                        monto
                    FROM totalCuotaPatronal

                    UNION ALL
                    SELECT v.concepto, v.monto
                    FROM TotalesCalculados tc
                    CROSS APPLY (VALUES
                        (''Fee'', tc.fee),
                        (''Subtotal'', tc.subtotal),
                        (''IVA'', tc.iva),
                        (''Total Factura'', tc.total)
                    ) v(concepto, monto)
                ) ' + '
                SELECT ' + @cols + '
                FROM columnasGeneral
                PIVOT (
                    SUM(monto)
                    FOR concepto IN (' + @cols + ')
                ) p;
                ';

                EXEC sp_executesql @sql;

            ";

            $result = collect(DB::connection('sqlsrv_dynamic')->select($sql))->first();

            return $result;
        } catch (\Exception $e) {
            return [];
        }
    }

    private function data_03(array $data)
    {
        try {

            $idNominaGapeCliente = $data['id_nomina_gape_cliente'];
            $idNominaGapeEmpresa = $data['id_nomina_gape_empresa'];

            $idTipoPeriodo = $data['id_tipo_periodo'];
            $idPeriodo = $data['id_periodo'];

            $idNominaGapeParametrizacion = $data['id_nomina_gape_parametrizacion'];

            $idEsquemaCombinacion = $data['id_nomina_gape_combinacion'];

            $sql = "
                -- FISCAL 01
                DECLARE @cols NVARCHAR(MAX),
                    @sql  NVARCHAR(MAX);
                ;WITH
                Obligaciones AS (
                    SELECT
                        descripcion,
                        10 AS orden
                    FROM nom10004
                    WHERE
                        numeroconcepto IN (4002, 8000, 8001, 8002)
                ),
                titulos AS (
                    SELECT * FROM (VALUES
                        ('Total Empleados', 1),
                        ('Salarios Brutos', 2),
                        ('Sindicato', 3),
                        ('Asimilados Neto', 4),
                        ('Asimilados Bruto', 5),
                        ('Tarjeta facil', 6),
                        ('Gastos por comprobar', 7),
                        ('Base de facturacion', 8),
                        ('ISN', 10),
                        ('Total de cuotas patronales', 20),
                        ('Fee', 30),

                        ('Subtotal', 31),
                        ('IVA', 32),
                        ('Total Factura', 40)
                    ) AS X(descripcion, orden)
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

                DECLARE @idNominaGapeParametrizacion INT;
                DECLARE @idEsquemaCombinacion INT;

                DECLARE @idPeriodo INT;
                DECLARE @idTipoPeriodo INT;

                SET @idNominaGapeCliente = $idNominaGapeCliente;
                SET @idNominaGapeEmpresa = $idNominaGapeEmpresa;

                SET @idNominaGapeParametrizacion = $idNominaGapeParametrizacion;
                SET @idEsquemaCombinacion = $idEsquemaCombinacion;

                SET @idPeriodo = $idPeriodo;
                SET @idTipoPeriodo = $idTipoPeriodo;

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
                    SELECT idempleado, idperiodo, valor, idtarjetaincapacidad, ti.mnemonico AS mnemonico, ti.TipoImss AS TipoImss
                    FROM nom10009 AS fi
                    INNER JOIN nom10022 ti
                        ON fi.idtipoincidencia = ti.idtipoincidencia
                    WHERE
                        ti.TipoImss IN (''I'', ''A'')
                        AND idperiodo = @idPeriodo
                    UNION ALL
                    SELECT idempleado, idperiodo, valor, idtarjetaincapacidad, ti.mnemonico AS mnemonico, ti.TipoImss AS TipoImss
                    FROM NOM10010 AS fi
                    INNER JOIN nom10022 ti
                        ON fi.idtipoincidencia = ti.idtipoincidencia
                    WHERE
                        ti.TipoImss IN (''I'', ''A'')
                        AND idperiodo = @idPeriodo
                ), ' + '
                TarjetaControlAgrupado AS (
                    SELECT
                        idempleado,
                        SUM(CASE WHEN TipoImss IN (''I'') THEN valor ELSE 0 END) AS incapacidad,
                        SUM(CASE WHEN TipoImss IN (''A'') THEN valor ELSE 0 END) AS faltas
                    FROM tarjetaControl
                    GROUP BY
                        idempleado
                ), ' + '
                Movimientos AS (
                    SELECT idempleado, idperiodo, his.idconcepto, valor, importetotal, con.descripcion, con.tipoconcepto, con.numeroconcepto, con.imprimir, con.ClaveAgrupadoraSAT
                    FROM Nom10007 AS his
                    INNER JOIN nom10004 con
                        ON his.idconcepto = con.idconcepto
                    WHERE
                        importetotal > 0
                        AND idperiodo = @idPeriodo
                        AND con.tipoconcepto IN (''P'',''D'', ''O'')
                    UNION ALL
                    SELECT idempleado, idperiodo, actual.idconcepto, valor, importetotal, con.descripcion, con.tipoconcepto, con.numeroconcepto, con.imprimir, con.ClaveAgrupadoraSAT
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
                                    WHEN empPeriodo.fechaalta <= periodo.fechainicio THEN
                                        periodo.diasdepago
                                        + ISNULL(SUM(movP.valor), 0)
                                        - ISNULL(tc.incapacidad, 0)
                                        - ISNULL(tc.faltas, 0)

                                    WHEN empPeriodo.fechaalta > periodo.fechainicio AND empPeriodo.fechaalta <= periodo.fechafin THEN
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
                        CASE
                            WHEN pdo.numeroconcepto = 20
                                AND empPeriodo.sueldodiario <> 0
                            THEN
                                SUM(pdo.importetotal) * (emp.ccampoextranumerico2 / empPeriodo.sueldodiario)
                            ELSE
                                SUM(pdo.importetotal)
                        END AS monto,
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
                    WHERE ' + '
                        empPeriodo.idtipoperiodo = @idTipoPeriodo
                        AND empPeriodo.estadoempleado IN (''A'', ''R'')
                        AND emp.TipoRegimen IN (''02'', ''03'', ''04'')

                    GROUP BY
                        emp.codigoempleado,
                        emp.ccampoextranumerico2,
                        empPeriodo.cdiaspagados,
                        pdo.descripcion,
                        pdo.tipoconcepto,
                        pdo.numeroconcepto,
                        empPeriodo.sueldodiario,
                        emp.ccampoextranumerico3,
                        periodo.diasdepago,
                        empPeriodo.fechaalta,
                        periodo.fechainicio,
                        periodo.fechafin,
                        tc.faltas,
                        tc.incapacidad
                ), ' + '
                IncidenciaMasReciente AS (
                    SELECT TOP 1
                        ngi.id
                    FROM [becma-core2].dbo.nomina_gape_incidencia AS ngi
                    WHERE
                        ngi.id_nomina_gape_cliente = @idNominaGapeCliente
                        AND ngi.id_nomina_gape_empresa = @idNominaGapeEmpresa
                        AND ngi.id_tipo_periodo = @idTipoPeriodo
                        AND ngi.id_periodo = @idPeriodo
                    ORDER BY
                        ngi.created_at DESC,
                        ngi.id DESC
                ), ' + '
                IncidenciasNormalizadasQ2 AS (
                    SELECT
                        emp.codigoempleado as codigoempleado,
                        x.descripcion AS descripcion,
                        x.tipoConcepto AS tipoConcepto,
                        x.valor AS valor,
                        emp.ccampoextranumerico2 AS pagoPorDia,
                        emp.ccampoextranumerico3 AS pension
                    FROM IncidenciaMasReciente imr
                    INNER JOIN [becma-core2].dbo.nomina_gape_incidencia_detalle AS ngid
                        ON imr.id = ngid.id_nomina_gape_incidencia
                    INNER JOIN nom10034 AS empP
                        ON ngid.id_empleado = empP.idempleado
                            AND empP.cidperiodo = @idPeriodo
                            AND empP.idtipoperiodo = @idTipoPeriodo
                            AND empP.estadoempleado IN (''A'', ''R'')
                    INNER JOIN nom10001 AS emp
                        ON empP.idempleado = emp.idempleado
                    CROSS APPLY (VALUES
                        (''Bono'',                      ngid.bono,                      ''P''),
                        (''Comisiones'',                ngid.comision,                  ''P''),
                        (''Dia Festivo cantidad'',      ngid.cantidad_dias_festivos,    ''P''),
                        (''Dia Festivo monto'',         ISNULL(ngid.cantidad_dias_festivos, 0) * emp.ccampoextranumerico2, ''P''),
                        (''Horas Extra Doble cantidad'', ngid.horas_extra_doble_cantidad, ''P''),
                        (''Horas Extra Doble monto'',    ISNULL(ngid.horas_extra_doble_cantidad, 0) * (emp.ccampoextranumerico2 / 8.0) * 2, ''P''),
                        (''Horas Extra Triple cantidad'', ngid.horas_extra_triple_cantidad, ''P''),
                        (''Horas Extra Triple monto'',    ISNULL(ngid.horas_extra_triple_cantidad, 0) * (emp.ccampoextranumerico2 / 8.0) * 3, ''P''),
                        (''Pago adicional'',             ngid.pago_adicional, ''P''),
                        (''Premio puntualidad'',         ngid.premio_puntualidad, ''P''),
                        (''Prima Dominical cantidad'',   ngid.cantidad_prima_dominical, ''P''),
                        (''Prima Dominical monto'',      ISNULL(ngid.cantidad_prima_dominical, 0) * emp.ccampoextranumerico2 * 0.25, ''P''),
                        (''Descuentos'',                 ngid.descuento, ''D'')
                    ) AS x(descripcion, valor, tipoConcepto)
                    WHERE
                        emp.TipoRegimen IN (''02'', ''03'', ''04'')
                ), ' + '
                PreTotalesPorEmpleadoGeneralQ2 AS (
                    SELECT
                        codigoempleado,
                        MAX(sueldo) AS sueldo,
                        SUM(
                            CASE
                                WHEN tipoConcepto = ''P''
                                THEN ISNULL(monto,0) + ISNULL(valor,0)
                                ELSE 0
                            END
                        ) AS total_percepciones_sin_sueldo,
                        SUM(
                            CASE
                                WHEN tipoConcepto = ''D''
                                THEN ISNULL(monto,0) + ISNULL(valor,0)
                                ELSE 0
                            END
                        ) AS total_deducciones,
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
                            tipoConcepto AS tipoConcepto,
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
                        AND ngepcp.id = @idEsquemaCombinacion
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
                                (
                                    pdo.tipoconcepto = ''P''
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
                                        pdo.imprimir = 1
                                        AND pdo.ClaveAgrupadoraSAT != ''''
                                    )
                                )
                            )
                    WHERE
                        empPeriodo.idtipoperiodo = @idTipoPeriodo
                        AND empPeriodo.estadoempleado IN (''A'', ''R'')
                        AND emp.TipoRegimen IN (''02'', ''03'', ''04'')
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
                        q3.codigoempleado,
                        ISNULL((pe.total_percepciones_excedente - de.total_deducciones_excedente), 0) AS neto_excedente
                    FROM TotalesPorEmpleadoQ3 q3
                    LEFT JOIN PercepcionesExcedentes pe
                        ON q3.codigoempleado = pe.codigoempleado
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
                MovimientosObligaciones AS (
                    SELECT
                        emp.codigoempleado AS codigoempleado,
                        CASE WHEN pdo.numeroconcepto = ''90'' THEN ''ISN'' ELSE pdo.descripcion END AS concepto,
                        ''O'' AS tipoConcepto,
                        pdo.importetotal AS monto
                    FROM nom10001 emp
                    INNER JOIN nom10034 empPeriodo
                        ON emp.idempleado = empPeriodo.idempleado AND empPeriodo.cidperiodo = @idPeriodo
                    LEFT JOIN Movimientos pdo
                        ON empPeriodo.cidperiodo = pdo.idperiodo
                            AND emp.idempleado = pdo.idempleado
                            AND pdo.tipoconcepto = ''O''
                            AND pdo.numeroconcepto IN (90, 4002, 8000, 8001, 8002)
                    WHERE
                        empPeriodo.idtipoperiodo = @idTipoPeriodo
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
                CostoTotal AS (
                    SELECT
                        codigoempleado,
                        ''Compensacion'' as concepto,
                        neto_total_a_pagar AS monto
                    FROM NetoTotalAPagar
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
                        rf.percepcion_bruta * (nge.fee / 100.0) AS fee,
                        rf.percepcion_bruta * (1 + (nge.fee / 100.0)) AS subtotal,
                        rf.percepcion_bruta * (1 + (nge.fee / 100.0)) * 0.16 AS iva,
                        rf.percepcion_bruta * (1 + (nge.fee / 100.0)) * 1.16 AS total
                    FROM ResumenFinal rf
                    CROSS JOIN nomGapeEmpresa nge
                ), ' + '
                preBaseFacturacion AS (
                    SELECT
                        ''Salarios Brutos'' AS concepto,
                        SUM(total_percepciones) AS monto
                    FROM TotalesPorEmpleadoQ3
                    UNION ALL
                    SELECT
                        concepto,
                        SUM(monto_asignado)
                    FROM ProrrateoRecursivo
                    GROUP BY
                        concepto
                ), ' + '
                baseFacturacion AS (
                    SELECT
                        ''Base de facturacion'' AS concepto,
                        SUM(monto) AS monto
                    FROM preBaseFacturacion
                ), ' + '
                totalCuotaPatronal AS (
                    SELECT
                        ''Total de cuotas patronales'' AS concepto,
                        SUM(monto) AS monto
                    FROM MovimientosObligaciones
                ), ' + '
                totalEmpleados AS (
                    SELECT
                        ''Total Empleados'' AS concepto,
                        COUNT(DISTINCT codigoempleado) AS monto
                    FROM TotalesPorEmpleadoQ3
                ),
                columnasGeneral AS (
                    SELECT
                        concepto,
                        monto
                    FROM totalEmpleados
                    UNION ALL
                    SELECT
                        concepto,
                        monto
                    FROM preBaseFacturacion
                    UNION ALL
                    SELECT
                        concepto,
                        monto
                    FROM baseFacturacion
                    UNION ALL
                    SELECT
                        concepto,
                        SUM(monto)
                    FROM MovimientosObligaciones
                    GROUP BY
                        concepto
                    UNION ALL
                    ' + '
                    SELECT
                        concepto,
                        monto
                    FROM totalCuotaPatronal

                    UNION ALL
                    SELECT v.concepto, v.monto
                    FROM TotalesCalculados tc
                    CROSS APPLY (VALUES
                        (''Fee'', tc.fee),
                        (''Subtotal'', tc.subtotal),
                        (''IVA'', tc.iva),
                        (''Total Factura'', tc.total)
                    ) v(concepto, monto)
                ) ' + '
                SELECT ' + @cols + '
                FROM columnasGeneral
                PIVOT (
                    SUM(monto)
                    FOR concepto IN (' + @cols + ')
                ) p;
                ';

                EXEC sp_executesql @sql;

            ";

            $result = collect(DB::connection('sqlsrv_dynamic')->select($sql))->first();

            return $result;
        } catch (\Exception $e) {
            return [];
        }
    }

    private function data_04(array $data)
    {
        try {

            $idNominaGapeCliente = $data['id_nomina_gape_cliente'];
            $idNominaGapeEmpresa = $data['id_nomina_gape_empresa'];

            $idTipoPeriodo = $data['id_tipo_periodo'];
            $idPeriodo = $data['id_periodo'];

            $idNominaGapeParametrizacion = $data['id_nomina_gape_parametrizacion'];

            $idEsquemaCombinacion = $data['id_nomina_gape_combinacion'];

            $sql = "
                -- FISCAL 02
                DECLARE @cols NVARCHAR(MAX),
                    @sql  NVARCHAR(MAX);
                ;WITH
                Obligaciones AS (
                    SELECT
                        descripcion,
                        10 AS orden
                    FROM nom10004
                    WHERE
                        numeroconcepto IN (4002, 8000, 8001, 8002)
                ),
                titulos AS (
                    SELECT * FROM (VALUES
                        ('Total Empleados', 1),
                        ('Salarios Brutos', 2),
                        ('Sindicato', 3),
                        ('Asimilados Neto', 4),
                        ('Asimilados Bruto', 5),
                        ('Tarjeta facil', 6),
                        ('Gastos por comprobar', 7),
                        ('Base de facturacion', 8),

                        ('ISN', 10),

                        ('Total de cuotas patronales', 20),
                        ('Fee', 30),

                        ('Subtotal', 31),
                        ('IVA', 32),
                        ('Total Factura', 40)
                    ) AS X(descripcion, orden)
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

                DECLARE @idNominaGapeParametrizacion INT;
                DECLARE @idEsquemaCombinacion INT;

                DECLARE @idPeriodo INT;
                DECLARE @idTipoPeriodo INT;

                SET @idNominaGapeCliente = $idNominaGapeCliente;
                SET @idNominaGapeEmpresa = $idNominaGapeEmpresa;

                SET @idNominaGapeParametrizacion = $idNominaGapeParametrizacion;
                SET @idEsquemaCombinacion = $idEsquemaCombinacion;

                SET @idPeriodo = $idPeriodo;
                SET @idTipoPeriodo = $idTipoPeriodo;

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
                    SELECT idempleado, idperiodo, valor, idtarjetaincapacidad, ti.mnemonico AS mnemonico, ti.TipoImss AS TipoImss
                    FROM nom10009 AS fi
                    INNER JOIN nom10022 ti
                        ON fi.idtipoincidencia = ti.idtipoincidencia
                    WHERE
                        ti.TipoImss IN (''I'', ''A'')
                        AND idperiodo = @idPeriodo
                    UNION ALL
                    SELECT idempleado, idperiodo, valor, idtarjetaincapacidad, ti.mnemonico AS mnemonico, ti.TipoImss AS TipoImss
                    FROM NOM10010 AS fi
                    INNER JOIN nom10022 ti
                        ON fi.idtipoincidencia = ti.idtipoincidencia
                    WHERE
                        ti.TipoImss IN (''I'', ''A'')
                        AND idperiodo = @idPeriodo
                ), ' + '
                TarjetaControlAgrupado AS (
                    SELECT
                        idempleado,
                        SUM(CASE WHEN TipoImss IN (''I'') THEN valor ELSE 0 END) AS incapacidad,
                        SUM(CASE WHEN TipoImss IN (''A'') THEN valor ELSE 0 END) AS faltas
                    FROM tarjetaControl
                    GROUP BY
                        idempleado
                ), ' + '
                Movimientos AS (
                    SELECT idempleado, idperiodo, his.idconcepto, valor, importetotal, con.descripcion, con.tipoconcepto, con.numeroconcepto, con.imprimir, con.ClaveAgrupadoraSAT
                    FROM Nom10007 AS his
                    INNER JOIN nom10004 con
                        ON his.idconcepto = con.idconcepto
                    WHERE
                        importetotal > 0
                        AND idperiodo = @idPeriodo
                        AND con.tipoconcepto IN (''P'',''D'', ''O'')
                    UNION ALL
                    SELECT idempleado, idperiodo, actual.idconcepto, valor, importetotal, con.descripcion, con.tipoconcepto, con.numeroconcepto, con.imprimir, con.ClaveAgrupadoraSAT
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
                                    WHEN empPeriodo.fechaalta <= periodo.fechainicio THEN
                                        periodo.diasdepago
                                        + ISNULL(SUM(movP.valor), 0)
                                        - ISNULL(tc.incapacidad, 0)
                                        - ISNULL(tc.faltas, 0)

                                    WHEN empPeriodo.fechaalta > periodo.fechainicio AND empPeriodo.fechaalta <= periodo.fechafin THEN
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
                        CASE
                            WHEN pdo.numeroconcepto = 20
                                AND empPeriodo.sueldodiario <> 0
                            THEN
                                SUM(pdo.importetotal) * (emp.ccampoextranumerico2 / empPeriodo.sueldodiario)
                            ELSE
                                SUM(pdo.importetotal)
                        END AS monto,
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
                    WHERE ' + '
                        empPeriodo.idtipoperiodo = @idTipoPeriodo
                        AND empPeriodo.estadoempleado IN (''A'', ''R'')
                        AND emp.TipoRegimen IN (''02'', ''03'', ''04'')

                    GROUP BY
                        emp.codigoempleado,
                        emp.ccampoextranumerico2,
                        empPeriodo.cdiaspagados,
                        pdo.descripcion,
                        pdo.tipoconcepto,
                        pdo.numeroconcepto,
                        empPeriodo.sueldodiario,
                        emp.ccampoextranumerico3,
                        periodo.diasdepago,
                        empPeriodo.fechaalta,
                        periodo.fechainicio,
                        periodo.fechafin,
                        tc.faltas,
                        tc.incapacidad
                ), ' + '
                IncidenciaMasReciente AS (
                    SELECT TOP 1
                        ngi.id
                    FROM [becma-core2].dbo.nomina_gape_incidencia AS ngi
                    WHERE
                        ngi.id_nomina_gape_cliente = @idNominaGapeCliente
                        AND ngi.id_nomina_gape_empresa = @idNominaGapeEmpresa
                        AND ngi.id_tipo_periodo = @idTipoPeriodo
                        AND ngi.id_periodo = @idPeriodo
                    ORDER BY
                        ngi.created_at DESC,
                        ngi.id DESC
                ), ' + '
                IncidenciasNormalizadasQ2 AS (
                    SELECT
                        emp.codigoempleado as codigoempleado,
                        x.descripcion AS descripcion,
                        x.tipoConcepto AS tipoConcepto,
                        x.valor AS valor,
                        emp.ccampoextranumerico2 AS pagoPorDia,
                        emp.ccampoextranumerico3 AS pension
                    FROM IncidenciaMasReciente imr
                    INNER JOIN [becma-core2].dbo.nomina_gape_incidencia_detalle AS ngid
                        ON imr.id = ngid.id_nomina_gape_incidencia
                    INNER JOIN nom10034 AS empP
                        ON ngid.id_empleado = empP.idempleado
                            AND empP.cidperiodo = @idPeriodo
                            AND empP.idtipoperiodo = @idTipoPeriodo
                            AND empP.estadoempleado IN (''A'', ''R'')
                    INNER JOIN nom10001 AS emp
                        ON empP.idempleado = emp.idempleado
                    CROSS APPLY (VALUES
                        (''Bono'',                      ngid.bono,                      ''P''),
                        (''Comisiones'',                ngid.comision,                  ''P''),
                        (''Dia Festivo cantidad'',      ngid.cantidad_dias_festivos,    ''P''),
                        (''Dia Festivo monto'',         ISNULL(ngid.cantidad_dias_festivos, 0) * emp.ccampoextranumerico2, ''P''),
                        (''Horas Extra Doble cantidad'', ngid.horas_extra_doble_cantidad, ''P''),
                        (''Horas Extra Doble monto'',    ISNULL(ngid.horas_extra_doble_cantidad, 0) * (emp.ccampoextranumerico2 / 8.0) * 2, ''P''),
                        (''Horas Extra Triple cantidad'', ngid.horas_extra_triple_cantidad, ''P''),
                        (''Horas Extra Triple monto'',    ISNULL(ngid.horas_extra_triple_cantidad, 0) * (emp.ccampoextranumerico2 / 8.0) * 3, ''P''),
                        (''Pago adicional'',             ngid.pago_adicional, ''P''),
                        (''Premio puntualidad'',         ngid.premio_puntualidad, ''P''),
                        (''Prima Dominical cantidad'',   ngid.cantidad_prima_dominical, ''P''),
                        (''Prima Dominical monto'',      ISNULL(ngid.cantidad_prima_dominical, 0) * emp.ccampoextranumerico2 * 0.25, ''P''),
                        (''Descuentos'',                 ngid.descuento, ''D'')
                    ) AS x(descripcion, valor, tipoConcepto)
                    WHERE
                        emp.TipoRegimen IN (''02'', ''03'', ''04'')
                ), ' + '
                PreTotalesPorEmpleadoGeneralQ2 AS (
                    SELECT
                        codigoempleado,
                        MAX(sueldo) AS sueldo,
                        SUM(
                            CASE
                                WHEN tipoConcepto = ''P''
                                THEN ISNULL(monto,0) + ISNULL(valor,0)
                                ELSE 0
                            END
                        ) AS total_percepciones_sin_sueldo,
                        SUM(
                            CASE
                                WHEN tipoConcepto = ''D''
                                THEN ISNULL(monto,0) + ISNULL(valor,0)
                                ELSE 0
                            END
                        ) AS total_deducciones,
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
                            tipoConcepto AS tipoConcepto,
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
                        AND ngepcp.id = @idNominaGapeParametrizacion
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
                                (
                                    pdo.tipoconcepto = ''P''
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
                                        pdo.imprimir = 1
                                        AND pdo.ClaveAgrupadoraSAT != ''''
                                    )
                                )
                            )
                    WHERE
                        empPeriodo.idtipoperiodo = @idTipoPeriodo
                        AND empPeriodo.estadoempleado IN (''A'', ''R'')
                        AND emp.TipoRegimen IN (''02'', ''03'', ''04'')
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
                        q3.codigoempleado,
                        ISNULL((pe.total_percepciones_excedente - de.total_deducciones_excedente), 0) AS neto_excedente
                    FROM TotalesPorEmpleadoQ3 q3
                    LEFT JOIN PercepcionesExcedentes pe
                        ON q3.codigoempleado = pe.codigoempleado
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
                MovimientosObligaciones AS (
                    SELECT
                        emp.codigoempleado AS codigoempleado,
                        CASE WHEN pdo.numeroconcepto = ''90'' THEN ''ISN'' ELSE pdo.descripcion END AS concepto,
                        ''O'' AS tipoConcepto,
                        pdo.importetotal AS monto
                    FROM nom10001 emp
                    INNER JOIN nom10034 empPeriodo
                        ON emp.idempleado = empPeriodo.idempleado AND empPeriodo.cidperiodo = @idPeriodo
                    LEFT JOIN Movimientos pdo
                        ON empPeriodo.cidperiodo = pdo.idperiodo
                            AND emp.idempleado = pdo.idempleado
                            AND pdo.tipoconcepto = ''O''
                            AND pdo.numeroconcepto IN (90, 4002, 8000, 8001, 8002)
                    WHERE
                        empPeriodo.idtipoperiodo = @idTipoPeriodo
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
                CostoTotal AS (
                    SELECT
                            codigoempleado,
                            ''Compensacion'' as concepto,
                            neto_total_a_pagar AS monto
                        FROM NetoTotalAPagar
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
                        (rf.percepcion_bruta + rf.costo_social) * (nge.fee / 100.0) AS fee,
                        (rf.percepcion_bruta + rf.costo_social) * (1 + (nge.fee / 100.0)) AS subtotal,
                        (rf.percepcion_bruta + rf.costo_social) * (1 + (nge.fee / 100.0)) * 0.16 AS iva,
                        (rf.percepcion_bruta + rf.costo_social) * (1 + (nge.fee / 100.0)) * 1.16 AS total
                    FROM ResumenFinal rf
                    CROSS JOIN nomGapeEmpresa nge
                ), ' + '
                preBaseFacturacion AS (
                    SELECT
                        ''Salarios Brutos'' AS concepto,
                        SUM(total_percepciones) AS monto
                    FROM TotalesPorEmpleadoQ3
                    UNION ALL
                    SELECT
                        concepto,
                        SUM(monto_asignado)
                    FROM ProrrateoRecursivo
                    GROUP BY
                        concepto
                ), ' + '
                baseFacturacion AS (
                    SELECT
                        ''Base de facturacion'' AS concepto,
                        SUM(monto) AS monto
                    FROM preBaseFacturacion
                ), ' + '
                totalCuotaPatronal AS (
                    SELECT
                        ''Total de cuotas patronales'' AS concepto,
                        SUM(monto) AS monto
                    FROM MovimientosObligaciones
                ), ' + '
                totalEmpleados AS (
                    SELECT
                        ''Total Empleados'' AS concepto,
                        COUNT(DISTINCT codigoempleado) AS monto
                    FROM TotalesPorEmpleadoQ3
                ),
                columnasGeneral AS (
                    SELECT
                        concepto,
                        monto
                    FROM totalEmpleados
                    UNION ALL
                    SELECT
                        concepto,
                        monto
                    FROM preBaseFacturacion
                    UNION ALL
                    SELECT
                        concepto,
                        monto
                    FROM baseFacturacion
                    UNION ALL
                    SELECT
                        concepto,
                        SUM(monto)
                    FROM MovimientosObligaciones
                    GROUP BY
                        concepto
                    UNION ALL
                    ' + '
                    SELECT
                        concepto,
                        monto
                    FROM totalCuotaPatronal

                    UNION ALL
                    SELECT v.concepto, v.monto
                    FROM TotalesCalculados tc
                    CROSS APPLY (VALUES
                        (''Fee'', tc.fee),
                        (''Subtotal'', tc.subtotal),
                        (''IVA'', tc.iva),
                        (''Total Factura'', tc.total)
                    ) v(concepto, monto)
                ) ' + '
                SELECT ' + @cols + '
                FROM columnasGeneral
                PIVOT (
                    SUM(monto)
                    FOR concepto IN (' + @cols + ')
                ) p;
                ';

                EXEC sp_executesql @sql;

            ";

            $result = collect(DB::connection('sqlsrv_dynamic')->select($sql))->first();

            return $result;
        } catch (\Exception $e) {
            return [];
        }
    }
}
