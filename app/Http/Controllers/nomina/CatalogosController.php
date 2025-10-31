<?php

namespace App\Http\Controllers\nomina;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\core\EmpresaUsuario;
use App\Models\core\EmpresaDatabase;
use App\Models\core\CoreUsuarioConexion;
use App\Models\core\Conexion;
use App\Models\core\Sistema;

use App\Models\nomina\default\TipoPeriodo;
use App\Models\nomina\default\Periodo;
use App\Models\nomina\default\Departamento;
use App\Models\nomina\default\Puesto;
use App\Models\nomina\default\TipoPrestacion;
use App\Models\nomina\default\Turno;
use App\Models\nomina\default\RegistroPatronal;
use App\Models\nomina\default\Empresa;
use App\Models\nomina\default\Empleado;
use App\Models\nomina\nomGenerales\SATCatTipoContrato;
use App\Models\nomina\nomGenerales\SATCatTipoRegimen;
use App\Models\nomina\nomGenerales\SATCatEntidadFederativa;
use App\Models\nomina\nomGenerales\SATCatBancos;
use App\Models\nomina\nomGenerales\IMSSCatTipoSemanaReducida;
use App\Models\nomina\nomGenerales\NominaEmpresa;

use App\Models\nomina\GAPE\NominaGapeCliente;
use App\Models\nomina\GAPE\NominaGapeParametrizacion;

use App\Http\Controllers\core\HelperController;

class CatalogosController extends Controller
{

    protected $helperController;

    public function __construct(helperController $helperController)
    {
        $this->helperController = $helperController;
    }

    public function sincronizarEmpresasNomGemerales()
    {
        $conexion = Conexion::select(
            'id',
            'usuario',
            'password',
            'ip',
            'puerto',
            'host'
        )
            ->first();

        $this->helperController->setDatabaseConnection($conexion, 'nomGenerales');

        $empresasGenerales = NominaEmpresa::select(
            'IDEmpresa',
            'NombreEmpresa',
            'NombreCorto',
            'RutaEmpresa'
        )
            ->where('RutaEmpresa', '!=', '')
            ->get();

        foreach ($empresasGenerales as $empresa) {
            // Validamos si ya existe la RutaEmpresa como nombre_base
            $yaExiste = EmpresaDatabase::where('nombre_base', $empresa->RutaEmpresa)->exists();

            if (!$yaExiste) {
                EmpresaDatabase::create([
                    'estado'            => 1, // o el valor por default que uses
                    'usuario_modificador' => null,
                    'id_conexion'       => $conexion->id,
                    'nombre_base'       => $empresa->RutaEmpresa,
                    'nombre_empresa'    => $empresa->NombreEmpresa,
                    'created_at'        => now(),
                    'updated_at'        => now(),
                ]);
            }
        }

        return response()->json(['message' => 'Empresas sincronizadas correctamente.']);
    }

    /**
     * Obtiene un catálogo de nómina (periodos, contratos, régimen, etc.)
     * usando conexión dinámica por empresa_database (NGE)
     */
    public function obtenerCatalogoNominaNGE(Request $request, string $modelo, array $columnas)
    {
        try {
            // 1️⃣ Validar parámetro de empresa
            $validated = $request->validate([
                'id' => 'required|integer',
            ]);

            $idNominaGapeEmpresa = $validated['id'];

            // 2️⃣ Obtener conexión desde empresa_database
            $conexion = $this->helperController->getConexionDatabaseNGE($idNominaGapeEmpresa, 'Nom');
            $this->helperController->setDatabaseConnection($conexion, $conexion->nombre_base);

            // 3️⃣ Verificar que el modelo exista
            if (!class_exists($modelo)) {
                throw new \Exception("El modelo {$modelo} no existe.");
            }

            // 4️⃣ Consultar el catálogo solicitado
            $data = $modelo::select($columnas)->get();

            // 5️⃣ Retornar respuesta uniforme
            return response()->json([
                'code' => 200,
                'data' => $data,
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'code' => 422,
                'message' => 'Datos de entrada inválidos.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'message' => 'Error al obtener datos del catálogo.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    /*
    public function tipoPeriodoNGE(Request $request)
    {
        return $this->obtenerCatalogoNominaNGE($request, TipoPeriodo::class, [
            'idtipoperiodo',
            'nombretipoperiodo'
        ]);
    }
        */


    public function tipoPeriodoNGE(Request $request)
    {
        try {
            // 1️⃣ Validar parámetros de entrada
            $validated = $request->validate([
                'id' => 'required|integer',          // id de la empresa
                'idCliente' => 'required|integer',   // id del cliente
                'action' => 'required|string',       // 'new' o 'update'
            ]);

            $idNominaGapeEmpresa = $validated['id'];
            $idNominaGapeCliente = $validated['idCliente'];
            $action = strtolower($validated['action']); // normalize case

            // 2️⃣ Obtener los tipos de periodo ya configurados para esa empresa
            $tiposExistentes = NominaGapeParametrizacion::where('id_nomina_gape_cliente', $idNominaGapeCliente)
                ->where('id_nomina_gape_empresa', $idNominaGapeEmpresa)
                ->pluck('id_tipo_periodo')
                ->filter()
                ->toArray();

            // 3️⃣ Conectarse a la base de datos de nómina (según empresa)
            $conexion = $this->helperController->getConexionDatabaseNGE($idNominaGapeEmpresa, 'Nom');
            $this->helperController->setDatabaseConnection($conexion, $conexion->nombre_base);

            // 4️⃣ Obtener todos los tipos de periodo desde la base NGE
            $tipoPeriodo = TipoPeriodo::select('idtipoperiodo', 'nombretipoperiodo')->get();

            // 5️⃣ Filtrar según acción
            if ($action === 'new') {
                // Solo mostrar los que NO estén ya registrados
                $tipoPeriodo = $tipoPeriodo->filter(function ($item) use ($tiposExistentes) {
                    return !in_array($item->idtipoperiodo, $tiposExistentes);
                })->values();
            }
            // Si es "update", no se filtra (se devuelven todos)

            // 6️⃣ Estructurar respuesta
            return response()->json([
                'code' => 200,
                'data' => $tipoPeriodo,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'code' => 422,
                'message' => 'Datos de entrada inválidos.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'message' => 'Error al obtener datos del catálogo de periodos.',
                'error' => $e->getMessage(),
            ], 500);
        }
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

    public function gapeCliente(Request $request)
    {
        try {
            $cliente = NominaGapeCliente::select(
                'id',
                'nombre',
                'codigo',
            )
                ->get();

            return response()->json([
                'code' => 200,
                'data' => $cliente,
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

        $conexion = $this->helperController->getConexionDatabase($idEmpresaDatabase, $idEmpresaUsuario, 'Nom');

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
        try {
            // 1️⃣ Obtener el idEmpresaUsuario según sea superadmin o usuario normal
            if ($request->boolean('superadmin')) {
                // Buscar el primer usuario asociado a la empresa "GAPE"
                $empresaUsuario = EmpresaUsuario::select('empresa_usuario.id')
                    ->join('empresa', 'empresa_usuario.id_empresa', '=', 'empresa.id')
                    ->where('empresa.nombre', 'GAPE')
                    ->first();

                if (!$empresaUsuario) {
                    return response()->json([
                        'code' => 404,
                        'message' => 'No se encontró la empresa GAPE o su usuario asociado.',
                    ], 404);
                }

                $idEmpresaUsuario = $empresaUsuario->id;
            } else {
                // Si no es superadmin, usar el usuario autenticado o fallback
                $idEmpresaUsuario = optional($request->user())->id ?? 3;
            }

            // 2️⃣ Validar parámetro id de empresa
            $validated = $request->validate([
                'id' => 'required|integer',
            ]);
            $idEmpresaDatabase = $validated['id'];

            // 3️⃣ Conectarse a la base de datos dinámica de nómina
            $conexion = $this->helperController->getConexionDatabase($idEmpresaDatabase, $idEmpresaUsuario, 'Nom');
            $this->helperController->setDatabaseConnection($conexion, $conexion->nombre_base);

            // 4️⃣ Obtener tipos de periodo
            $tipoPeriodo = TipoPeriodo::select('idtipoperiodo', 'nombretipoperiodo')->get();

            // 5️⃣ Devolver respuesta exitosa
            return response()->json([
                'code' => 200,
                'data' => $tipoPeriodo,
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'code' => 422,
                'message' => 'Datos de entrada inválidos.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'message' => 'Error al obtener los datos del tipo de periodo.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    public function periodo(Request $request)
    {
        // $idEmpresaUsuario = $request->user()->id
        $idEmpresaUsuario = 3;
        $idEmpresaDatabase =  $request->id;
        $idTipoPeriodo =  $request->idTipoPeriodo;

        $conexion = $this->helperController->getConexionDatabase($idEmpresaDatabase, $idEmpresaUsuario, 'Nom');

        $this->helperController->setDatabaseConnection($conexion, $conexion->nombre_base);

        try {
            $periodo = Periodo::select(
                'idperiodo',
                'numeroperiodo',
                'ejercicio',
                'mes',
                'fechainicio',
                'fechafin',
            )
                ->where('idtipoperiodo', $idTipoPeriodo)
                ->get();

            return response()->json([
                'code' => 200,
                'data' => $periodo,
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

        $conexion = $this->helperController->getConexionDatabase($idEmpresaDatabase, $idEmpresaUsuario, 'Nom');

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

        $conexion = $this->helperController->getConexionDatabase($idEmpresaDatabase, $idEmpresaUsuario, 'Nom');

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

        $conexion = $this->helperController->getConexionDatabase($idEmpresaDatabase, $idEmpresaUsuario, 'Nom');

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

        $conexion = $this->helperController->getConexionDatabase($idEmpresaDatabase, $idEmpresaUsuario, 'Nom');

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

        $conexion = $this->helperController->getConexionDatabase($idEmpresaDatabase, $idEmpresaUsuario, 'Nom');

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

        $conexion = $this->helperController->getConexionDatabase($idEmpresaDatabase, $idEmpresaUsuario, 'Nom');

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

        $conexion = $this->helperController->getConexionDatabase($idEmpresaDatabase, $idEmpresaUsuario, 'Nom');

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

        $conexion = $this->helperController->getConexionDatabase($idEmpresaDatabase, $idEmpresaUsuario, 'Nom');

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

        $conexion = $this->helperController->getConexionDatabase($idEmpresaDatabase, $idEmpresaUsuario, 'Nom');

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

        $conexion = $this->helperController->getConexionDatabase($idEmpresaDatabase, $idEmpresaUsuario, 'Nom');

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
