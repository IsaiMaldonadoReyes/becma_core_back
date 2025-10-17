<?php

namespace App\Http\Controllers\comercial;

use App\Http\Controllers\Controller;
use App\Http\Controllers\core\HelperController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use App\Models\core\EmpresaDatabase;
use App\Models\comercial\PresupuestoPeriodo;

use Illuminate\Support\Carbon;

class RptPresupuestoController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    protected $helperController;

    public function __construct(helperController $helperController)
    {
        $this->helperController = $helperController;
    }


    public function label(Request $request)
    {
        try {
            $labels = "DECLARE @startDate DATE, @endDate DATE;
                    SET @startDate = '$request->fechaInicio';  
                    SET @endDate = '$request->fechaFin';    

                    
                    IF DATEDIFF(MONTH, @startDate, @endDate) > 0
                    BEGIN
                        
                        WITH Meses AS (
                            SELECT @startDate AS Fecha
                            UNION ALL
                            SELECT DATEADD(MONTH, 1, Fecha)
                            FROM Meses
                            WHERE DATEADD(MONTH, 1, Fecha) <= @endDate
                        )
                        SELECT DISTINCT Format(Fecha, 'MMMM', 'es-ES') AS labels,
                                        Month(Fecha)                   AS mesNumero
                        FROM   Meses
                        ORDER  BY Month(Fecha)
                        OPTION (MAXRECURSION 12);
                    END
                    
                    ELSE IF DATEDIFF(DAY, @startDate, @endDate) > 1
                    BEGIN
                        
                        SELECT CONVERT(VARCHAR(10), @startDate, 23) AS labels
                        UNION ALL
                        SELECT CONVERT(VARCHAR(10), DATEADD(DAY, 7, @startDate), 23)
                        UNION ALL
                        SELECT CONVERT(VARCHAR(10), DATEADD(DAY, 14, @startDate), 23)
                        UNION ALL
                        SELECT CONVERT(VARCHAR(10), DATEADD(DAY, 21, @startDate), 23)
                    END
                    
                    ELSE
                    BEGIN
                        
                        SELECT CONVERT(VARCHAR(10), @startDate, 23) AS labels
                    END
                    ";

            $resultados = DB::select($labels);

            // Retornar una respuesta JSON con los datos
            return response()->json([
                'code' => 200,
                'data' => $resultados,
            ], 200);
        } catch (\Exception $e) {
            // Manejo de errores
            return response()->json([
                'code' => 500,
                'message' => 'Error al obtener los datos',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Show the form for creating a new resource.
     */

    public function dataset(Request $request)
    {


        $idEmpresaUsuario = 1;
        $idEmpresaDatabase =  $request->empresa;

        $conexion = $this->helperController->getConexionDatabase($idEmpresaDatabase, $idEmpresaUsuario, 'Comercial');

        $this->helperController->setDatabaseConnection($conexion, $conexion->nombre_base);



        $tipoConsulta = $request->modo == "cantidad" ? "SUM(mov.CUNIDADESCAPTURADAS)" : "SUM(mov.CTOTAL)";

        $fechaInicial = Carbon::parse($request->fechaInicial)->startOfMonth()->format('Y-m-d');  // Primer día del mes
        $fechaFinal = Carbon::parse($request->fechaFinal)->endOfMonth()->format('Y-m-d');  // Último día del mes

        try {
            $data = "   
                    DECLARE @cols NVARCHAR(MAX), @valorClasificacion NVARCHAR(MAX), @query NVARCHAR(MAX), @startDate DATE, @endDate DATE;
                    SET @startDate = '$request->fechaInicio';  -- Fecha de inicio del rango
                    SET @endDate = '$request->fechaFin';    -- Fecha de fin del rango
                    SET @valorClasificacion = '$request->marcas';

                    WITH Meses AS (
                        SELECT @startDate AS Fecha
                        UNION ALL
                        SELECT DATEADD(MONTH, 1, Fecha)
                        FROM Meses
                        WHERE DATEADD(MONTH, 1, Fecha) <= @endDate
                    )
                    
                    SELECT @cols = STUFF((
                        SELECT ',' + QUOTENAME(FORMAT(Fecha, 'MMMM', 'es-ES'))
                        FROM Meses
                        GROUP BY FORMAT(Fecha, 'MMMM', 'es-ES'), MONTH(Fecha)
                        ORDER BY MONTH(Fecha)
                        FOR XML PATH(''), TYPE).value('.', 'NVARCHAR(MAX)'), 1, 1, '');
                    
                    -- Caso 1: El rango abarca más de un mes
                    IF DATEDIFF(MONTH, @startDate, @endDate) > 0
                    BEGIN

                        SET @query = 'SELECT marca, ' + @cols + '
                                    FROM (
                                        SELECT 
                                            claVal.CVALORCLASIFICACION AS marca
                                            , $tipoConsulta AS TotalVentas,
                                            FORMAT(doc.CFECHA, ''MMMM'', ''es-ES'') AS Periodo
                                        FROM admDocumentos AS doc
										INNER JOIN admMovimientos AS mov ON doc.CIDDOCUMENTO = mov.CIDDOCUMENTO
										INNER JOIN admProductos AS pro ON mov.CIDPRODUCTO = pro.CIDPRODUCTO
										INNER JOIN admClasificacionesValores AS claVal ON pro.CIDVALORCLASIFICACION1 = claVal.CIDVALORCLASIFICACION
										INNER JOIN admConceptos AS con ON doc.CIDCONCEPTODOCUMENTO = con.CIDCONCEPTODOCUMENTO
                                        WHERE con.CIDDOCUMENTODE = 4
                                        AND doc.CCANCELADO = 0
                                        AND doc.CFECHA BETWEEN ''' + CONVERT(VARCHAR, @startDate, 23) + ''' AND ''' + CONVERT(VARCHAR, @endDate, 23) + '''
                                        AND claVal.CIDVALORCLASIFICACION IN ('+ @valorClasificacion + ')
                                        GROUP BY 
                                            claVal.CVALORCLASIFICACION
                                            ,FORMAT(doc.CFECHA, ''MMMM'', ''es-ES'')
                                    ) AS SourceTable
                                    PIVOT (
                                        SUM(TotalVentas)
                                        FOR Periodo IN (' + @cols + ')
                                    ) AS PivotTable;';
                    END
                    -- Caso 2: El rango está dentro de un solo mes
                    ELSE IF DATEDIFF(DAY, @startDate, @endDate) > 1
                    BEGIN
                        -- Generamos las columnas basadas en semanas (4 semanas)
                        SET @cols = QUOTENAME(CONVERT(VARCHAR(10), @startDate, 23)) + ',' +
                                    QUOTENAME(CONVERT(VARCHAR(10), DATEADD(DAY, 7, @startDate), 23)) + ',' +
                                    QUOTENAME(CONVERT(VARCHAR(10), DATEADD(DAY, 14, @startDate), 23)) + ',' +
                                    QUOTENAME(CONVERT(VARCHAR(10), DATEADD(DAY, 21, @startDate), 23));

                        SET @query = 'SELECT marca, ' + @cols + '
                                    FROM (
                                        SELECT 
                                            claVal.CVALORCLASIFICACION AS marca
                                            ,$tipoConsulta AS TotalVentas,
                                            CASE 
                                                WHEN doc.CFECHA BETWEEN ''' + CONVERT(VARCHAR, @startDate, 23) + ''' AND ''' + CONVERT(VARCHAR, DATEADD(DAY, 7, @startDate), 23) + ''' THEN ''' + CONVERT(VARCHAR, @startDate, 23) + '''
                                                WHEN doc.CFECHA BETWEEN ''' + CONVERT(VARCHAR, DATEADD(DAY, 8, @startDate), 23) + ''' AND ''' + CONVERT(VARCHAR, DATEADD(DAY, 14, @startDate), 23) + ''' THEN ''' + CONVERT(VARCHAR, DATEADD(DAY, 7, @startDate), 23) + '''
                                                WHEN doc.CFECHA BETWEEN ''' + CONVERT(VARCHAR, DATEADD(DAY, 15, @startDate), 23) + ''' AND ''' + CONVERT(VARCHAR, DATEADD(DAY, 21, @startDate), 23) + ''' THEN ''' + CONVERT(VARCHAR, DATEADD(DAY, 14, @startDate), 23) + '''
                                                ELSE ''' + CONVERT(VARCHAR, DATEADD(DAY, 21, @startDate), 23) + '''
                                            END AS Periodo
                                        FROM admDocumentos AS doc
										INNER JOIN admMovimientos AS mov ON doc.CIDDOCUMENTO = mov.CIDDOCUMENTO
										INNER JOIN admProductos AS pro ON mov.CIDPRODUCTO = pro.CIDPRODUCTO
										INNER JOIN admClasificacionesValores AS claVal ON pro.CIDVALORCLASIFICACION1 = claVal.CIDVALORCLASIFICACION
										INNER JOIN admConceptos AS con ON doc.CIDCONCEPTODOCUMENTO = con.CIDCONCEPTODOCUMENTO
                                        WHERE con.CIDDOCUMENTODE = 4
                                        AND doc.CCANCELADO = 0
                                        AND doc.CFECHA BETWEEN ''' + CONVERT(VARCHAR, @startDate, 23) + ''' AND ''' + CONVERT(VARCHAR, @endDate, 23) + '''
                                        AND claVal.CIDVALORCLASIFICACION IN ('+ @valorClasificacion + ')
                                        GROUP BY 
                                            claVal.CVALORCLASIFICACION,
                                            doc.CFECHA
                                    ) AS SourceTable
                                    PIVOT (
                                        SUM(TotalVentas)
                                        FOR Periodo IN (' + @cols + ')
                                    ) AS PivotTable;';
                    END
                    -- Caso 3: El rango es de un solo día
                    ELSE
                    BEGIN
                        -- Generamos la columna basada en el día específico
                        SET @cols = QUOTENAME(CONVERT(VARCHAR(10), @startDate, 23));

                        SET @query = 'SELECT marca, ' + @cols + '
                                    FROM (
                                        SELECT 
                                            claVal.CVALORCLASIFICACION AS marca
                                            ,$tipoConsulta AS TotalVentas,
                                            CONVERT(VARCHAR(10), doc.CFECHA, 23) AS Periodo
                                        FROM admDocumentos AS doc
										INNER JOIN admMovimientos AS mov ON doc.CIDDOCUMENTO = mov.CIDDOCUMENTO
										INNER JOIN admProductos AS pro ON mov.CIDPRODUCTO = pro.CIDPRODUCTO
										INNER JOIN admClasificacionesValores AS claVal ON pro.CIDVALORCLASIFICACION1 = claVal.CIDVALORCLASIFICACION
										INNER JOIN admConceptos AS con ON doc.CIDCONCEPTODOCUMENTO = con.CIDCONCEPTODOCUMENTO
                                        WHERE con.CIDDOCUMENTODE = 4
                                        AND doc.CCANCELADO = 0
                                        AND doc.CFECHA = ''' + CONVERT(VARCHAR, @startDate, 23) + '''
                                        AND claVal.CIDVALORCLASIFICACION IN ('+ @valorClasificacion + ')
                                        GROUP BY 
                                            claVal.CVALORCLASIFICACION,
                                            doc.CFECHA
                                    ) AS SourceTable
                                    PIVOT (
                                        SUM(TotalVentas)
                                        FOR Periodo IN (' + @cols + ')
                                    ) AS PivotTable;';
                    END
                    EXEC sp_executesql @query; ";

            $resultados = DB::connection('sqlsrv_dynamic')->select($data);

            // Retornar una respuesta JSON con los datos
            return response()->json([
                'code' => 200,
                'data' => $resultados,
            ], 200);
        } catch (\Exception $e) {
            // Manejo de errores
            return response()->json([
                'code' => 500,
                'message' => 'Error al obtener los datos',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function ejercicios(Request $request)
{
    $idEmpresaUsuario = 1;
    $idEmpresaDatabase = $request->empresa;

    $conexion = $this->helperController->getConexionDatabase($idEmpresaDatabase, $idEmpresaUsuario, 'Comercial');
    $this->helperController->setDatabaseConnection($conexion, $conexion->nombre_base);

    try {
        $idMarca = $request->idMarca;

        $queryAgenteComercial = ($request->idAgente === null || $request->idAgente === "")
            ? ""
            : " AND doc.CIDAGENTE = {$request->idAgente} ";

        $queryAgentePresupuesto = ($request->idAgente === null || $request->idAgente === "")
            ? ""
            : " AND id_agente = {$request->idAgente} ";

        DB::connection('sqlsrv_dynamic')->beginTransaction();

        // Crear tabla si no existe
        DB::connection('sqlsrv_dynamic')->statement("
            IF NOT EXISTS (SELECT * FROM sys.tables WHERE name = 'VentasPivot')      
            CREATE TABLE VentasPivot (
                marca NVARCHAR(255),
                tipo NVARCHAR(50),
                enero DECIMAL(18, 2) NULL,
                febrero DECIMAL(18, 2) NULL,
                marzo DECIMAL(18, 2) NULL,
                abril DECIMAL(18, 2) NULL,
                mayo DECIMAL(18, 2) NULL,
                junio DECIMAL(18, 2) NULL,
                julio DECIMAL(18, 2) NULL,
                agosto DECIMAL(18, 2) NULL,
                septiembre DECIMAL(18, 2) NULL,
                octubre DECIMAL(18, 2) NULL,
                noviembre DECIMAL(18, 2) NULL,
                diciembre DECIMAL(18, 2) NULL
            );
        ");

        // Query dinámica con PIVOT
        $query = "
            DECLARE @cols NVARCHAR(MAX), @valorClasificacion NVARCHAR(MAX), @query NVARCHAR(MAX), 
                    @startDate DATE, @endDate DATE;
            SET @startDate = '$request->fechaInicio';  
            SET @endDate = '$request->fechaFin';    
            SET @valorClasificacion = '$idMarca';
    
            WITH Meses AS (
                SELECT @startDate AS Fecha
                UNION ALL
                SELECT DATEADD(MONTH, 1, Fecha)
                FROM Meses
                WHERE DATEADD(MONTH, 1, Fecha) <= @endDate
            )
            SELECT @cols = STUFF((
                SELECT ',' + QUOTENAME(FORMAT(Fecha, 'MMMM', 'es-ES'))
                FROM Meses
                GROUP BY FORMAT(Fecha, 'MMMM', 'es-ES'), MONTH(Fecha)
                ORDER BY MONTH(Fecha)
                FOR XML PATH(''), TYPE).value('.', 'NVARCHAR(MAX)'), 1, 1, '');
    
            SET @query = 'INSERT INTO VentasPivot (marca, tipo, ' + @cols + ')
                          SELECT marca, ''ventas'' AS tipo, ' + @cols + '
                          FROM (
                              SELECT 
                                  claVal.CVALORCLASIFICACION AS marca,
                                  SUM(mov.CTOTAL) AS TotalVentas,
                                  LOWER(FORMAT(doc.CFECHA, ''MMMM'', ''es-ES'')) AS Periodo
                              FROM admDocumentos AS doc
                              INNER JOIN admMovimientos AS mov ON doc.CIDDOCUMENTO = mov.CIDDOCUMENTO
                              INNER JOIN admProductos AS pro ON mov.CIDPRODUCTO = pro.CIDPRODUCTO
                              INNER JOIN admClasificacionesValores AS claVal 
                                  ON pro.CIDVALORCLASIFICACION1 = claVal.CIDVALORCLASIFICACION
                              INNER JOIN admConceptos AS con 
                                  ON doc.CIDCONCEPTODOCUMENTO = con.CIDCONCEPTODOCUMENTO
                              WHERE con.CIDDOCUMENTODE = 4
                              AND doc.CCANCELADO = 0
                              AND doc.CFECHA BETWEEN ''' + CONVERT(VARCHAR, @startDate, 23) + ''' 
                                                AND ''' + CONVERT(VARCHAR, @endDate, 23) + '''
                              AND claVal.CIDVALORCLASIFICACION IN (' + @valorClasificacion + ')
                              $queryAgenteComercial
                              GROUP BY claVal.CVALORCLASIFICACION, FORMAT(doc.CFECHA, ''MMMM'', ''es-ES'')
                          ) AS SourceTable
                          PIVOT (
                              SUM(TotalVentas) FOR Periodo IN (' + @cols + ')
                          ) AS PivotTable;';
    
            EXEC sp_executesql @query;
        ";

        // Ejecutar pivot dinámico
        DB::connection('sqlsrv_dynamic')->unprepared($query);

        // Consultar resultados
        $resultados = DB::connection('sqlsrv_dynamic')->select("
            SELECT * FROM VentasPivot
            UNION ALL
            SELECT 
                marca,
                'Objetivos' AS tipo,
                ISNULL(SUM(enero), 0) AS enero,
                ISNULL(SUM(febrero), 0) AS febrero,
                ISNULL(SUM(marzo), 0) AS marzo,
                ISNULL(SUM(abril), 0) AS abril,
                ISNULL(SUM(mayo), 0) AS mayo,
                ISNULL(SUM(junio), 0) AS junio,
                ISNULL(SUM(julio), 0) AS julio,
                ISNULL(SUM(agosto), 0) AS agosto,
                ISNULL(SUM(septiembre), 0) AS septiembre,
                ISNULL(SUM(octubre), 0) AS octubre,
                ISNULL(SUM(noviembre), 0) AS noviembre,
                ISNULL(SUM(diciembre), 0) AS diciembre
            FROM presupuesto_periodo
            WHERE 
                id_empresa = $request->idEmpresa 
                $queryAgentePresupuesto
                AND id_ejercicio = $request->idEjercicio 
                AND id_marca IN ($idMarca)
            GROUP BY marca
        ");

        DB::connection('sqlsrv_dynamic')->commit();

        // Limpiar tabla al final
        DB::connection('sqlsrv_dynamic')->statement("DELETE FROM VentasPivot");

        return response()->json([
            'code' => 200,
            'data' => $resultados,
        ], 200);

    } catch (\Exception $e) {
        DB::connection('sqlsrv_dynamic')->rollBack();

        return response()->json([
            'code' => 500,
            'message' => 'Error al obtener los datos',
            'error' => $e->getMessage(),
        ], 500);
    }
}


    public function marcas(Request $request)
    {
        try {

            $result = PresupuestoPeriodo::where('id_empresa', '=', $request->empresa)
                ->where('id_ejercicio', '=', $request->ejercicio)
                ->select('id_marca', 'marca')
                ->groupBy('id_marca', 'marca')
                ->get();

            // Retornar una respuesta JSON con los datos
            return response()->json([
                'code' => 200,
                'data' => $result,
            ], 200);
        } catch (\Exception $e) {
            // Manejo de errores
            return response()->json([
                'code' => 500,
                'message' => 'Error al obtener los datos',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function agentes(Request $request)
    {
        $idEmpresaUsuario = 1;
        $idEmpresaDatabase =  $request->empresa;

        $conexion = $this->helperController->getConexionDatabase($idEmpresaDatabase, $idEmpresaUsuario, 'Comercial');

        $this->helperController->setDatabaseConnection($conexion, $conexion->nombre_base);

        try {
            $query = " 
                    DECLARE @idEjercicio Int;
                    SET @idEjercicio = $request->ejercicio;  

                    SELECT  CIDAGENTE
                            , CNOMBREAGENTE
                    FROM    [becma-core].dbo.presupuesto_periodo AS pp
                            INNER JOIN admAgentes AS age on pp.id_agente = age.CIDAGENTE
                    WHERE   pp.id_ejercicio = @idEjercicio
                    GROUP BY 
                            CIDAGENTE
                            , CNOMBREAGENTE
                    ";

            $result = DB::connection('sqlsrv_dynamic')->select($query);

            // Retornar una respuesta JSON con los datos
            return response()->json([
                'code' => 200,
                'data' => $result,
            ], 200);
        } catch (\Exception $e) {
            // Manejo de errores
            return response()->json([
                'code' => 500,
                'message' => 'Error al obtener los datos',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
