<?php

namespace App\Http\Services\Nomina\Export\Incidencias;

use Illuminate\Http\Request;

use App\Http\Services\Core\HelperService;

use Illuminate\Support\Facades\DB;

class IncidenciasQueryService
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
    private function datosEncabezadoQueryContpaq(Request $request)
    {
        try {
            $idNominaGapeEmpresa = $request->id_nomina_gape_empresa;
            $idTipoPeriodo = $request->id_tipo_periodo;
            $idPeriodo = $request->periodo_inicial;

            $conexion = $this->helper->getConexionDatabaseNGE($idNominaGapeEmpresa, 'Nom');

            $this->helper->setDatabaseConnection($conexion, $conexion->nombre_base);

            $sql = "
                DECLARE @idNominaGapeEmpresa INT;
                DECLARE @idPeriodo INT;
                DECLARE @idTipoPeriodo INT;

                SET @idNominaGapeEmpresa = $idNominaGapeEmpresa;
                SET @idPeriodo = $idPeriodo;
                SET @idTipoPeriodo = $idTipoPeriodo;

                SELECT
                    per.ejercicio,
                    per.tipoPeriodo,
                    per.periodo,
                    cli.nombre        AS cliente,
                    cli.razon_social  AS empresa
                FROM (
                    SELECT
                        p.ejercicio,
                        tp.nombretipoperiodo AS tipoPeriodo,
                        p.numeroperiodo     AS periodo
                    FROM nom10002 p
                    INNER JOIN nom10023 tp
                        ON p.idtipoperiodo = tp.idtipoperiodo
                    WHERE p.idperiodo = @idPeriodo
                ) per
                CROSS JOIN (
                    SELECT
                        cliente.nombre,
                        empresa.razon_social AS razon_social
                    FROM [becma-core2].dbo.nomina_gape_cliente AS cliente
                    INNER JOIN [becma-core2].dbo.nomina_gape_empresa AS empresa
                        ON cliente.id = empresa.id_nomina_gape_cliente
                    WHERE empresa.id = @idNominaGapeEmpresa
                ) cli;
            ";

            //return $sql;

            $result = collect(DB::connection('sqlsrv_dynamic')->select($sql))
                ->map(fn($row) => (array) $row)
                ->first();


            return $result;
        } catch (\Exception $e) {
            return [];
        }
    }

    private function datosEncabezadoQueryExcedente(Request $request)
    {
        try {
            $idNominaGapeEmpresa = $request->id_nomina_gape_empresa;

            $sql = "
                DECLARE @idNominaGapeEmpresa INT;

                SET @idNominaGapeEmpresa = $idNominaGapeEmpresa;

                SELECT
                    cliente.nombre AS cliente
                    , empresa.razon_social AS empresa
                FROM nomina_gape_cliente AS cliente
                INNER JOIN nomina_gape_empresa AS empresa
                    ON cliente.id = empresa.id_nomina_gape_cliente
                WHERE empresa.id = @idNominaGapeEmpresa
            ";

            $result = collect(DB::select($sql))
                ->map(fn($row) => (array) $row)
                ->first();


            return $result;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Consulta para formato fiscal/mixto.
     */
    private function datosQuerySueldoImss(Request $request)
    {
        try {
            $idNominaGapeEmpresa = $request->id_nomina_gape_empresa;
            $idTipoPeriodo = $request->id_tipo_periodo;
            $idPeriodo = $request->periodo_inicial;

            $conexion = $this->helper->getConexionDatabaseNGE($idNominaGapeEmpresa, 'Nom');

            $this->helper->setDatabaseConnection($conexion, $conexion->nombre_base);

            $sql = "
                DECLARE @idPeriodo INT;
                DECLARE @idTipoPeriodo INT;

                SET @idPeriodo = $idPeriodo;
                SET @idTipoPeriodo = $idTipoPeriodo;

                SELECT
                    emp.codigoempleado
                    , emp.nombrelargo AS nombre
                    , ISNULL(puesto.descripcion, '') AS puesto
                    , FORMAT(emp.fechaalta, 'dd-MM-yyyy') AS fechaAlta
                    , ISNULL(emp.campoextra1, '') AS fechaAltaGape
                    , emp.numerosegurosocial AS nss
                    , emp.rfc + SUBSTRING(CONVERT(char(10),emp.fechanacimiento , 126), 3,2) + SUBSTRING(CONVERT(char(10),emp.fechanacimiento , 126), 6,2) + SUBSTRING(CONVERT(char(10),emp.fechanacimiento, 126), 9,2) + homoclave AS rfc
                    , emp.curpi + SUBSTRING(CONVERT(char(10),emp.fechanacimiento , 126), 3,2) + SUBSTRING(CONVERT(char(10),emp.fechanacimiento , 126), 6,2) + SUBSTRING(CONVERT(char(10),emp.fechanacimiento, 126), 9,2) + emp.curpf AS curp
                    , emp.ccampoextranumerico1 as sueldoMensual
                    , emp.ccampoextranumerico2 as sueldoDiario
                    , empPeriodo.sueldodiario AS sd
                    , empPeriodo.sueldointegrado AS sdi
                FROM nom10001 emp
                    INNER JOIN nom10034 AS empPeriodo
                        ON emp.idempleado = empPeriodo.idempleado
                        AND empPeriodo.cidperiodo = @idPeriodo
                    INNER JOIN nom10002 AS periodo
                        ON empPeriodo.cidperiodo = periodo.idperiodo
                    LEFT JOIN nom10006 AS puesto
                        ON emp.idpuesto = puesto.idpuesto
                WHERE emp.idtipoperiodo = @idTipoPeriodo
                    AND empPeriodo.estadoempleado IN ('A', 'R')
                    AND emp.TipoRegimen IN ('02', '03', '04') -- sueldos y salarios
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

    private function datosQueryAsimilados(Request $request)
    {
        try {
            $idNominaGapeEmpresa = $request->id_nomina_gape_empresa;
            $idTipoPeriodo = $request->id_tipo_periodo;
            $idPeriodo = $request->periodo_inicial;

            $conexion = $this->helper->getConexionDatabaseNGE($idNominaGapeEmpresa, 'Nom');

            $this->helper->setDatabaseConnection($conexion, $conexion->nombre_base);

            $sql = "
                DECLARE @idPeriodo INT;
                DECLARE @idTipoPeriodo INT;

                SET @idPeriodo = $idPeriodo;
                SET @idTipoPeriodo = $idTipoPeriodo;

                SELECT
                    emp.codigoempleado
                    , emp.nombrelargo AS nombre
                    , ISNULL(puesto.descripcion, '') AS puesto
                    , FORMAT(emp.fechaalta, 'dd-MM-yyyy') AS fechaAlta
                    , ISNULL(emp.campoextra1, '') AS fechaAltaGape
                    , emp.numerosegurosocial AS nss
                    , emp.rfc + SUBSTRING(CONVERT(char(10),emp.fechanacimiento , 126), 3,2) + SUBSTRING(CONVERT(char(10),emp.fechanacimiento , 126), 6,2) + SUBSTRING(CONVERT(char(10),emp.fechanacimiento, 126), 9,2) + homoclave AS rfc
                    , emp.curpi + SUBSTRING(CONVERT(char(10),emp.fechanacimiento , 126), 3,2) + SUBSTRING(CONVERT(char(10),emp.fechanacimiento , 126), 6,2) + SUBSTRING(CONVERT(char(10),emp.fechanacimiento, 126), 9,2) + emp.curpf AS curp
                    , emp.ccampoextranumerico1 as sueldoMensual
                    , emp.ccampoextranumerico2 as sueldoDiario
                    , empPeriodo.sueldodiario AS sd
                    , empPeriodo.sueldointegrado AS sdi
                FROM nom10001 emp
                    INNER JOIN nom10034 AS empPeriodo
                        ON emp.idempleado = empPeriodo.idempleado
                        AND empPeriodo.cidperiodo = @idPeriodo
                    INNER JOIN nom10002 AS periodo
                        ON empPeriodo.cidperiodo = periodo.idperiodo
                    LEFT JOIN nom10006 AS puesto
                        ON emp.idpuesto = puesto.idpuesto
                WHERE emp.idtipoperiodo = @idTipoPeriodo
                    AND empPeriodo.estadoempleado IN ('A', 'R')
                    AND emp.TipoRegimen IN ('05', '06', '07', '08', '09', '10', '11') -- honorarios y asimilados
                ORDER BY
                    emp.codigoempleado
            ";

            $result = DB::connection('sqlsrv_dynamic')->select($sql);

            return $result;
        } catch (\Exception $e) {
            return [];
        }
    }

    private function datosQuerySindicato(Request $request)
    {
        try {
            $idNominaGapeCliente = $request->id_nomina_gape_cliente;
            $idNominaGapeEmpresa = $request->id_nomina_gape_empresa;
            $idTipoPeriodo = $request->id_tipo_periodo;
            $idPeriodo = $request->periodo_inicial;

            $sql = "
                SELECT
                    codigoempleado
                    , apellidopaterno + ' ' + apellidomaterno + ' ' + nombre AS nombrelargo
                    , '' AS puesto
                    , FORMAT(fechaalta, 'dd-MM-yyyy') as fechaAlta
                    , ISNULL(campoextra1, '') AS fechaAltaGape
                    , ClabeInterbancaria AS nss
                    , cuentacw AS rfc
                    , '' AS curp
                FROM nomina_gape_empleado emp
                INNER JOIN nomina_gape_esquema as esquema
                    ON emp.id_nomina_gape_esquema = esquema.id
                WHERE
                    emp.estado_empleado = 1
                    AND esquema.esquema = 'Sindicato'
                    AND emp.id_nomina_gape_cliente = $idNominaGapeCliente
                    AND emp.id_nomina_gape_empresa = $idNominaGapeEmpresa
                ORDER BY
                    emp.codigoempleado
            ";

            $result = DB::select($sql);


            return $result;
        } catch (\Exception $e) {
            return [];
        }
    }

    private function datosQueryTarjetaFacil(Request $request)
    {
        try {
            $idNominaGapeCliente = $request->id_nomina_gape_cliente;
            $idNominaGapeEmpresa = $request->id_nomina_gape_empresa;
            $idTipoPeriodo = $request->id_tipo_periodo;
            $idPeriodo = $request->periodo_inicial;

            $sql = "
                SELECT
                    codigoempleado
                    , apellidopaterno + ' ' + apellidomaterno + ' ' + nombre AS nombrelargo
                    , '' AS puesto
                    , FORMAT(fechaalta, 'dd-MM-yyyy') as fechaAlta
                    , ISNULL(campoextra1, '') AS fechaAltaGape
                    , ClabeInterbancaria AS nss
                    , cuentacw AS rfc
                    , '' AS curp
                FROM nomina_gape_empleado emp
                INNER JOIN nomina_gape_esquema as esquema
                    ON emp.id_nomina_gape_esquema = esquema.id
                WHERE
                    emp.estado_empleado = 1
                    AND esquema.esquema = 'Tarjeta facil'
                    AND emp.id_nomina_gape_cliente = $idNominaGapeCliente
                    AND emp.id_nomina_gape_empresa = $idNominaGapeEmpresa
                ORDER BY
                    emp.codigoempleado
            ";

            $result = DB::select($sql);


            return $result;
        } catch (\Exception $e) {
            return [];
        }
    }

    private function datosQueryGastosComprobar(Request $request)
    {
        try {
            $idNominaGapeCliente = $request->id_nomina_gape_cliente;
            $idNominaGapeEmpresa = $request->id_nomina_gape_empresa;
            $idTipoPeriodo = $request->id_tipo_periodo;
            $idPeriodo = $request->periodo_inicial;

            $sql = "
                SELECT
                    codigoempleado
                    , apellidopaterno + ' ' + apellidomaterno + ' ' + nombre AS nombrelargo
                    , '' AS puesto
                    , FORMAT(fechaalta, 'dd-MM-yyyy') as fechaAlta
                    , ISNULL(campoextra1, '') AS fechaAltaGape
                    , ClabeInterbancaria AS nss
                    , cuentacw AS rfc
                    , '' AS curp
                FROM nomina_gape_empleado emp
                INNER JOIN nomina_gape_esquema as esquema
                    ON emp.id_nomina_gape_esquema = esquema.id
                WHERE
                    emp.estado_empleado = 1
                    AND esquema.esquema = 'Gastos por comprobar'
                    AND emp.id_nomina_gape_cliente = $idNominaGapeCliente
                    AND emp.id_nomina_gape_empresa = $idNominaGapeEmpresa
                ORDER BY
                    emp.codigoempleado
            ";

            $result = DB::select($sql);

            return $result;
        } catch (\Exception $e) {
            return [];
        }
    }
}
