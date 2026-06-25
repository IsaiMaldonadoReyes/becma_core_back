<?php

namespace App\Http\Controllers\nomina;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\DB;

use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\HttpFoundation\StreamedResponse;

use App\Http\Services\Nomina\Export\Reportes\ReporteNomina_01\ExportReporteService;

class ReporteController extends Controller
{
    /**
     * Display a listing of the resource.
     */


    public function reporte_nomina_01(Request $request, ExportReporteService $exportService)
    {
        $validated = $request->validate([
            '*.id_nomina_gape_cliente' => 'required|integer',
            '*.cliente' => 'required|string',
            '*.base_nomina' => 'required|string',
            '*.id_nomina_gape_empresa' => 'required|integer',
            '*.idtipoperiodo' => 'required|integer',
            '*.nombretipoperiodo' => 'required|string',
            '*.combinacion' => 'required|string',
            '*.base_fee' => 'required|string',
            '*.id_nomina_gape_combinacion' => 'required|integer',
            '*.id_nomina_gape_parametrizacion' => 'required|integer',
            '*.fecha_inicial' => 'required|date',
            '*.fecha_final' => 'required|date',
        ]);

        $report = $exportService->preProcessData($validated);

        // 4. DESCARGA

        $writer = IOFactory::createWriter($report, 'Xlsx');


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

    public function getParametrizacionConfigurada()
    {
        //
        try {

            $query = "
            DECLARE @sql NVARCHAR(MAX) = N'';

            ;WITH empresasConfiguradas AS (
                SELECT
                    ed.nombre_base AS base_nomina,
                    nge.id AS id_nomina_gape_empresa,
                    ngepcp.idtipoperiodo AS idtipoperiodo,
                    ngepcp.base_fee AS base_fee,
                    ngc.id AS id_nomina_gape_cliente,
                    ngc.nombre AS cliente,
                    STRING_AGG(nges.esquema, ' + ')
                    WITHIN GROUP (ORDER BY nges.id) as combinacion,
                    ngepcp.id AS id_nomina_gape_parametrizacion,
					ngcec.combinacion AS id_nomina_gape_combinacion

                FROM [becma-core2].dbo.empresa_database AS ed
                INNER JOIN [becma-core2].dbo.nomina_gape_empresa AS nge
                    ON ed.id = nge.id_empresa_database
                INNER JOIN [becma-core2].dbo.nomina_gape_cliente AS ngc
                    ON nge.id_nomina_gape_cliente = ngc.id
                INNER JOIN [becma-core2].dbo.nomina_gape_empresa_periodo_combinacion_parametrizacion AS ngepcp
                    ON nge.id = ngepcp.id_nomina_gape_empresa
                        AND nge.id_nomina_gape_cliente = ngepcp.id_nomina_gape_cliente
                INNER JOIN [becma-core2].dbo.nomina_gape_cliente_esquema_combinacion AS ngcec
                    ON ngepcp.id_nomina_gape_cliente_esquema_combinacion = ngcec.combinacion
                        AND ngepcp.id_nomina_gape_cliente = ngcec.id_nomina_gape_cliente
                        AND ngepcp.id_nomina_gape_empresa = ngcec.id_nomina_gape_empresa

                INNER JOIN [becma-core2].dbo.nomina_gape_esquema AS nges ON ngcec.id_nomina_gape_esquema = nges.id

                GROUP BY
                    ngcec.combinacion,
                    ed.nombre_base,
                    nge.id,
                    ngepcp.idtipoperiodo,
                    ngepcp.base_fee,
                    ngc.id,
                    ngc.nombre,
                    ngepcp.id
            )
            SELECT @sql = STRING_AGG(
                CAST('
                SELECT
                    ' + CAST(id_nomina_gape_cliente AS VARCHAR(20)) + ' AS id_nomina_gape_cliente,
                    ''' + cliente + ''' AS cliente,
                    ''' + base_nomina + ''' AS base_nomina,
                    ' + CAST(id_nomina_gape_empresa AS VARCHAR(20)) + ' AS id_nomina_gape_empresa,
                    tp.idtipoperiodo,
                    tp.nombretipoperiodo,
                    ''' + combinacion + ''' AS combinacion,
                    ''' + base_fee + ''' AS base_fee,
                    ' + CAST(id_nomina_gape_parametrizacion AS VARCHAR(20)) + ' AS id_nomina_gape_parametrizacion,
					' + CAST(id_nomina_gape_combinacion AS VARCHAR(20)) + ' AS id_nomina_gape_combinacion
                FROM ' + QUOTENAME(base_nomina) + '.dbo.NOM10023 AS tp
                WHERE tp.idtipoperiodo = ' + CAST(idtipoperiodo AS VARCHAR(20))
                AS NVARCHAR(MAX)),
                '
                UNION ALL
                '
            )
            FROM empresasConfiguradas

            SET @sql = @sql + '
                ORDER BY
                    id_nomina_gape_cliente,
                    id_nomina_gape_empresa,
                    nombretipoperiodo';

            EXEC sp_executesql @sql;
            ";

            $resultados = DB::select($query);

            $queryNf = "
            SELECT
                ngc.id AS id_nomina_gape_cliente,
                ngc.nombre AS cliente,
                nge.razon_social AS base_nomina,
                nge.id AS id_nomina_gape_empresa,
                ngepcp.id_nomina_gape_tipo_periodo AS idtipoperiodo,
                ngtp.nombretipoperiodo AS nombretipoperiodo,
                STRING_AGG(nges.esquema, ' + ')
                WITHIN GROUP (ORDER BY nges.id) as combinacion,
                ngepcp.base_fee AS base_fee,
                ngepcp.id AS id_nomina_gape_parametrizacion,
                ngcec.combinacion AS id_nomina_gape_combinacion

            FROM [becma-core2].dbo.nomina_gape_empresa AS nge
            INNER JOIN [becma-core2].dbo.nomina_gape_cliente AS ngc
                ON nge.id_nomina_gape_cliente = ngc.id
            INNER JOIN [becma-core2].dbo.nomina_gape_empresa_periodo_combinacion_parametrizacion AS ngepcp
                ON nge.id = ngepcp.id_nomina_gape_empresa
                    AND nge.id_nomina_gape_cliente = ngepcp.id_nomina_gape_cliente
            INNER JOIN [becma-core2].dbo.nomina_gape_cliente_esquema_combinacion AS ngcec
                ON ngepcp.id_nomina_gape_cliente_esquema_combinacion = ngcec.combinacion
                    AND ngepcp.id_nomina_gape_cliente = ngcec.id_nomina_gape_cliente
                    AND ngepcp.id_nomina_gape_empresa = ngcec.id_nomina_gape_empresa

            INNER JOIN [becma-core2].dbo.nomina_gape_esquema AS nges
                ON ngcec.id_nomina_gape_esquema = nges.id

            INNER JOIN [becma-core2].dbo.nomina_gape_tipo_periodo AS ngtp
                ON ngepcp.id_nomina_gape_tipo_periodo = ngtp.id
            WHERE
                nge.id_empresa_database IS NULL
            GROUP BY
                ngcec.combinacion,
                nge.razon_social,
                nge.id,
                ngepcp.id_nomina_gape_tipo_periodo,
                ngtp.nombretipoperiodo,
                ngepcp.base_fee,
                ngc.id,
                ngc.nombre,
                ngepcp.id
            ";

            $resultadosNf = DB::select($queryNf);

            $resultados = array_merge($resultados, $resultadosNf);

            return response()->json([
                'code' => 200,
                'data' => $resultados,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'code' => 500,
                'message' => 'Error al obtener los tipos de periodo.',
                'error' => $e->getMessage(),
            ], 500);
        }
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
}
