<?php

namespace App\Http\Controllers\nomina;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Carbon\Carbon;

use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\HttpFoundation\StreamedResponse;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

use App\Models\nomina\GAPE\NominaGapeIncidencia;

use App\Http\Services\Nomina\Export\Incidencias\ConfigFormatoIncidenciasService;
use App\Http\Services\Nomina\Export\Incidencias\IncidenciasQueryService;
use App\Http\Services\Nomina\Export\Incidencias\ExportIncidenciasService;

use App\Http\Services\Nomina\Import\Incidencias\IncidenciasSaver;
use App\Http\Services\Nomina\Import\Incidencias\IncidenciasNominaApplier;
use App\Http\Services\Nomina\Import\Incidencias\IncidenciasImporter;
use App\Http\Services\Nomina\Import\Incidencias\IncidenciasHojasValidator;

use App\Http\Services\Core\HelperService;

use Illuminate\Support\Facades\DB;

class IncidenciaController extends Controller
{

    public function descargaFormato(
        Request $request,
        IncidenciasQueryService $queryService,
        ExportIncidenciasService $exporter
    ) {
        $validated = $request->validate([
            'id_nomina_gape_cliente' => 'required|integer',
            'id_nomina_gape_empresa' => 'required|integer',
            'id_esquema'             => 'required|array|min:1',
            'id_tipo_periodo'        => 'required_if:fiscal,true',
            'id_ejercicio'           => 'required_if:fiscal,true',
            'periodo_inicial'        => 'required_if:fiscal,true',
        ]);

        $idCliente  = $validated['id_nomina_gape_cliente'];
        $idEmpresa  = $validated['id_nomina_gape_empresa'];
        $idEsquemas = array_map('intval', $validated['id_esquema']);

        $mapaEsquemaConfig = [
            'Sueldo IMSS'          => 'SUELDO_IMSS',
            'Asimilados'           => 'ASIMILADOS',
            'Sindicato'            => 'SINDICATO',
            'Tarjeta facil'        => 'TARJETA_FACIL',
            'Gastos por comprobar' => 'GASTOS_POR_COMPROBAR',
        ];

        /**
         * 1️⃣ Obtener esquemas reales
         */
        $esquemas = DB::table('nomina_gape_cliente_esquema_combinacion as ngcec')
            ->join(
                'nomina_gape_empresa_periodo_combinacion_parametrizacion as ngepcp',
                'ngcec.combinacion',
                '=',
                'ngepcp.id_nomina_gape_cliente_esquema_combinacion'
            )
            ->join(
                'nomina_gape_esquema as nge',
                'ngcec.id_nomina_gape_esquema',
                '=',
                'nge.id'
            )
            ->where('ngepcp.id_nomina_gape_cliente', $idCliente)
            ->where('ngepcp.id_nomina_gape_empresa', $idEmpresa)
            ->where('ngcec.id_nomina_gape_cliente', $idCliente)
            ->whereIn('ngcec.combinacion', $idEsquemas)
            ->where('ngcec.orden', 1)
            ->select('nge.esquema')
            ->get();

        if ($esquemas->isEmpty()) {
            throw new \Exception('No hay esquemas para generar el formato.');
        }

        /**
         * 2️⃣ Cargar plantilla UNA sola vez
         */
        $configs = ConfigFormatoIncidenciasService::getConfig();
        $spreadsheet = $exporter->loadSpreadsheet($configs['SUELDO_IMSS']['path']);

        /**
         * 3️⃣ Iterar esquemas y llenar hojas
         */

        $hojasUsadas = [];

        foreach ($esquemas as $row) {
            $nombreEsquema = $row->esquema;

            $clave = $mapaEsquemaConfig[$nombreEsquema] ?? null;
            if (!$clave || !isset($configs[$clave])) {
                continue;
            }

            $configHoja = $configs[$clave];

            // Obtener datos por hoja
            $dataRaw = $queryService->getData($configHoja['query'], $request);

            $data = collect($dataRaw)
                ->map(fn($r) => (array)$r)
                ->toArray();

            /*
            dd([
                'clave' => $dataRaw,
            ]);
            */

            // Llenar hoja

            if ($data !== null && count($data) !== 0) {

                $exporter->fillSheetFromConfig($spreadsheet, $configHoja, $data);

                $hojasUsadas[] = $configHoja['sheet_name'];
            }
        }

        foreach ($spreadsheet->getAllSheets() as $sheet) {
            if (!in_array($sheet->getTitle(), $hojasUsadas)) {
                $spreadsheet->removeSheetByIndex(
                    $spreadsheet->getIndex($sheet)
                );
            }
        }

        // 4. DESCARGA

        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');


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

    public function uploadIncidencias(
        Request $request,
        HelperService $helper,
        IncidenciasImporter $importer,
        IncidenciasHojasValidator $hojasValidator,
        IncidenciasSaver $saver,
        IncidenciasNominaApplier $applier
    ) {

        // VALIDACIÓN BÁSICA
        $validated = $request->validate([
            'file'          => 'required|file|mimes:xlsx,xls',
            'idCliente'     => 'required|integer',
            'idEmpresa'     => 'required|integer',
            'idEsquema'     => 'required|array|min:1',
            'idTipoPeriodo' => 'required_if:fiscal,true',
            'idPeriodo'     => 'required_if:fiscal,true',
        ]);

        $idNominaGapeEmpresa = $validated['idEmpresa'];
        $idNominaGapeCliente = $validated['idCliente'];
        $idEsquema = $validated['idEsquema'];

        $mapaEsquemaConfig = [
            'Sueldo IMSS'          => 'SUELDO_IMSS',
            'Asimilados'           => 'ASIMILADOS',
            'Sindicato'            => 'SINDICATO',
            'Tarjeta facil'        => 'TARJETA_FACIL',
            'Gastos por comprobar' => 'GASTOS_POR_COMPROBAR',
        ];

        $hojasExcel = $importer->hojasExcel($request);

        // 2️⃣ Validar estructura del archivo
        try {
            $hojasValidator->validate($request, $idEsquema, $hojasExcel);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 422,
                'message' => 'El archivo Excel no coincide con los esquemas seleccionados.',
                'details' => json_decode($e->getMessage(), true),
            ], 422);
        }

        $conexion = $helper->getConexionDatabaseNGE($idNominaGapeEmpresa, 'Nom');
        $helper->setDatabaseConnection($conexion, $conexion->nombre_base);

        $result = $importer->procesarArchivo($request);

        $erroresGlobales = [];

        foreach ($result as $nombreHoja => $resultadoHoja) {

            // Si la hoja tiene errores, los acumulamos
            if (!empty($resultadoHoja->errores)) {
                foreach ($resultadoHoja->errores as $error) {
                    $erroresGlobales[] = $error;
                }
            }
        }

        if (!empty($erroresGlobales)) {

            // Estructura final
            $resultado = [];

            // Agrupar por agrupador
            foreach ($erroresGlobales as $err) {

                $agrupador = $err['agrupador'];
                $tipo      = $err['tipo'];
                $celda     = $err['columna'] . $err['fila']; // Ej: "K12"

                // Crear agrupador si no existe
                if (!isset($resultado[$agrupador])) {
                    $resultado[$agrupador] = [
                        'agrupador' => $agrupador,
                        'errores' => []
                    ];
                }

                // Buscar si ya existe este tipo dentro del agrupador
                $tipoIndex = null;
                foreach ($resultado[$agrupador]['errores'] as $idx => $item) {
                    if ($item['tipo'] === $tipo) {
                        $tipoIndex = $idx;
                        break;
                    }
                }

                // Si no existe el tipo, se crea
                if ($tipoIndex === null) {
                    $resultado[$agrupador]['errores'][] = [
                        'tipo'   => $tipo,
                        'celdas' => [$celda]
                    ];
                } else {
                    // Si ya existe, solo agregamos la celda
                    $resultado[$agrupador]['errores'][$tipoIndex]['celdas'][] = $celda;
                }
            }

            // Convertir arrays de celdas a string separado por comas
            foreach ($resultado as &$grupo) {
                foreach ($grupo['errores'] as &$err) {
                    $err['celdas'] = implode(',', $err['celdas']);
                }
            }

            return response()->json([
                'code' => 422,
                'errors' => array_values($resultado),
                'errorsRaw' => $erroresGlobales,
            ], 422);
        }

        $hojasAplicaNomina = ['SUELDO_IMSS', 'ASIMILADOS'];


        $mapaEsquemas = $hojasValidator->mapaEsquemas($request, $idEsquema);

        $mapaHojaEsquema = [];

        foreach ($mapaEsquemas as $row) {

            if (!isset($mapaEsquemaConfig[$row->esquema])) {
                continue; // seguridad
            }

            $nombreHojaTecnico = $mapaEsquemaConfig[$row->esquema];

            $mapaHojaEsquema[$nombreHojaTecnico] = [
                'id_nomina_gape_esquema' => $row->id_esquema,
                'id_nomina_gape_esquema_combinacion' => $row->combinacion,
            ];
        }

        // 2. GUARDAR MAESTRO
        $incidencia = $saver->guardarMaestro($request);

        foreach ($result as $nombreHoja => $resultadoHoja) {

            if (!isset($mapaHojaEsquema[$nombreHoja])) {
                continue;
            }

            $idsEsquema = $mapaHojaEsquema[$nombreHoja];

            foreach ($resultadoHoja->filasValidas as $row) {

                $detalle = $saver->guardarDetalle(
                    $resultadoHoja->sheet,
                    $row,
                    $incidencia->id,
                    $nombreHoja,  // 👈 MUY IMPORTANTE,
                    $idsEsquema['id_nomina_gape_esquema'],
                    $idsEsquema['id_nomina_gape_esquema_combinacion']
                );

                // SOLO APLICAR NOMINA PARA ESTAS HOJAS
                if (in_array($nombreHoja, $hojasAplicaNomina)) {
                    $applier->aplicar(
                        $resultadoHoja->sheet,
                        $row,
                        $detalle->id_empleado,
                        $request->idPeriodo,
                        $nombreHoja
                    );
                }
            }
        }

        return response()->json([
            'ok'  => true,
            'msg' => "Incidencias procesadas correctamente.",
        ]);
    }

    public function listIncidenciasPrenomina(Request $request)
    {
        try {
            $validated = $request->validate([
                'idCliente' => 'required|integer',
                'idEmpresa' => 'required|integer',
                'idTipoPeriodo' => 'required|integer',
                'idEsquema' => 'required',
            ]);

            $incidencias = NominaGapeIncidencia::from('nomina_gape_incidencia as ngi')
                ->join(
                    'nomina_gape_incidencia_detalle as ngid',
                    'ngi.id',
                    '=',
                    'ngid.id_nomina_gape_incidencia'
                )
                ->where('ngi.id_nomina_gape_cliente', $validated['idCliente'])
                ->where('ngi.id_nomina_gape_empresa', $validated['idEmpresa'])
                ->where('ngi.id_tipo_periodo', $validated['idTipoPeriodo'])
                ->where('ngid.id_nomina_gape_combinacion', $validated['idEsquema'])
                ->groupBy('ngi.id', 'ngi.created_at')
                ->select([
                    'ngi.id as id',
                    DB::raw(
                        "CONVERT(VARCHAR(16), ngi.created_at, 103) + ' ' + CONVERT(VARCHAR(5), ngi.created_at, 108)
                        AS titulo_incidencia"
                    ),
                    DB::raw("CONCAT(COUNT(ngid.id), ' incidencia(s) cargada(s)') AS descripcion_incidencia"),
                ])
                ->get();

            return response()->json([
                'code' => 200,
                'data' => $incidencias,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'message' => 'Error al obtener la información de la parametrización',
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
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
