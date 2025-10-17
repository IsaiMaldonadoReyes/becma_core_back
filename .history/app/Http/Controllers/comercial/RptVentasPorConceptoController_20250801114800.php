<?php

namespace App\Http\Controllers\comercial;

use App\Http\Controllers\Controller;
use App\Http\Controllers\core\HelperController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RptVentasPorConceptoController extends Controller
{

    protected $helperController;

    public function __construct(helperController $helperController)
    {
        $this->helperController = $helperController;
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    public function conceptosFacturaComercial(Request $request)
    {
        $idEmpresaUsuario = 1;
        $idEmpresaDatabase =  $request->empresa;

        $conexion = $this->helperController->getConexionDatabase($idEmpresaDatabase, $idEmpresaUsuario, 'Comercial');

        $this->helperController->setDatabaseConnection($conexion, $conexion->nombre_base);

        try {
            $labels = " DECLARE @startDate DATE, @endDate DATE;
                    SET @startDate = '$request->fechaInicio';
                    SET @endDate = '$request->fechaFin';

                    SELECT
                            con.CIDCONCEPTODOCUMENTO
                            , con.CNOMBRECONCEPTO
                    FROM admDocumentos AS doc
                    INNER JOIN admConceptos AS con ON doc.CIDCONCEPTODOCUMENTO = con.CIDCONCEPTODOCUMENTO
                    WHERE con.CIDDOCUMENTODE = 4
                        AND doc.CCANCELADO = 0
                        AND doc.CFECHA BETWEEN '' + CONVERT(VARCHAR, @startDate, 23) + '' AND '' + CONVERT(VARCHAR, @endDate, 23) + ''
                    GROUP BY
                        con.CIDCONCEPTODOCUMENTO
                        , con.CNOMBRECONCEPTO ";

            $resultados = DB::connection('sqlsrv_dynamic')->select($labels);

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

    public function dataset(Request $request)
    {
        $idEmpresaUsuario = 1;
        $idEmpresaDatabase =  $request->empresa;

        $conexion = $this->helperController->getConexionDatabase($idEmpresaDatabase, $idEmpresaUsuario, 'Comercial');

        $this->helperController->setDatabaseConnection($conexion, $conexion->nombre_base);

        try {

            $data = "   DECLARE @cols NVARCHAR(MAX), @documentCodes NVARCHAR(MAX), @query NVARCHAR(MAX), @startDate DATE, @endDate DATE;
                    SET @startDate = '$request->fechaInicio';  -- Fecha de inicio del rango
                    SET @endDate = '$request->fechaFin';    -- Fecha de fin del rango
                    SET @documentCodes = '$request->conceptos';

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

                        SET @query = 'SELECT CNOMBRECONCEPTO, CCODIGOCONCEPTO, ' + @cols + '
                                    FROM (
                                        SELECT
                                            admConceptos.CNOMBRECONCEPTO,
                                            admConceptos.CCODIGOCONCEPTO,
                                            SUM(admDocumentos.CTOTAL) AS TotalVentas,
                                            FORMAT(admDocumentos.CFECHA, ''MMMM'', ''es-ES'') AS Periodo
                                        FROM admDocumentos
                                        INNER JOIN admConceptos
                                            ON admDocumentos.CIDCONCEPTODOCUMENTO = admConceptos.CIDCONCEPTODOCUMENTO
                                        WHERE admConceptos.CIDDOCUMENTODE = 4
                                        AND admDocumentos.CCANCELADO = 0
                                        AND CFECHA BETWEEN ''' + CONVERT(VARCHAR, @startDate, 23) + ''' AND ''' + CONVERT(VARCHAR, @endDate, 23) + '''
                                        AND admConceptos.CIDCONCEPTODOCUMENTO IN ('+ @documentCodes + ')
                                        GROUP BY
                                            admConceptos.CNOMBRECONCEPTO,
                                            admConceptos.CCODIGOCONCEPTO,
                                            FORMAT(admDocumentos.CFECHA, ''MMMM'', ''es-ES'')
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

                        SET @query = 'SELECT CNOMBRECONCEPTO, CCODIGOCONCEPTO, ' + @cols + '
                                    FROM (
                                        SELECT
                                            admConceptos.CNOMBRECONCEPTO,
                                            admConceptos.CCODIGOCONCEPTO,
                                            SUM(admDocumentos.CTOTAL) AS TotalVentas,
                                            CASE
                                                WHEN CFECHA BETWEEN ''' + CONVERT(VARCHAR, @startDate, 23) + ''' AND ''' + CONVERT(VARCHAR, DATEADD(DAY, 7, @startDate), 23) + ''' THEN ''' + CONVERT(VARCHAR, @startDate, 23) + '''
                                                WHEN CFECHA BETWEEN ''' + CONVERT(VARCHAR, DATEADD(DAY, 8, @startDate), 23) + ''' AND ''' + CONVERT(VARCHAR, DATEADD(DAY, 14, @startDate), 23) + ''' THEN ''' + CONVERT(VARCHAR, DATEADD(DAY, 7, @startDate), 23) + '''
                                                WHEN CFECHA BETWEEN ''' + CONVERT(VARCHAR, DATEADD(DAY, 15, @startDate), 23) + ''' AND ''' + CONVERT(VARCHAR, DATEADD(DAY, 21, @startDate), 23) + ''' THEN ''' + CONVERT(VARCHAR, DATEADD(DAY, 14, @startDate), 23) + '''
                                                ELSE ''' + CONVERT(VARCHAR, DATEADD(DAY, 21, @startDate), 23) + '''
                                            END AS Periodo
                                        FROM admDocumentos
                                        INNER JOIN admConceptos
                                            ON admDocumentos.CIDCONCEPTODOCUMENTO = admConceptos.CIDCONCEPTODOCUMENTO
                                        WHERE admConceptos.CIDDOCUMENTODE = 4
                                        AND admDocumentos.CCANCELADO = 0
                                        AND CFECHA BETWEEN ''' + CONVERT(VARCHAR, @startDate, 23) + ''' AND ''' + CONVERT(VARCHAR, @endDate, 23) + '''
                                        AND admConceptos.CIDCONCEPTODOCUMENTO IN ('+ @documentCodes + ')
                                        GROUP BY
                                            admConceptos.CNOMBRECONCEPTO,
                                            admConceptos.CCODIGOCONCEPTO,
                                            CFECHA
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

                        SET @query = 'SELECT CNOMBRECONCEPTO, CCODIGOCONCEPTO, ' + @cols + '
                                    FROM (
                                        SELECT
                                            admConceptos.CNOMBRECONCEPTO,
                                            admConceptos.CCODIGOCONCEPTO,
                                            SUM(admDocumentos.CTOTAL) AS TotalVentas,
                                            CONVERT(VARCHAR(10), CFECHA, 23) AS Periodo
                                        FROM admDocumentos
                                        INNER JOIN admConceptos
                                            ON admDocumentos.CIDCONCEPTODOCUMENTO = admConceptos.CIDCONCEPTODOCUMENTO
                                        WHERE admConceptos.CIDDOCUMENTODE = 4
                                        AND admDocumentos.CCANCELADO = 0
                                        AND CFECHA = ''' + CONVERT(VARCHAR, @startDate, 23) + '''
                                        AND admConceptos.CIDCONCEPTODOCUMENTO IN ('+ @documentCodes + ')
                                        GROUP BY
                                            admConceptos.CNOMBRECONCEPTO,
                                            admConceptos.CCODIGOCONCEPTO,
                                            CFECHA
                                    ) AS SourceTable
                                    PIVOT (
                                        SUM(TotalVentas)
                                        FOR Periodo IN (' + @cols + ')
                                    ) AS PivotTable;';
                    END
                    EXEC sp_executesql @query; ";

            $resultados = DB::connection('sqlsrv_dynamic')->select($data);

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
}
