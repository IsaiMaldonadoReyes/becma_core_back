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
use App\Models\nomina\GAPE\NominaGapeTipoPeriodo;
use App\Models\nomina\GAPE\NominaGapeEsquema;
use App\Models\nomina\GAPE\NominaGapeEmpresaPeriodoCombinacionParametrizacion;

use App\Http\Controllers\core\HelperController;
use App\Models\nomina\GAPE\NominaGapeEmpresa;

use Illuminate\Support\Facades\DB;

use App\Http\Services\Core\HelperService;

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
            )
                ->first();

            $mascarilla = $empresa->mascarillacodigo ?? 'XXXX';
            $longitud = substr_count($mascarilla, 'X');

            $ultimo = Empleado::orderBy('codigoempleado', 'desc')->value('codigoempleado');

            $siguiente = $this->generarSiguienteCodigo($ultimo, $longitud);

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

    public function tipoPeriodoPorClienteEmpresaDisponibles(
        Request $request,
        HelperService $helper
    ) {
        try {
            $validated = $request->validate([
                'idEmpresa' => 'required|integer',
                'idCliente' => 'required|integer',
            ]);

            $idNominaGapeEmpresa = $validated['idEmpresa'];
            $idNominaGapeCliente = $validated['idCliente'];

            // 1️⃣ Obtener parametrización real
            $periodosConfigurados = NominaGapeEmpresaPeriodoCombinacionParametrizacion::query()
                ->where('id_nomina_gape_empresa', $idNominaGapeEmpresa)
                ->where('id_nomina_gape_cliente', $idNominaGapeCliente)
                ->whereNotNull('fee')
                ->whereNotNull('base_fee')
                ->whereNotNull('provisiones')
                ->select('id_nomina_gape_tipo_periodo', 'idtipoperiodo')
                ->groupBy('id_nomina_gape_tipo_periodo', 'idtipoperiodo')
                ->get();

            // 2️⃣ Separar fiscales / no fiscales
            $fiscalesIds = $periodosConfigurados
                ->whereNotNull('idtipoperiodo')
                ->pluck('idtipoperiodo')
                ->unique();

            $noFiscalesIds = $periodosConfigurados
                ->whereNotNull('id_nomina_gape_tipo_periodo')
                ->pluck('id_nomina_gape_tipo_periodo')
                ->unique();

            // 3️⃣ Catálogo fiscal (NGE)
            $fiscales = collect();
            if ($fiscalesIds->isNotEmpty()) {
                $conexion = $helper->getConexionDatabaseNGE($idNominaGapeEmpresa, 'Nom');
                $helper->setDatabaseConnection($conexion, $conexion->nombre_base);

                $fiscales = TipoPeriodo::whereIn('idtipoperiodo', $fiscalesIds)
                    ->select('idtipoperiodo as idtipoperiodo', 'nombretipoperiodo')
                    ->get();
            }

            // 4️⃣ Catálogo NO fiscal (GAPE)
            $noFiscales = collect();
            if ($noFiscalesIds->isNotEmpty()) {
                $noFiscales = NominaGapeTipoPeriodo::whereIn('id', $noFiscalesIds)
                    ->select('id as idtipoperiodo', 'nombretipoperiodo')
                    ->get();
            }

            // 5️⃣ Resultado final
            return response()->json([
                'code' => 200,
                'data' => $fiscales->merge($noFiscales)->values(),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'code' => 500,
                'message' => 'Error al obtener los tipos de periodo.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function combinacionPorTipoPeriodoDisponibles(Request $request)
    {
        try {
            $validated = $request->validate([
                'idEmpresa'     => 'required|integer',
                'idCliente'     => 'required|integer',
                'idTipoPeriodo' => 'required|integer',
            ]);

            $idEmpresa     = $validated['idEmpresa'];
            $idCliente     = $validated['idCliente'];
            $idTipoPeriodo = $validated['idTipoPeriodo'];

            $combinaciones = DB::table('nomina_gape_cliente_esquema_combinacion as ngcec')
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
                // 🔥 AQUÍ VA EL OR
                ->where(function ($q) use ($idTipoPeriodo) {
                    $q->where('ngepcp.idtipoperiodo', $idTipoPeriodo)
                        ->orWhere('ngepcp.id_nomina_gape_tipo_periodo', $idTipoPeriodo);
                })
                ->select([
                    'ngcec.combinacion as id',
                    DB::raw(
                        "STRING_AGG(nge.esquema, ' + ')
                     WITHIN GROUP (ORDER BY nge.id) as combinacion"
                    ),
                    DB::raw(
                        "MAX(CAST(nge.contpaq AS INT)) AS contpaq"
                    ),
                ])
                ->groupBy('ngcec.combinacion')
                ->get();

            return response()->json([
                'code' => 200,
                'data' => $combinaciones,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'code' => 500,
                'message' => 'Error al obtener las combinaciones disponibles.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function esquemaPorTipoPeriodoDisponibles(Request $request)
    {
        try {
            $validated = $request->validate([
                'idEmpresa'     => 'required|integer',
                'idCliente'     => 'required|integer',
                'idTipoPeriodo' => 'required|integer',
            ]);

            $idNominaGapeEmpresa   = $validated['idEmpresa'];
            $idNominaGapeTipoPeriodo = $validated['idTipoPeriodo'];

            /*$esquemas = NominaGapeEmpresaPeriodoEsquemaParametrizacion::query()
                ->join(
                    'nomina_gape_esquema as esquema',
                    'nomina_gape_empresa_periodo_esquema_parametrizacion.id_nomina_gape_esquema',
                    '=',
                    'esquema.id'
                )
                ->where('nomina_gape_empresa_periodo_esquema_parametrizacion.id_nomina_gape_empresa', $idNominaGapeEmpresa)
                ->whereNotNull('nomina_gape_empresa_periodo_esquema_parametrizacion.fee')
                ->whereNotNull('nomina_gape_empresa_periodo_esquema_parametrizacion.base_fee')
                ->whereNotNull('nomina_gape_empresa_periodo_esquema_parametrizacion.provisiones')
                ->where(function ($q) use ($idNominaGapeTipoPeriodo) {
                    $q->where(
                        'nomina_gape_empresa_periodo_esquema_parametrizacion.id_nomina_gape_tipo_periodo',
                        $idNominaGapeTipoPeriodo
                    )->orWhere(
                        'nomina_gape_empresa_periodo_esquema_parametrizacion.idtipoperiodo',
                        $idNominaGapeTipoPeriodo
                    );
                })
                ->select(
                    'esquema.id',
                    'esquema.esquema',
                    'esquema.contpaq',
                )
                ->groupBy(
                    'esquema.id',
                    'esquema.esquema',
                    'esquema.contpaq'
                )
                ->get();*/

            $esquemas = NominaGapeEsquema::query()
                ->join(
                    'nomina_gape_empresa_periodo_combinacion_parametrizacion as parame',
                    'parame.id_nomina_gape_esquema',
                    '=',
                    'nomina_gape_esquema.id'
                )
                ->where('parame.id_nomina_gape_empresa', $idNominaGapeEmpresa)
                ->whereNotNull('parame.fee')
                ->whereNotNull('parame.base_fee')
                ->whereNotNull('parame.provisiones')
                ->where(function ($q) use ($idNominaGapeTipoPeriodo) {
                    $q->where('parame.id_nomina_gape_tipo_periodo', $idNominaGapeTipoPeriodo)
                        ->orWhere('parame.idtipoperiodo', $idNominaGapeTipoPeriodo);
                })
                ->select(
                    'nomina_gape_esquema.id',
                    'nomina_gape_esquema.esquema',
                    'nomina_gape_esquema.contpaq'
                )
                ->groupBy(
                    'nomina_gape_esquema.id',
                    'nomina_gape_esquema.esquema',
                    'nomina_gape_esquema.contpaq'
                )
                ->get();

            return response()->json([
                'code' => 200,
                'data' => $esquemas,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'code' => 500,
                'message' => 'Error al obtener los esquemas por tipo de periodo.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function esquemaPorEmpresaDisponibles(Request $request)
    {
        try {
            $validated = $request->validate([
                'idEmpresa'     => 'required|integer',
                'idCliente'     => 'required|integer',
            ]);

            $idNominaGapeEmpresa   = $validated['idEmpresa'];
            $idNominaGapeCliente   = $validated['idCliente'];

            $esquemas = NominaGapeEsquema::select(
                'nomina_gape_esquema.id',
                'nomina_gape_esquema.esquema',
                'nomina_gape_esquema.contpaq'
            )
                ->join('nomina_gape_cliente_esquema_combinacion', 'nomina_gape_esquema.id', '=', 'nomina_gape_cliente_esquema_combinacion.id_nomina_gape_esquema')
                ->where('nomina_gape_cliente_esquema_combinacion.id_nomina_gape_empresa', $idNominaGapeEmpresa)
                ->where('nomina_gape_cliente_esquema_combinacion.id_nomina_gape_cliente', $idNominaGapeCliente)
                ->where('nomina_gape_cliente_esquema_combinacion.estado', 1)
                ->where('nomina_gape_cliente_esquema_combinacion.orden', 1)
                ->get();

            return response()->json([
                'code' => 200,
                'data' => $esquemas,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'code' => 500,
                'message' => 'Error al obtener los esquemas por tipo de periodo.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    public function tipoPeriodoPorClienteEmpresaDisponibles2(
        Request $request,
        HelperService $helper
    ) {
        try {
            // 1️⃣ Validar parámetros de entrada
            $validated = $request->validate([
                'idEmpresa' => 'required|integer',          // id de la empresa
                'idCliente' => 'required|integer',   // id del cliente
            ]);

            $idNominaGapeEmpresa = $validated['idEmpresa'];
            $idNominaGapeCliente = $validated['idCliente'];

            // 2️⃣ Obtener los tipos de periodo ya configurados para esa empresa
            $tiposExistentes = NominaGapeParametrizacion::where('id_nomina_gape_cliente', $idNominaGapeCliente)
                ->where('id_nomina_gape_empresa', $idNominaGapeEmpresa)
                ->pluck('id_tipo_periodo')
                ->filter()
                ->toArray();

            // 3️⃣ Conectarse a la base de datos de nómina (según empresa)
            $conexion = $helper->getConexionDatabaseNGE($idNominaGapeEmpresa, 'Nom');
            $helper->setDatabaseConnection($conexion, $conexion->nombre_base);

            // 4️⃣ Obtener todos los tipos de periodo desde la base NGE
            $tipoPeriodo = TipoPeriodo::select('idtipoperiodo', 'nombretipoperiodo')->get();

            $tipoPeriodoGape = NominaGapeTipoPeriodo::select('id', 'nombretipoperiodo')->get();

            // Solo mostrar los que NO estén ya registrados
            $tipoPeriodo = $tipoPeriodo->filter(function ($item) use ($tiposExistentes) {
                return in_array($item->idtipoperiodo, $tiposExistentes);
            })->values();

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

    public function ejerciciosPorTipoPeriodoPorClienteEmpresa(Request $request)
    {
        try {
            // 1️⃣ Validar parámetros de entrada
            $validated = $request->validate([
                'idEmpresa' => 'required|integer',          // id de la empresa
                'idCliente' => 'required|integer',   // id del cliente
                'idTipoPeriodo' => 'required|integer',   // id del cliente
            ]);

            $idNominaGapeEmpresa = $validated['idEmpresa'];
            $idNominaGapeCliente = $validated['idCliente'];
            $idTipoPeriodo = $validated['idTipoPeriodo'];

            // 3️⃣ Conectarse a la base de datos de nómina (según empresa)
            $conexion = $this->helperController->getConexionDatabaseNGE($idNominaGapeEmpresa, 'Nom');
            $this->helperController->setDatabaseConnection($conexion, $conexion->nombre_base);

            // 4️⃣ Obtener todos los tipos de periodo desde la base NGE
            $ejercicio = Periodo::select('ejercicio')
                ->where('idtipoperiodo', $idTipoPeriodo)
                ->groupBy('ejercicio')
                ->get();

            // Si es "update", no se filtra (se devuelven todos)

            // 6️⃣ Estructurar respuesta
            return response()->json([
                'code' => 200,
                'data' => $ejercicio,
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

    public function ejerciciosPorTipoPeriodoActivo(Request $request)
    {
        try {
            // 1️⃣ Validar parámetros de entrada
            $validated = $request->validate([
                'idEmpresa' => 'required|integer',          // id de la empresa
                'idCliente' => 'required|integer',   // id del cliente
                'idTipoPeriodo' => 'required|integer',   // id del cliente
            ]);

            $idNominaGapeEmpresa = $validated['idEmpresa'];
            $idNominaGapeCliente = $validated['idCliente'];
            $idTipoPeriodo = $validated['idTipoPeriodo'];

            // 3️⃣ Conectarse a la base de datos de nómina (según empresa)
            $conexion = $this->helperController->getConexionDatabaseNGE($idNominaGapeEmpresa, 'Nom');
            $this->helperController->setDatabaseConnection($conexion, $conexion->nombre_base);

            $ejercicio = Periodo::select('ejercicio')
                ->where('idtipoperiodo', $idTipoPeriodo)
                ->where('afectado', 0)
                ->orderBy('ejercicio')
                ->orderBy('numeroperiodo')
                ->limit(1)     // importante
                ->get();

            $ejercicioActivo = Periodo::select('idperiodo')
                ->where('idtipoperiodo', $idTipoPeriodo)
                ->where('afectado', 0)
                ->orderBy('ejercicio')
                ->orderBy('numeroperiodo')
                ->first();

            // 6️⃣ Estructurar respuesta
            return response()->json([
                'code' => 200,
                'data' => $ejercicio,
                'idPeriodo' => $ejercicioActivo->idperiodo,
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

    public function periodoPorEjercicioPorClienteEmpresa(Request $request)
    {
        try {
            // 1️⃣ Validar parámetros de entrada
            $validated = $request->validate([
                'idEmpresa' => 'required|integer',          // id de la empresa
                'idCliente' => 'required|integer',   // id del cliente
                'idTipoPeriodo' => 'required|integer',   // id del tipo de periodo
                'idEjercicio' => 'required|integer',   // ejercicio
            ]);

            $idNominaGapeEmpresa = $validated['idEmpresa'];
            $idNominaGapeCliente = $validated['idCliente'];
            $idTipoPeriodo = $validated['idTipoPeriodo'];
            $idEjercicio = $validated['idEjercicio'];

            // 3️⃣ Conectarse a la base de datos de nómina (según empresa)
            $conexion = $this->helperController->getConexionDatabaseNGE($idNominaGapeEmpresa, 'Nom');
            $this->helperController->setDatabaseConnection($conexion, $conexion->nombre_base);

            // 4️⃣ Obtener todos los tipos de periodo desde la base NGE
            $periodos = Periodo::select(
                'idperiodo',
                'numeroperiodo',
                'ejercicio',
                'mes',
                DB::raw("CONVERT(VARCHAR(10), fechainicio, 23) AS fechainicio"),
                DB::raw("CONVERT(VARCHAR(10), fechafin, 23) AS fechafin")
            )
                ->where('idtipoperiodo', $idTipoPeriodo)
                ->where('ejercicio', $idEjercicio)
                ->get();

            // 6️⃣ Estructurar respuesta
            return response()->json([
                'code' => 200,
                'data' => $periodos,
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

    private function generarSiguienteCodigo(?string $ultimo, int $longitud): string
    {
        // Si no hay empleados aún, inicia en 1
        $ultimoNumerico = $ultimo ? intval(preg_replace('/\D/', '', $ultimo)) : 0;

        $maxIntentos = 9999;
        for ($i = 0; $i < $maxIntentos; $i++) {
            $nuevo = $ultimoNumerico + $i + 1;
            $codigoNumerico = str_pad($nuevo, $longitud, '0', STR_PAD_LEFT);

            // Validar que no exista ya en la base dinámica
            $existe = Empleado::where('codigoempleado', $codigoNumerico)
                ->exists();

            if (!$existe) {
                return $codigoNumerico;
            }
        }

        throw new \Exception('No se pudo generar un nuevo código único después de múltiples intentos.');
    }
}
