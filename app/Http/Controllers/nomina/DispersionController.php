<?php

namespace App\Http\Controllers\nomina;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\FondeadoraExport;
use App\Exports\AztecaBancarioExport;
use App\Exports\AztecaInterbancarioExport;
use App\Exports\BanorteTerceroExport;

use App\Http\Controllers\core\HelperController;

class DispersionController extends Controller
{
    protected $helperController;

    public function __construct(HelperController $helperController)
    {
        $this->helperController = $helperController;
    }

    public function exportar(Request $request)
    {
        $tipo = $request->input('tipo'); // 'csv', 'tab1', 'tab2', 'txt'
        $idEmpresaDatabase = $request->input('id', 10);
        $claveId = $request->input('claveId', '142335');
        $cuentaOrigen = $request->input('cuentaOrigen', '0102087623');
        $idperiodo = $request->input('idperiodo', 470);

        $conexion = $this->helperController->getConexionDatabase($idEmpresaDatabase, 3);


        $datos = $this->obtenerDatosDispersión($idperiodo, $conexion, $tipo);

        $ordenante = $this->obtenerDatosOrdenante($conexion);

        switch ($tipo) {
            case 'fondeadora':
                return Excel::download(
                    new FondeadoraExport($datos),
                    'dispersion_fondeadora.csv',
                    \Maatwebsite\Excel\Excel::CSV
                );

            case 'azteca_bancario':
                return Excel::download(
                    new AztecaBancarioExport($datos, $cuentaOrigen, $ordenante->NombreEmpresaFiscal, $ordenante->rfc),
                    'dispersion_bancaria.xlsx',
                    \Maatwebsite\Excel\Excel::XLSX
                );

            case 'azteca_interbancario':
                return Excel::download(
                    new AztecaInterbancarioExport($datos, $cuentaOrigen, $ordenante->NombreEmpresaFiscal, $ordenante->rfc),
                    'dispersion_interbancaria.csv',
                    \Maatwebsite\Excel\Excel::XLSX
                );

            case 'banorte_tercero':
                return Excel::download(
                    new BanorteTerceroExport($datos, $claveId, $cuentaOrigen, $ordenante->rfc),
                    'dispersion_banorte_tab.xlsx',
                    \Maatwebsite\Excel\Excel::CSV
                );

            default:
                //return $this->generarArchivoTextoPlano($datos, $claveId, $cuentaOrigen);
                return response()->json(['error' => 'Tipo de layout no soportado'], 400);
        }
    }

    public function obtenerDatosDispersión($idperiodo, $conexion, $tipo)
    {
        $this->helperController->setDatabaseConnection($conexion, $conexion->nombre_base);

        $estadosEmpl = ['A', 'R'];

        return DB::table('nom10008 as d')
            ->join('nom10002 as p', 'd.idperiodo', '=', 'p.idperiodo')
            ->join('nom10001 as emp', 'd.idempleado', '=', 'emp.idempleado')
            ->join('nom10004 as c', 'd.idconcepto', '=', 'c.idconcepto')
            ->join('nom10034 as he', function ($join) {
                $join->on('d.idempleado', '=', 'he.idempleado')
                    ->on('d.idperiodo', '=', 'he.cidperiodo');
            })
            ->join('nom10003 as dep', 'he.iddepartamento', '=', 'dep.iddepartamento')
            ->where('p.idperiodo', $idperiodo)
            ->where('c.tipoconcepto', 'N')
            ->where('c.idconcepto', 1)
            ->where('d.importetotal', '>', 0)
            ->when($tipo === 'azteca_bancario', function ($query) {
                $query->where('emp.bancopagoelectronico', '=', '127');
            })
            ->whereIn('emp.estadoempleado', $estadosEmpl)
            ->select([
                'd.idperiodo',
                'emp.nombre',
                'emp.apellidopaterno',
                'emp.apellidomaterno',
                'emp.cuentapagoelectronico',
                'p.ejercicio',
                'p.numeroperiodo',
                'emp.bancopagoelectronico',
                DB::raw('ROUND(d.importetotal, 2) as importe'),
                DB::raw("emp.rfc +
                    SUBSTRING(CONVERT(char(10), emp.fechanacimiento, 126), 3, 2) +
                    SUBSTRING(CONVERT(char(10), emp.fechanacimiento, 126), 6, 2) +
                    SUBSTRING(CONVERT(char(10), emp.fechanacimiento, 126), 9, 2) +
                    emp.homoclave as rfc")
            ])
            ->get();
    }

    public function obtenerDatosOrdenante($conexion)
    {
        $this->helperController->setDatabaseConnection($conexion, $conexion->nombre_base);


        return DB::table('nom10000 AS emp')
            ->select(DB::raw("
                emp.rfc +
                SUBSTRING(CONVERT(char(10), emp.fechaconstitucion, 126), 3, 2) +
                SUBSTRING(CONVERT(char(10), emp.fechaconstitucion, 126), 6, 2) +
                SUBSTRING(CONVERT(char(10), emp.fechaconstitucion, 126), 9, 2) +
                emp.homoclave
                AS rfc,
                emp.NombreEmpresaFiscal"))
            ->first();
    }



    public function generarArchivoTextoPlano($datos, $claveId, $cuentaOrigen)
    {
        $rfcOrdenante = DB::table('nom10000 AS emp')
            ->select(DB::raw("
                emp.rfc +
                SUBSTRING(CONVERT(char(10), emp.fechaconstitucion, 126), 3, 2) +
                SUBSTRING(CONVERT(char(10), emp.fechaconstitucion, 126), 6, 2) +
                SUBSTRING(CONVERT(char(10), emp.fechaconstitucion, 126), 9, 2) +
                emp.homoclave
            AS rfc"))
            ->value('rfc');

        $lineas = [];
        $ejercicio = '';
        $numeroPeriodo = '';

        foreach ($datos as $row) {
            $ejercicio = $row->ejercicio;
            $numeroPeriodo = $row->numeroperiodo;

            $nombreCompleto = trim($row->nombre . ' ' . $row->apellidopaterno . ' ' . $row->apellidomaterno);
            $descripcion = strlen($nombreCompleto) > 30
                ? substr($nombreCompleto, 0, 30)
                : $nombreCompleto;
            $descripcion = str_replace(["\t", "\r", "\n"], ' ', $descripcion);

            $lineas[] = implode("\t", [
                '02',
                "'" . $claveId,
                "'" . $cuentaOrigen,
                "'" . $row->cuentapagoelectronico,
                number_format($row->importe, 2, '.', ''),
                '',
                $descripcion,
                $rfcOrdenante,
                '0.00',
                now()->format('Y-m-d'),
                'X',
                '',
            ]);
        }

        $contenido = implode("\r\n", $lineas);
        $nombreArchivo = 'dispersion_banorte_' . now()->format('Ymd_His') . '_ejercicio_' . $ejercicio . '_periodo_' . $numeroPeriodo . '.txt';

        return Response::make($contenido, 200, [
            'Content-Type' => 'text/plain',
            'Content-Disposition' => "attachment; filename={$nombreArchivo}",
            'Content-Length' => strlen($contenido),
        ]);
    }
}
