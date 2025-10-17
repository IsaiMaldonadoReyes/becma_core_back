<?php

namespace App\Http\Controllers\comercial;

use App\Http\Controllers\Controller;
use App\Http\Controllers\core\HelperController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use App\Models\core\EmpresaDatabase;
use App\Models\comercial\PresupuestoPeriodo;

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
        $idEmpresaDatabase =  $request->empresa;

        $conexion = $this->helperController->getConexionDatabase($idEmpresaDatabase, $idEmpresaUsuario, 'Comercial');

        $this->helperController->setDatabaseConnection($conexion, $conexion->nombre_base);

        try {
            $ejercicios = " 
                    SELECT 
                        eje.cidejercicio
                        , eje.cejercicio
                    FROM admEjercicios AS eje
                    ";

            $resultados = DB::connection('sqlsrv_dynamic')->select($ejercicios);

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

    public function marcas(Request $request)
    {
        try {

            $presupuesto = PresupuestoPeriodo::where('id_empresa', '=', $request->empresa)
                ->where('id_ejercicio', '=', $request->ejercicio)
                ->select('id_marca', 'marca')
                ->groupBy('id_marca', 'marca')
                ->get();

            // Retornar una respuesta JSON con los datos
            return response()->json([
                'code' => 200,
                'data' => $presupuesto,
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
        try {

            $presupuesto = PresupuestoPeriodo::where('id_empresa', '=', $request->empresa)
                ->where('id_ejercicio', '=', $request->ejercicio)
                ->select('id_marca', 'marca')
                ->groupBy('id_marca', 'marca')
                ->get();

            // Retornar una respuesta JSON con los datos
            return response()->json([
                'code' => 200,
                'data' => $presupuesto,
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
