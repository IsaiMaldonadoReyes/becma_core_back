<?php

namespace App\Http\Services\Nomina\Export\Reportes\ReporteNomina_01;

use Illuminate\Http\Request;

use App\Http\Services\Core\HelperService;

use Illuminate\Support\Facades\DB;

class QueryServiceExcedente
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
            throw new \Exception("La consulta '$queryName' no está definida en QueryServiceExcedente.");
        }

        return $this->{$queryName}($request);
    }

    private function data_01(array $data)
    {
        try {


            $idNominaGapeCliente = $data['id_nomina_gape_cliente'];
            $idNominaGapeEmpresa = $data['id_nomina_gape_empresa'];

            $idTipoPeriodo = $data['idtipoperiodo'];

            $fecha_inicio = $data['fecha_inicial'];
            $fecha_fin = $data['fecha_final'];

            $esquema = $data['esquema'];

            $idEsquemaCombinacion = $data['id_nomina_gape_combinacion'];

            $sql = "
                DECLARE @cols NVARCHAR(MAX),
                    @query  NVARCHAR(MAX);
                ;WITH
                titulos AS (
                    SELECT * FROM (VALUES
                        ('Total Empleados', 1),
                        ('Sindicato', 2),
                        ('Tarjeta facil', 5),
                        ('Gastos por comprobar', 6),
                        ('Base de facturacion', 7),
                        ('Fee', 30),
                        ('Subtotal', 31),
                        ('IVA', 32),
                        ('Total Factura', 40)
                    ) AS X(descripcion, orden)
                )
                SELECT @cols = STUFF((
                SELECT ', ' + QUOTENAME(descripcion)
                FROM titulos
                ORDER BY orden
                FOR XML PATH(''), TYPE
                ).value('.', 'NVARCHAR(MAX)'), 1, 2, '');

                SET @query = CAST('' AS NVARCHAR(MAX)) + '

                DECLARE @fechaInicio DATE;
                DECLARE @fechaFin DATE;

                DECLARE @idTipoPeriodo INT;

                DECLARE @idNominaGapeCliente INT;
                DECLARE @idNominaGapeEmpresa INT;

                DECLARE @idEsquemaCombinacion INT;

                SET @idNominaGapeCliente = $idNominaGapeCliente;
                SET @idNominaGapeEmpresa = $idNominaGapeEmpresa;

                SET @idEsquemaCombinacion = $idEsquemaCombinacion;

                SET @fechaInicio = ''$fecha_inicio'';
                SET @fechaFin = ''$fecha_fin'';
                SET @idTipoPeriodo = $idTipoPeriodo;

                ' + '
                ;WITH
                IncidenciasMasRecientes AS (
                    SELECT
                        id,
                        id_nomina_gape_cliente,
                        id_nomina_gape_empresa,
                        CAST(created_at AS DATE) AS fecha_carga,
                        created_at,
                        ROW_NUMBER() OVER (
                            PARTITION BY CAST(created_at AS DATE)
                            ORDER BY created_at DESC, id DESC
                        ) AS rn
                    FROM nomina_gape_incidencia
                    WHERE
                        id_nomina_gape_cliente = @idNominaGapeCliente
                        AND id_nomina_gape_empresa = @idNominaGapeEmpresa
                        AND id_tipo_periodo = @idTipoPeriodo
                        AND CAST(created_at AS DATE) BETWEEN @fechaInicio AND @fechaFin
                ),  ' + '
                UltimasIncidenciasPorDia AS (
                    SELECT *
                    FROM IncidenciasMasRecientes
                    WHERE rn = 1
                ),  ' + '
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
                    INNER JOIN UltimasIncidenciasPorDia AS ngi
                        ON emp.id_nomina_gape_cliente = ngi.id_nomina_gape_cliente
                            AND emp.id_nomina_gape_empresa = ngi.id_nomina_gape_empresa
                    LEFT JOIN nomina_gape_incidencia_detalle AS ngid
                        ON ngi.id = ngid.id_nomina_gape_incidencia
                            AND emp.codigoempleado = ngid.codigo_empleado
                            AND ngid.id_nomina_gape_combinacion = @idEsquemaCombinacion
                    CROSS APPLY (VALUES
                        (''Bono'',                      ngid.bono),
                        (''Comisiones'',                ngid.comision),
                        (''Dia Festivo cantidad'',      ngid.cantidad_dias_festivos),
                        (''Dia Festivo monto'',         ISNULL(ngid.cantidad_dias_festivos, 0) * emp.ccampoextranumerico2),
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
                        esquema.esquema = ''$esquema''
                        AND emp.estado_empleado = 1
                        AND emp.id_nomina_gape_cliente = @idNominaGapeCliente
                        AND emp.id_nomina_gape_empresa = @idNominaGapeEmpresa
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
                ProrrateoFinal AS (
                    SELECT
                        concepto,
                        SUM(monto_asignado) AS monto
                    FROM ProrrateoRecursivo
                    GROUP BY concepto
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
                        AND ngcec.id_nomina_gape_empresa = @idNominaGapeEmpresa
                        AND ngcec.combinacion = @idEsquemaCombinacion
                        AND ngcec.orden = 1
                ), ' + '
                baseFacturacion AS (
                    SELECT
                        ''Base de facturacion'' AS concepto,
                        SUM(monto) AS monto
                    FROM ProrrateoFinal
                ), ' + '
                totalEmpleados AS (
                    SELECT
                        ''Total Empleados'' AS concepto,
                        COUNT(DISTINCT codigoempleado) AS monto
                    FROM IncidenciasNormalizadasQ2
                ), ' + '
                totales AS (
                    SELECT
                        SUM(total_percepciones) AS percepcion_bruta
                    FROM TotalesPorEmpleadoGeneralQ2
                ),
                TotalesCalculados AS (
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
                    CROSS JOIN parametrizacion p
                ), ' + '
                columnasGeneral AS (
                    SELECT
                        concepto,
                        monto
                    FROM totalEmpleados
                    UNION ALL
                    SELECT
                        concepto,
                        monto
                    FROM ProrrateoFinal

                    UNION ALL

                    SELECT
                        concepto,
                        monto
                    FROM baseFacturacion

                    UNION ALL

                    SELECT v.concepto, v.monto
                    FROM TotalesCalculados tc
                    CROSS APPLY (VALUES
                        (''Fee'', tc.fee),
                        (''Subtotal'', tc.subtotal),
                        (''IVA'', tc.iva),
                        (''Total Factura'', tc.total)
                    ) v(concepto, monto)
                )

                SELECT ' + @cols + '
                FROM columnasGeneral
                PIVOT (
                    SUM(monto)
                    FOR concepto IN (' + @cols + ')
                ) p;
                ';

                EXEC(@query);

            ";


            /*
            dd([
                'row' => $sql,
            ]);

            */


            $result = collect(DB::select($sql));

            return $result;
        } catch (\Exception $e) {
            return [];
        }
    }
}
