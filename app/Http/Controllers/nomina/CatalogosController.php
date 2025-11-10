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
use App\Models\nomina\GAPE\NominaGapeEmpleado;
use App\Models\nomina\GAPE\NominaGapeParametrizacion;

use App\Http\Controllers\core\HelperController;
use App\Models\nomina\GAPE\NominaGapeEmpresa;

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
    public function obtenerCatalogoNominaNGE(Request $request, ?string $nombreBase, string $modelo, array $columnas, array $filtros = [])
    {
        try {
            // 1️⃣ Validar parámetro de empresa
            $validated = $request->validate([
                'idEmpresa' => 'required|integer',
            ]);

            $idNominaGapeEmpresa = $validated['idEmpresa'];

            // 2️⃣ Obtener conexión desde empresa_database
            $conexion = $this->helperController->getConexionDatabaseNGE($idNominaGapeEmpresa, 'Nom');

            // 3️⃣ Si no se especifica una base, usar la base por defecto del cliente
            if (empty($nombreBase)) {
                $nombreBase = $conexion->nombre_base; // 👈 usa la base normal por defecto
            }

            // 4️⃣ Cambiar la conexión
            $this->helperController->setDatabaseConnection($conexion, $nombreBase);

            // 5️⃣ Verificar que el modelo exista
            if (!class_exists($modelo)) {
                throw new \Exception("El modelo {$modelo} no existe.");
            }

            // 6️⃣ Construir la consulta
            $query = $modelo::select($columnas);

            // 7️⃣ Aplicar filtros dinámicos si existen
            if (!empty($filtros)) {
                foreach ($filtros as $columna => $valor) {
                    $query->where($columna, '=', $valor);
                }
            }

            // 8️⃣ Ejecutar la consulta
            $data = $query->get();


            // 7️⃣ Retornar respuesta uniforme
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




    public function tipoContrato(Request $request)
    {
        return $this->obtenerCatalogoNominaNGE($request, 'nomGenerales', SATCatTipoContrato::class, [
            'ClaveTipoContrato',
            'Descripcion'
        ]);
    }

    public function tipoPeriodo(Request $request)
    {
        return $this->obtenerCatalogoNominaNGE($request, '', TipoPeriodo::class, [
            'idtipoperiodo',
            'nombretipoperiodo'
        ]);
    }

    public function departamento(Request $request)
    {
        return $this->obtenerCatalogoNominaNGE($request, '', Departamento::class, [
            'iddepartamento',
            'descripcion'
        ]);
    }

    public function puesto(Request $request)
    {
        return $this->obtenerCatalogoNominaNGE($request, '', Puesto::class, [
            'idpuesto',
            'descripcion'
        ]);
    }

    public function tipoPrestacion(Request $request)
    {
        return $this->obtenerCatalogoNominaNGE($request, '', TipoPrestacion::class, [
            'IDTabla',
            'Nombre'
        ]);
    }

    public function turno(Request $request)
    {
        return $this->obtenerCatalogoNominaNGE($request, '', Turno::class, [
            'idturno',
            'descripcion'
        ]);
    }

    public function tipoRegimen(Request $request)
    {
        return $this->obtenerCatalogoNominaNGE($request, 'nomGenerales', SATCatTipoRegimen::class, [
            'claveTipoRegimen',
            'descripcion'
        ]);
    }

    public function registroPatronal(Request $request)
    {
        return $this->obtenerCatalogoNominaNGE($request, '', RegistroPatronal::class, [
            'cidregistropatronal',
            'cregistroimss'
        ]);
    }

    public function entidadFederativa(Request $request)
    {
        return $this->obtenerCatalogoNominaNGE(
            $request,
            'nomGenerales',
            SATCatEntidadFederativa::class,
            [
                'ClaveEstado',
                'Descripcion'
            ],
            ['ClavePais' => 'MEX']
        );
    }

    public function bancos(Request $request)
    {
        return $this->obtenerCatalogoNominaNGE($request, 'nomGenerales', SATCatBancos::class, [
            'ClaveBanco',
            'Descripcion'
        ]);
    }

    public function tipoJornada(Request $request)
    {
        return $this->obtenerCatalogoNominaNGE($request, 'nomGenerales', IMSSCatTipoSemanaReducida::class, [
            'TipoSemanaReducida',
            'Descripcion'
        ]);
    }

    public function empresa(Request $request)
    {
        try {
            $validated = $request->validate([
                'idEmpresa' => 'required|integer',
            ]);

            $idNominaGapeEmpresa = $validated['idEmpresa'];

            // 2️⃣ Obtener conexión desde empresa_database
            $conexion = $this->helperController->getConexionDatabaseNGE($idNominaGapeEmpresa, 'Nom');

            $this->helperController->setDatabaseConnection($conexion, $conexion->nombre_base);

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

    public function sigCodigoPorEmpresa(Request $request)
    {
        try {
            $validated = $request->validate([
                'idEmpresa' => 'required|integer',
                'idCliente' => 'required|integer',
                'fiscal' => 'required|boolean',
            ]);

            $idEmpresa = $validated['idEmpresa'];
            $idCliente = $validated['idCliente'];

            // 1️⃣ Obtener configuración de empresa
            $empresa = NominaGapeEmpresa::select(
                'mascara_codigo',
                'codigo_inicial',
                'codigo_actual'
            )
                ->where('id', $idEmpresa)
                ->first();

            if (!$empresa) {
                return response()->json([
                    'code' => 404,
                    'message' => 'No se encontró la empresa especificada',
                ], 404);
            }

            $mascarilla = $empresa->mascara_codigo ?? 'XXXX';
            $longitud = substr_count($mascarilla, 'X');

            // Si no tiene longitud válida
            if ($longitud < 1) {
                return response()->json([
                    'code' => 422,
                    'message' => 'La máscara de código no es válida',
                ], 422);
            }

            // 2️⃣ Buscar el último código de empleado registrado
            $ultimoCodigo = NominaGapeEmpleado::where('id_nomina_gape_empresa', $idEmpresa)
                ->where('id_nomina_gape_cliente', $idCliente)
                ->orderBy('codigoempleado', 'desc')
                ->value('codigoempleado');

            // 3️⃣ Determinar el punto de partida
            $baseCodigo = $ultimoCodigo ?? $empresa->codigo_actual ?? $empresa->codigo_inicial ?? str_pad('1', $longitud, '0', STR_PAD_LEFT);

            // Asegurar formato correcto (rellenar con ceros)
            $baseCodigo = str_pad(preg_replace('/\D/', '', $baseCodigo), $longitud, '0', STR_PAD_LEFT);

            // Convertir a entero para incrementar
            $siguienteNum = intval($baseCodigo);

            // 4️⃣ Generar siguiente disponible
            $maxIntentos = 9999; // evitar bucles infinitos
            $encontrado = false;

            for ($i = 0; $i < $maxIntentos; $i++) {
                $siguienteNum++;
                $nuevoCodigo = str_pad($siguienteNum, $longitud, '0', STR_PAD_LEFT);

                // Verificar si ya existe
                $existe = NominaGapeEmpleado::where('id_nomina_gape_empresa', $idEmpresa)
                    ->where('id_nomina_gape_cliente', $idCliente)
                    ->where('codigoempleado', $nuevoCodigo)
                    ->exists();

                if (!$existe) {
                    $encontrado = true;
                    break;
                }
            }

            if (!$encontrado) {
                return response()->json([
                    'code' => 500,
                    'message' => 'No se pudo generar un nuevo código único después de múltiples intentos',
                ], 500);
            }

            // 5️⃣ Retornar el siguiente código disponible
            return response()->json([
                'code' => 200,
                'data' => [
                    'empresa' => $empresa,
                    'siguienteCodigo' => $nuevoCodigo,
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'message' => 'Error al obtener el siguiente código',
                'error' => $e->getMessage(),
            ], 500);
        }
    }



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

    public function tipoContratoxxx(Request $request)
    {
        try {
            $validated = $request->validate([
                'idCliente' => 'required|integer',          // id del cliente
                'idEmpresa' => 'required|integer',   // id de la empresa
            ]);

            $idNominaGapeCliente = $validated['idCliente'];
            $idNominaGapeEmpresa = $validated['idEmpresa'];

            $conexion = $this->helperController->getConexionDatabaseNGE($idNominaGapeEmpresa, 'Nom');
            $this->helperController->setDatabaseConnection($conexion, $conexion->database_maestra);

            $tipoContrato = SATCatTipoContrato::select(
                'ClaveTipoContrato',
                'Descripcion'
            )
                ->get();

            return response()->json([
                'code' => 200,
                'data' => $tipoContrato,
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

    public function tipoPeriodoxxx(Request $request)
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

    public function departamentoxxx(Request $request)
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

    public function puestoxxx(Request $request)
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

    public function tipoPrestacionxxx(Request $request)
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

    public function turnoxxx(Request $request)
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

    public function tipoRegimenxxx(Request $request)
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

    public function registroPatronalxxx(Request $request)
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

    public function entidadFederativaxxx(Request $request)
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

    public function bancosxxx(Request $request)
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

    public function empresaxxx(Request $request)
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

    public function tipoJornadaxxx(Request $request)
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
