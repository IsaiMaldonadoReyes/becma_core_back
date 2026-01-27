<?php

namespace App\Http\Controllers\nomina;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\HttpFoundation\StreamedResponse;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

use Illuminate\Support\Facades\DB;

use App\Http\Controllers\core\HelperController;

// refactor
use App\Http\Services\Core\HelperService;

use App\Http\Services\Nomina\Export\Prenomina\ConfigFormatoPrenominaService;
use App\Http\Services\Nomina\Export\Prenomina\PrenominaQueryService;
use App\Http\Services\Nomina\Export\Prenomina\ExportPrenominaService;

class PrenominaController extends Controller
{

    protected $helperController;

    public function __construct(helperController $helperController)
    {
        $this->helperController = $helperController;
    }

    public function prenomina(
        Request $request,
        HelperService $helper,
        PrenominaQueryService $queryService,
        ExportPrenominaService $exporter
    ) {
        // ⚠ OPTIMIZACIÓN GLOBAL PARA EXCEL
        ini_set('max_execution_time', 0);
        ini_set('memory_limit', '2048M');


        $validated = $request->validate([
            'id_nomina_gape_cliente' => 'required',
            'id_nomina_gape_empresa' => 'required',
            'id_esquema'             => 'required|array|min:1',
        ]);

        $idCliente = $validated['id_nomina_gape_cliente'];
        $idEmpresa = $validated['id_nomina_gape_empresa'];
        $idEsquemas = array_map('intval', $validated['id_esquema']);

        $mapaEsquemaConfig = [
            'Sueldo IMSS'          => 'SUELDO_IMSS',
            'Asimilados'           => 'ASIMILADOS',
            'Sindicato'            => 'SINDICATO',
            'Tarjeta facil'        => 'TARJETA_FACIL',
            'Gastos por comprobar' => 'GASTOS_POR_COMPROBAR',
        ];

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
            ->select('nge.esquema', 'ngcec.combinacion AS id', 'nge.id AS id_nomina_gape_esquema', 'ngepcp.base_fee AS base_fee')
            ->get();

        if ($esquemas->isEmpty()) {
            throw new \Exception('No hay esquemas para generar el formato.');
        }

        $conexion = $helper->getConexionDatabaseNGE($idEmpresa, 'Nom');
        $helper->setDatabaseConnection($conexion, $conexion->nombre_base);

        // 1. CONFIG
        $config = ConfigFormatoPrenominaService::getConfig();

        $spreadsheet = $exporter->loadSpreadsheet($config['SUELDO_IMSS']['path']);

        $hojasUsadas = [];

        foreach ($esquemas as $row) {
            // 🔹 id de combinación (ngcec.combinacion)
            $idCombinacion = (int) $row->id;
            $baseFee = $row->base_fee;

            // 🔹 nombre BD (Sueldo IMSS, Asimilados, etc.)
            $nombreEsquema = $row->esquema;

            $clave = $mapaEsquemaConfig[$nombreEsquema] ?? null;

            if (!$clave || !isset($config[$clave])) {
                continue;
            }

            $configHoja = $config[$clave];

            // 🔹 obtener la combinación específica que viene del front
            $combo = collect($request->combinaciones)
                ->firstWhere('id_esquema', $idCombinacion);

            if (!$combo) {
                continue;
            }

            // 🔹 request contextual por hoja
            $requestCombo = new Request(array_merge(
                $request->all(),
                $combo,
                [
                    'id_nomina_gape_esquema' => $row->id_nomina_gape_esquema,
                ]
            ));

            /*
            dd([
                'row' => $requestCombo,
            ]);
            */

            $exporter->fillSheetFromConfig($spreadsheet, $configHoja, $queryService, $requestCombo, $baseFee);

            $hojasUsadas[] = $configHoja['sheet_name'];
        }

        $hojasUsadas[] = 'TOTALES';

        foreach ($spreadsheet->getAllSheets() as $sheet) {
            if (!in_array($sheet->getTitle(), $hojasUsadas)) {
                $spreadsheet->removeSheetByIndex(
                    $spreadsheet->getIndex($sheet)
                );
            }
        }

        $exporter->fillTotalesSheet($spreadsheet, $esquemas, $config, $queryService, $request);

        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');

        //$response = null;
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
}
