<?php

namespace App\Http\Controllers\nomina;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\core\EmpresaUsuario;
use App\Models\core\EmpresaDatabase;
use App\Models\core\CoreUsuarioConexion;
use App\Models\core\Conexion;
use App\Models\core\Sistema;

use App\Models\nomina\nomGenerales\SATCatTipoContrato;
use App\Models\nomina\default\TipoPeriodo;
use App\Models\nomina\default\Departamento;
use App\Models\nomina\default\Puesto;
use App\Models\nomina\default\TipoPrestacion;
use App\Models\nomina\default\Turno;
use App\Models\nomina\nomGenerales\SATCatTipoRegimen;
use App\Models\nomina\default\RegistroPatronal;
use App\Models\nomina\nomGenerales\SATCatEntidadFederativa;
use App\Models\nomina\nomGenerales\SATCatBancos;
use App\Models\nomina\default\Empresa;
use App\Models\nomina\nomGenerales\IMSSCatTipoSemanaReducida;
use App\Models\nomina\default\Empleado;

use App\Http\Controllers\core\HelperController;

class CatalogosController extends Controller
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

    public function empresasNominas(Request $request)
    {
        // $idEmpresaUsuario = $request->user()->id

        $idEmpresaUsuario = 3;

        try {

            $empresas = EmpresaUsuario::select(
                'empresa_database.id ',
                'empresa_database.nombre_empresa',
                'empresa_database.nombre_base'
            )
                ->join('empresa_usuario_database', 'empresa_usuario.id', '=', 'empresa_usuario_database.id_empresa_usuario')
                ->join('empresa_database', 'empresa_usuario_database.id_empresa_database', '=', 'empresa_database.id')
                ->join('core_usuario_conexion', 'empresa_database.id_conexion', '=', 'core_usuario_conexion.id_conexion')
                ->join('conexion', 'empresa_database.id_conexion', '=', 'conexion.id')
                ->join('sistema', 'conexion.id_sistema', '=', 'sistema.id')
                ->where('core_usuario_conexion.estado', 1)
                ->where('empresa_database.estado', 1)
                ->where('empresa_usuario_database.estado', 1)
                ->where('sistema.codigo', '=', 'Nom')
                ->where('sistema.estado', 1)
                ->where('empresa_usuario.id', $idEmpresaUsuario)
                ->get();

            return response()->json([
                'code' => 200,
                'data' => $empresas,
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

    public function tipoContrato(Request $request)
    {
        // $idEmpresaUsuario = $request->user()->id
        $idEmpresaUsuario = 3;
        $idEmpresaDatabase =  $request->id;

        $conexion = $this->helperController->getConexionDatabase($idEmpresaDatabase, $idEmpresaUsuario);

        $this->helperController->setDatabaseConnection($conexion, $conexion->database_maestra);

        try {
            $tipoContrato = SATCatTipoContrato::select(
                'ClaveTipoContrato',
                'Descripcion'
            )
                ->get();

            return response()->json([
                'code' => 200,
                'data' => $tipoContrato,
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

    public function tipoPeriodo(Request $request)
    {
        // $idEmpresaUsuario = $request->user()->id
        $idEmpresaUsuario = 3;
        $idEmpresaDatabase =  $request->id;

        $conexion = $this->helperController->getConexionDatabase($idEmpresaDatabase, $idEmpresaUsuario);

        $this->helperController->setDatabaseConnection($conexion, $conexion->nombre_base);

        try {
            $tipoPeriodo = TipoPeriodo::select(
                'idtipoperiodo',
                'nombretipoperiodo'
            )
                ->get();

            return response()->json([
                'code' => 200,
                'data' => $tipoPeriodo,
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

    public function departamento(Request $request)
    {
        // $idEmpresaUsuario = $request->user()->id
        $idEmpresaUsuario = 3;
        $idEmpresaDatabase =  $request->id;

        $conexion = $this->helperController->getConexionDatabase($idEmpresaDatabase, $idEmpresaUsuario);

        $this->helperController->setDatabaseConnection($conexion, $conexion->nombre_base);

        try {
            $departamento = Departamento::select(
                'iddepartamento',
                'descripcion'
            )
                ->get();

            return response()->json([
                'code' => 200,
                'data' => $departamento,
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

    public function puesto(Request $request)
    {
        // $idEmpresaUsuario = $request->user()->id
        $idEmpresaUsuario = 3;
        $idEmpresaDatabase =  $request->id;

        $conexion = $this->helperController->getConexionDatabase($idEmpresaDatabase, $idEmpresaUsuario);

        $this->helperController->setDatabaseConnection($conexion, $conexion->nombre_base);

        try {
            $departamento = Puesto::select(
                'idpuesto',
                'descripcion'
            )
                ->get();

            return response()->json([
                'code' => 200,
                'data' => $departamento,
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

    public function tipoPrestacion(Request $request)
    {
        // $idEmpresaUsuario = $request->user()->id
        $idEmpresaUsuario = 3;
        $idEmpresaDatabase =  $request->id;

        $conexion = $this->helperController->getConexionDatabase($idEmpresaDatabase, $idEmpresaUsuario);

        $this->helperController->setDatabaseConnection($conexion, $conexion->nombre_base);

        try {
            $tipoPrestacion = TipoPrestacion::select(
                'IDTabla',
                'Nombre'
            )
                ->get();

            return response()->json([
                'code' => 200,
                'data' => $tipoPrestacion,
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

    public function turno(Request $request)
    {
        // $idEmpresaUsuario = $request->user()->id
        $idEmpresaUsuario = 3;
        $idEmpresaDatabase =  $request->id;

        $conexion = $this->helperController->getConexionDatabase($idEmpresaDatabase, $idEmpresaUsuario);

        $this->helperController->setDatabaseConnection($conexion, $conexion->nombre_base);

        try {
            $turno = Turno::select(
                'idturno',
                'descripcion'
            )
                ->get();

            return response()->json([
                'code' => 200,
                'data' => $turno,
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

    public function tipoRegimen(Request $request)
    {
        // $idEmpresaUsuario = $request->user()->id
        $idEmpresaUsuario = 3;
        $idEmpresaDatabase =  $request->id;

        $conexion = $this->helperController->getConexionDatabase($idEmpresaDatabase, $idEmpresaUsuario);

        $this->helperController->setDatabaseConnection($conexion, $conexion->database_maestra);

        try {
            $tipoRegimen = SATCatTipoRegimen::select(
                'claveTipoRegimen',
                'descripcion'
            )
                ->get();

            return response()->json([
                'code' => 200,
                'data' => $tipoRegimen,
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

    public function registroPatronal(Request $request)
    {
        // $idEmpresaUsuario = $request->user()->id
        $idEmpresaUsuario = 3;
        $idEmpresaDatabase =  $request->id;

        $conexion = $this->helperController->getConexionDatabase($idEmpresaDatabase, $idEmpresaUsuario);

        $this->helperController->setDatabaseConnection($conexion, $conexion->nombre_base);

        try {
            $registroPatronal = RegistroPatronal::select(
                'cidregistropatronal',
                'cregistroimss'
            )
                ->get();

            return response()->json([
                'code' => 200,
                'data' => $registroPatronal,
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

    public function entidadFederativa(Request $request)
    {
        // $idEmpresaUsuario = $request->user()->id
        $idEmpresaUsuario = 3;
        $idEmpresaDatabase =  $request->id;

        $conexion = $this->helperController->getConexionDatabase($idEmpresaDatabase, $idEmpresaUsuario);

        $this->helperController->setDatabaseConnection($conexion, $conexion->database_maestra);

        try {
            $entidadFederativa = SATCatEntidadFederativa::select(
                'ClaveEstado',
                'Descripcion'
            )
                ->where('ClavePais', '=', 'MEX')
                ->get();

            return response()->json([
                'code' => 200,
                'data' => $entidadFederativa,
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

    public function bancos(Request $request)
    {
        // $idEmpresaUsuario = $request->user()->id
        $idEmpresaUsuario = 3;
        $idEmpresaDatabase =  $request->id;

        $conexion = $this->helperController->getConexionDatabase($idEmpresaDatabase, $idEmpresaUsuario);

        $this->helperController->setDatabaseConnection($conexion, $conexion->database_maestra);

        try {
            $banco = SATCatBancos::select(
                'ClaveBanco',
                'Descripcion'
            )
                ->get();

            return response()->json([
                'code' => 200,
                'data' => $banco,
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

    public function empresa(Request $request)
    {
        // $idEmpresaUsuario = $request->user()->id
        $idEmpresaUsuario = 3;
        $idEmpresaDatabase =  $request->id;

        $conexion = $this->helperController->getConexionDatabase($idEmpresaDatabase, $idEmpresaUsuario);

        $this->helperController->setDatabaseConnection($conexion, $conexion->nombre_empresa);

        try {
            $empresa = Empresa::select(
                'nombrecorto',
                'mascarillacodigo',
                'zonasalariogeneral',
                'tipocodigoempleado'
            )
                ->first();

            $mascarilla = $empresa->mascarillacodigo ?? 'XXXX';
            $tipo = $empresa->tipocodigoempleado ?? 'A';
            $longitud = substr_count($mascarilla, 'X');

            $ultimo = Empleado::orderBy('codigoempleado', 'desc')->value('codigoempleado');

            $siguiente = $this->generarSiguienteCodigo($ultimo, $longitud, $tipo);

            return response()->json([
                'code' => 200,
                'data' => [
                    'empresa' => $empresa,
                    'siguienteCodigo' => $siguiente,
                ]
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

    public function tipoJornada(Request $request)
    {
        // $idEmpresaUsuario = $request->user()->id
        $idEmpresaUsuario = 3;
        $idEmpresaDatabase =  $request->id;

        $conexion = $this->helperController->getConexionDatabase($idEmpresaDatabase, $idEmpresaUsuario);

        $this->helperController->setDatabaseConnection($conexion, $conexion->database_maestra);

        try {
            $tipoJornada = IMSSCatTipoSemanaReducida::select(
                'TipoSemanaReducida',
                'Descripcion'
            )
                ->get();

            return response()->json([
                'code' => 200,
                'data' => $tipoJornada,
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

    private function generarSiguienteCodigo(?string $ultimo, int $longitud, string $tipo): string
    {
        $charset = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $base = strlen($charset);

        // Convertidor de base alfanumérica a decimal
        $toDecimal = function (string $input) use ($charset, $base): int {
            $input = strtoupper($input);
            $decimal = 0;
            for ($i = 0; $i < strlen($input); $i++) {
                $decimal *= $base;
                $decimal += strpos($charset, $input[$i]);
            }
            return $decimal;
        };

        // Convertidor de decimal a base alfanumérica
        $toAlphanumeric = function (int $number) use ($charset, $base): string {
            $result = '';
            do {
                $result = $charset[$number % $base] . $result;
                $number = intdiv($number, $base);
            } while ($number > 0);
            return $result;
        };

        $start = $ultimo
            ? ($tipo === 'N' ? intval($ultimo) : $toDecimal($ultimo))
            : 0;

        $intento = $start;

        // Intentar encontrar un código que no exista
        do {
            $intento++;
            $codigo = $tipo === 'N'
                ? str_pad((string)$intento, $longitud, '0', STR_PAD_LEFT)
                : str_pad($toAlphanumeric($intento), $longitud, '0', STR_PAD_LEFT);

            $existe = Empleado::where('codigoempleado', $codigo)->exists();

            if (!$existe) return $codigo;
        } while ($intento < pow($base, $longitud)); // evitar bucle infinito

        throw new \Exception('No hay códigos disponibles');
    }
}
