<?php

namespace App\Http\Controllers\nomina;

use App\Http\Controllers\Controller;
use App\Http\Requests\nomina\gape\StoreEmpleadoRequest;
use Illuminate\Http\Request;

// Importar modelos necesarios
use App\Models\nomina\GAPE\NominaGapeEmpleado; // Asegúrate de que este modelo exista
use App\Models\nomina\default\Empleado; // Asegúrate de que este modelo exista
use App\Models\nomina\default\Periodo; // Asegúrate de que este modelo exista
use App\Models\nomina\default\EmpleadosPorPeriodo; // Asegúrate de que este modelo exista

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use App\Http\Controllers\core\HelperController;

class EmpleadoController extends Controller
{
    protected $helperController;

    public function __construct(helperController $helperController)
    {
        $this->helperController = $helperController;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            // 1️⃣ Validar los campos obligatorios

            $validated = $request->validate([
                'idCliente' => 'required',
                'idEmpresa' => 'required',
                'fiscal' => 'required',
            ]);

            $idNominaGapeCliente = $validated['idCliente'];
            $idNominaGapeEmpresa = $validated['idEmpresa'];
            $fiscal = $validated['fiscal'];

            $empleados = null;

            if ($fiscal == true) {
                $conexion = $this->helperController->getConexionDatabaseNGE($idNominaGapeEmpresa, 'Nom');
                $this->helperController->setDatabaseConnection($conexion, $conexion->nombre_base);

                $empleados = Empleado::select(
                    'idempleado',
                    'codigoempleado',
                    DB::raw("LTRIM(RTRIM(nombre)) + ' ' + LTRIM(RTRIM(apellidopaterno)) + ' ' + LTRIM(RTRIM(apellidomaterno)) AS nombrelargo"),
                    DB::raw("rfc + SUBSTRING(CONVERT(char(10),fechanacimiento , 126), 3,2) + SUBSTRING(CONVERT(char(10),fechanacimiento , 126), 6,2) + SUBSTRING(CONVERT(char(10),fechanacimiento, 126), 9,2) + homoclave AS rfc"),
                    DB::raw("FORMAT(fechaalta, 'dd-MM-yyyy') as fechaalta"),

                )->get();
            } else {
                $empleados = NominaGapeEmpleado::where('id_nomina_gape_cliente', $idNominaGapeCliente)
                    ->where('id_nomina_gape_empresa', $idNominaGapeEmpresa)
                    ->select(
                        'id as idempleado',
                        'codigoempleado',
                        DB::raw("LTRIM(RTRIM(nombre)) + ' ' + LTRIM(RTRIM(apellidopaterno)) + ' ' + LTRIM(RTRIM(apellidomaterno)) AS nombrelargo"),
                        DB::raw("cuentacw AS rfc"),
                        DB::raw("FORMAT(fechaalta, 'dd-MM-yyyy') as fechaalta"),
                    )
                    ->get();
            }

            return response()->json([
                'code' => 200,
                'data' => $empleados,
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
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreEmpleadoRequest $request)
    {
        //
        try {
            DB::beginTransaction();

            $validateData = $request->validated();

            $booleanFields = [
                'calculado',
                'afectado',
                'calculadoextraordinario',
                'afectadoextraordinario',
                'interfazcheqpaqw',
                'modificacionneto',
                'calculoptu',
                'calculoaguinaldo',
                'modificacionsalarioimss',
                'altaimss',
                'bajaimss',
                'cambiocotizacionimss',
                'Subcontratacion',
                'ExtranjeroSinCURP'
            ];

            foreach ($booleanFields as $field) {
                if (!isset($validateData[$field])) {
                    $validateData[$field] = false;
                }
            }

            $empleado = NominaGapeEmpleado::create($validateData);
            // Aquí puedes agregar lógica adicional después de crear el empleado


            $validated = $request->validate([
                'id_nomina_gape_empresa' => 'required',
            ]);

            $idNominaGapeEmpresa = $validated['id_nomina_gape_empresa'];

            // 2️⃣ Obtener conexión desde empresa_database
            $conexion = $this->helperController->getConexionDatabaseNGE($idNominaGapeEmpresa, 'Nom');

            $this->helperController->setDatabaseConnection($conexion, $conexion->nombre_base);
            // ✅ 2. Guardar en tabla Empleado (ctEvent_2)
            // ✅ 1️⃣ Construcción del array base
            $empleadoData = [
                'iddepartamento' => $empleado->iddepartamento,
                'idpuesto' => $empleado->idpuesto,
                'idtipoperiodo' => $empleado->idtipoperiodo,
                'idturno' => $empleado->idturno,
                'codigoempleado' => $empleado->codigoempleado,
                'nombre' => $empleado->nombre,
                'apellidopaterno' => $empleado->apellidopaterno,
                'apellidomaterno' => $empleado->apellidomaterno,
                'nombrelargo' => $empleado->nombrelargo,
                'fechanacimiento' => $empleado->fechanacimiento,
                'lugarnacimiento' => $empleado->lugarnacimiento,
                'estadocivil' => $empleado->estadocivil,
                'sexo' => $empleado->sexo,
                'curpi' => $empleado->curpi,
                'curpf' => $empleado->curpf,
                'numerosegurosocial' => $empleado->numerosegurosocial,
                'umf' => $empleado->umf,
                'rfc' => $empleado->curpi,
                'homoclave' => $empleado->homoclave,
                'cuentapagoelectronico' => $empleado->cuentapagoelectronico,
                'sucursalpagoelectronico' => $empleado->sucursalpagoelectronico,
                'bancopagoelectronico' => $empleado->bancopagoelectronico,
                'estadoempleado' => $empleado->estadoempleado,
                'sueldodiario' => $empleado->sueldodiario,
                'fechasueldodiario' => $empleado->fechasueldodiario ?? $empleado->fechaalta,
                'sueldovariable' => $empleado->sueldovariable ?? 1,
                'fechasueldovariable' => $empleado->fechasueldovariable ?? $empleado->fechaalta,
                'sueldopromedio' => $empleado->sueldopromedio ?? 1,
                'fechasueldopromedio' => $empleado->fechasueldopromedio ?? $empleado->fechaalta,
                'sueldointegrado' => $empleado->sueldointegrado ?? 1,
                'fechasueldointegrado' => $empleado->fechasueldointegrado ?? $empleado->fechaalta,
                'calculado' => $empleado->calculado,
                'afectado' => $empleado->afectado,
                'calculadoextraordinario' => $empleado->calculadoextraordinario,
                'afectadoextraordinario' => $empleado->afectadoextraordinario,
                'interfazcheqpaqw' => $empleado->interfazcheqpaqw,
                'modificacionneto' => $empleado->modificacionneto,
                'fechaalta' => $empleado->fechaalta,
                'tipocontrato' => $empleado->tipocontrato,
                'basecotizacionimss' => $empleado->basecotizacionimss,
                'tipoempleado' => $empleado->tipoempleado,
                'basepago' => $empleado->basepago,
                'formapago' => $empleado->formapago,
                'zonasalario' => $empleado->zonasalario,
                'calculoptu' => $empleado->calculoptu ?? 1,
                'calculoaguinaldo' => $empleado->calculoaguinaldo ?? 1,
                'modificacionsalarioimss' => $empleado->modificacionsalarioimss,
                'altaimss' => $empleado->altaimss,
                'bajaimss' => $empleado->bajaimss,
                'cambiocotizacionimss' => $empleado->cambiocotizacionimss,
                'telefono' => $empleado->telefono,
                'codigopostal' => $empleado->codigopostal,
                'direccion' => $empleado->direccion,
                'poblacion' => $empleado->poblacion,
                'estado' => $empleado->estado,
                'nombrepadre' => $empleado->nombrepadre,
                'nombremadre' => $empleado->nombremadre,
                'numeroafore' => $empleado->numeroafore,
                'causabaja' => $empleado->causabaja,
                'ClabeInterbancaria' => $empleado->ClabeInterbancaria,
                'TipoRegimen' => $empleado->TipoRegimen,
                'Subcontratacion' => $empleado->Subcontratacion,
                'ExtranjeroSinCURP' => $empleado->ExtranjeroSinCURP,
                'TipoPrestacion' => $empleado->TipoPrestacion,
                'CorreoElectronico' => $empleado->CorreoElectronico,
                'DiasVacTomadasAntesdeAlta' => $empleado->DiasVacTomadasAntesdeAlta,
                'DiasPrimaVacTomadasAntesdeAlta' => $empleado->DiasPrimaVacTomadasAntesdeAlta,
                'TipoSemanaReducida' => $empleado->TipoSemanaReducida,
                'Teletrabajador' => $empleado->Teletrabajador,
                'EntidadFederativa' => $empleado->EntidadFederativa,
                'cestadoempleadoperiodo' => 'A_',
                'fechabaja' => '1899-12-30',
                'fechareingreso' => '1899-12-30',
                'cfechasueldomixto' => '1899-12-30',
                'csueldomixto' => '0',
                'cidregistropatronal' => $empleado->cidregistropatronal,
                'NumeroFonacot' => $empleado->NumeroFonacot,
                'ajustealneto' => 0,
                'sueldobaseliquidacion' => $empleado->sueldobaseliquidacion ?? 0,
            ];

            // ✅ 2️⃣ Lista de campos NVARCHAR / texto que deben reemplazar null por ''
            $stringFields = [
                'codigoempleado',
                'nombre',
                'apellidopaterno',
                'apellidomaterno',
                'nombrelargo',
                'lugarnacimiento',
                'estadocivil',
                'sexo',
                'curpi',
                'curpf',
                'rfc',
                'umf',
                'homoclave',
                'cuentapagoelectronico',
                'sucursalpagoelectronico',
                'bancopagoelectronico',
                'estadoempleado',
                'tipocontrato',
                'basecotizacionimss',
                'tipoempleado',
                'basepago',
                'formapago',
                'zonasalario',
                'telefono',
                'codigopostal',
                'direccion',
                'poblacion',
                'estado',
                'nombrepadre',
                'nombremadre',
                'numeroafore',
                'causabaja',
                'TipoRegimen',
                'CorreoElectronico',
                'ClabeInterbancaria',
                'EntidadFederativa',
                'NumeroFonacot'
            ];

            // ✅ 3️⃣ Limpiar valores NULL en campos tipo texto
            foreach ($stringFields as $field) {
                if (!isset($empleadoData[$field]) || is_null($empleadoData[$field])) {
                    $empleadoData[$field] = '';
                }
            }

            // ✅ 4️⃣ Insertar registro en base dinámica
            $empleadoInsertado = Empleado::create($empleadoData);

            $idempleado = $empleadoInsertado->idempleado;

            // guardar en la tabla NominaGapeEmpleado el idempleado generado desde Nominas
            $empleado->rfc = $empleado->curpi;
            $empleado->idempleado = $idempleado;
            $empleado->save();

            $cidPeriodo = Periodo::where('idtipoperiodo', $empleado->idtipoperiodo)
                ->where('afectado', 0)
                ->orderBy('idperiodo', 'asc')
                ->value('idperiodo');

            // Si no encuentra, lanza excepción controlada
            if (!$cidPeriodo) {
                throw new \Exception('No se encontró un periodo activo (afectado = 0) para el tipo de periodo ' . $empleado->idtipoperiodo);
            }

            // ✅ 7️⃣ Construir datos para EmpleadosPorPeriodo
            $empleadoPeriodoData = array_merge($empleadoData, [
                'idempleado' => $idempleado,
                'idtipoperiodo' => $empleado->idtipoperiodo,
                'cidperiodo' => $cidPeriodo,
                'calculado' => $empleado->calculado,
                'afectado' => $empleado->afectado,
                'calculadoextraordinario' => $empleado->calculadoextraordinario,
                'afectadoextraordinario' => $empleado->afectadoextraordinario,
                'interfazcheqpaqw' => $empleado->interfazcheqpaqw,
                'modificacionneto' => $empleado->modificacionneto,
                'modificacionsalarioimss' => $empleado->modificacionsalarioimss,
                'altaimss' => $empleado->altaimss,
                'bajaimss' => $empleado->bajaimss,
                'cambiocotizacionimss' => $empleado->cambiocotizacionimss,
                'TipoPrestacion' => $empleado->TipoPrestacion,
                'TipoSemanaReducida' => $empleado->TipoSemanaReducida,
                'Teletrabajador' => $empleado->Teletrabajador,
                'idLider' => 0, // puedes ajustar si existe jerarquía de líder
                'checkColabora' => 0, // valor por defecto
                'estadoempleado' => 'A',
            ]);

            // ✅ 8️⃣ Limpiar campos string nuevamente (para evitar null)
            foreach ($stringFields as $field) {
                if (!isset($empleadoPeriodoData[$field]) || is_null($empleadoPeriodoData[$field])) {
                    $empleadoPeriodoData[$field] = '';
                }
            }

            // ✅ 9️⃣ Insertar en EmpleadosPorPeriodo (tabla nom10034)
            EmpleadosPorPeriodo::on('sqlsrv_dynamic')->create($empleadoPeriodoData);

            DB::commit(); // ✅ Confirmar transacción

            return response()->json([
                'code' => 200,
                'message' => 'Empleado creado correctamente',
                'id' => $empleado->id,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'message' => 'Se generó un error al momento de guardar',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function storeNoFiscal(Request $request)
    {
        try {
            // 1️⃣ Validar datos de entrada
            $validated = $request->validate([
                'id_nomina_gape_cliente' => 'required|integer|exists:nomina_gape_cliente,id',
                'id_nomina_gape_empresa' => 'required|integer|exists:nomina_gape_empresa,id',
                'fiscal' => 'required|boolean',
                'codigoempleado' => [
                    'required',
                    'string',
                    Rule::unique('nomina_gape_empleado', 'codigoempleado')
                        ->where(
                            fn($query) =>
                            $query->where('id_nomina_gape_cliente', $request->id_nomina_gape_cliente)
                                ->where('id_nomina_gape_empresa', $request->id_nomina_gape_empresa)
                        ),
                ],
                'fechaalta' => 'required|date',
                'apellidopaterno' => 'required|string|max:84',
                'apellidomaterno' => 'required|string|max:83',
                'nombre' => 'required|string|max:85',
                'cuentacw' => 'required|string|max:31',
                'fecha_alta_gape' => 'required|date',
                'sueldo_real' => 'required|numeric|min:0',
                'sueldo_imss_gape' => 'required|numeric|min:0',
            ]);

            // 2️⃣ Agregar valores por defecto (para columnas NOT NULL)
            $defaults = [
                'calculado' => false,
                'afectado' => false,
                'calculadoextraordinario' => false,
                'afectadoextraordinario' => false,
                'interfazcheqpaqw' => false,
                'modificacionneto' => false,
                'calculoptu' => false,
                'calculoaguinaldo' => false,
                'modificacionsalarioimss' => false,
                'altaimss' => false,
                'bajaimss' => false,
                'cambiocotizacionimss' => false,
                'Subcontratacion' => false,
                'ExtranjeroSinCURP' => false,
                'TipoPrestacion' => 1,
                'DiasVacTomadasAntesdeAlta' => 0,
                'DiasPrimaVacTomadasAntesdeAlta' => 0,
                'TipoSemanaReducida' => 0,
                'Teletrabajador' => 0,
                'EntidadFederativa' => 'MC', // Valor por defecto (México CDMX)
            ];

            // 3️⃣ Unir los datos validados con los valores por defecto
            $data = array_merge($defaults, $validated);

            // 4️⃣ Crear el registro
            $empleado = NominaGapeEmpleado::create($data);

            return response()->json([
                'code' => 200,
                'message' => 'Empleado creado correctamente',
                'id' => $empleado->id,
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'code' => 422,
                'message' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'message' => 'Se generó un error al momento de guardar',
                'error' => $e->getMessage(),
            ], 500);
        }
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
    public function edit(Request $request)
    {
        try {
            // 1️⃣ Validar los campos obligatorios
            $validated = $request->validate([
                'idCliente' => 'required|integer',
                'idEmpresa' => 'required|integer',
                'fiscal' => 'required|boolean',
                'idEmpleado' => 'required|integer',
            ]);

            $idNominaGapeCliente = $validated['idCliente'];
            $idNominaGapeEmpresa = $validated['idEmpresa'];
            $fiscal = $validated['fiscal'];
            $idEmpleado = $validated['idEmpleado'];

            $empleado = null;
            $empleadoGape = null;

            // 2️⃣ Si es fiscal → buscar en la base dinámica (ctEvent_xx)
            if ($fiscal) {
                // Cambiar la conexión dinámica a la base del cliente
                $conexion = $this->helperController->getConexionDatabaseNGE($idNominaGapeEmpresa, 'Nom');
                $this->helperController->setDatabaseConnection($conexion, $conexion->nombre_base);

                // Obtener el empleado desde la tabla nom10001
                $empleado = Empleado::where('idempleado', $idEmpleado)->first();

                $empleadoGape = NominaGapeEmpleado::where('id_nomina_gape_cliente', $idNominaGapeCliente)
                    ->where('id_nomina_gape_empresa', $idNominaGapeEmpresa)
                    ->where('idempleado', $empleado->idempleado)
                    ->first();

                // Si existe información complementaria en GAPE, fusionarla
                if ($empleado && $empleadoGape) {
                    $empleado->fecha_alta_gape = $empleadoGape->fecha_alta_gape;
                    $empleado->sueldo_real = $empleadoGape->sueldo_real;
                    $empleado->sueldo_imss_gape = $empleadoGape->sueldo_imss_gape;
                }
            }
            // 3️⃣ Si no es fiscal → buscar en la tabla central
            else {
                $empleado = NominaGapeEmpleado::where('id_nomina_gape_cliente', $idNominaGapeCliente)
                    ->where('id_nomina_gape_empresa', $idNominaGapeEmpresa)
                    ->where('id', $idEmpleado)

                    ->first();
            }

            // 4️⃣ Validar existencia
            if (!$empleado) {
                return response()->json([
                    'code' => 404,
                    'message' => 'Empleado no encontrado.',
                ], 404);
            }

            // 5️⃣ Formatear fecha de nacimiento (si existe)
            if (!empty($empleado->fechanacimiento)) {
                $empleado->fechanacimiento = \Carbon\Carbon::parse($empleado->fechanacimiento)->format('Y-m-d');
            }

            // 5️⃣ Retornar datos completos
            return response()->json([
                'code' => 200,
                'data' => $empleado,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'message' => 'Error al obtener la información del empleado.',
                'error' => $e->getMessage(),
            ]);
        }
    }


    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request)
    {
        try {
            DB::beginTransaction();

            // 1️⃣ Validar campos esenciales
            $validated = $request->validate([
                'idempleado' => 'required|integer', // ID de NominaGapeEmpleado
                'id_nomina_gape_empresa' => 'required|integer',
                'id_nomina_gape_cliente' => 'required|integer',
            ]);

            $idEmpleadoGape = $validated['idempleado'];
            $idNominaGapeEmpresa = $validated['id_nomina_gape_empresa'];
            $idNominaGapeCliente = $validated['id_nomina_gape_cliente'];

            // 2️⃣ Buscar registro existente en NominaGapeEmpleado
            $empleado = NominaGapeEmpleado::where('idempleado', $idEmpleadoGape)
                ->where('id_nomina_gape_cliente', $idNominaGapeCliente)
                ->where('id_nomina_gape_empresa', $idNominaGapeEmpresa)
                ->first();

            if (!$empleado) {
                throw new \Exception("No se encontró el empleado con ID {$idEmpleadoGape}");
            }

            // 3️⃣ Obtener los datos validados
            $data = $request->all();

            // 4️⃣ Normalizar booleanos
            $booleanFields = [
                'calculado',
                'afectado',
                'calculadoextraordinario',
                'afectadoextraordinario',
                'interfazcheqpaqw',
                'modificacionneto',
                'calculoptu',
                'calculoaguinaldo',
                'modificacionsalarioimss',
                'altaimss',
                'bajaimss',
                'cambiocotizacionimss',
                'Subcontratacion',
                'ExtranjeroSinCURP'
            ];

            foreach ($booleanFields as $field) {
                $data[$field] = isset($data[$field]) ? (bool)$data[$field] : false;
            }

            // 5️⃣ Actualizar datos en NominaGapeEmpleado
            $empleado->update($data);

            // 6️⃣ Reestablecer conexión dinámica
            $conexion = $this->helperController->getConexionDatabaseNGE($idNominaGapeEmpresa, 'Nom');
            $this->helperController->setDatabaseConnection($conexion, $conexion->nombre_base);

            // 7️⃣ Obtener registro en tabla Empleado (ctEvent_x)
            $empleadoNomina = Empleado::where('idempleado', $empleado->idempleado)->first();

            if ($empleadoNomina) {
                // 8️⃣ Actualizar campos en Empleado (todos los coincidentes)
                $empleadoNomina->fill([
                    'iddepartamento' => $empleado->iddepartamento,
                    'idpuesto' => $empleado->idpuesto,
                    'idtipoperiodo' => $empleado->idtipoperiodo,
                    'idturno' => $empleado->idturno,
                    'codigoempleado' => $empleado->codigoempleado,
                    'nombre' => $empleado->nombre,
                    'apellidopaterno' => $empleado->apellidopaterno,
                    'apellidomaterno' => $empleado->apellidomaterno,
                    'nombrelargo' => $empleado->nombrelargo,
                    'fechanacimiento' => $empleado->fechanacimiento,
                    'lugarnacimiento' => $empleado->lugarnacimiento,
                    'estadocivil' => $empleado->estadocivil,
                    'sexo' => $empleado->sexo,
                    'curpi' => $empleado->curpi,
                    'curpf' => $empleado->curpf,
                    'numerosegurosocial' => $empleado->numerosegurosocial,
                    'umf' => $empleado->umf,
                    'rfc' => $empleado->curpi,
                    'homoclave' => $empleado->homoclave,
                    'cuentapagoelectronico' => $empleado->cuentapagoelectronico,
                    'sucursalpagoelectronico' => $empleado->sucursalpagoelectronico,
                    'bancopagoelectronico' => $empleado->bancopagoelectronico,
                    'estadoempleado' => $empleado->estadoempleado,
                    'sueldodiario' => $empleado->sueldodiario,
                    'fechasueldodiario' => $empleado->fechasueldodiario ?? $empleado->fechaalta,
                    'sueldovariable' => $empleado->sueldovariable ?? 1,
                    'fechasueldovariable' => $empleado->fechasueldovariable ?? $empleado->fechaalta,
                    'sueldopromedio' => $empleado->sueldopromedio ?? 1,
                    'fechasueldopromedio' => $empleado->fechasueldopromedio ?? $empleado->fechaalta,
                    'sueldointegrado' => $empleado->sueldointegrado ?? 1,
                    'fechasueldointegrado' => $empleado->fechasueldointegrado ?? $empleado->fechaalta,
                    'calculado' => $empleado->calculado,
                    'afectado' => $empleado->afectado,
                    'calculadoextraordinario' => $empleado->calculadoextraordinario,
                    'afectadoextraordinario' => $empleado->afectadoextraordinario,
                    'interfazcheqpaqw' => $empleado->interfazcheqpaqw,
                    'modificacionneto' => $empleado->modificacionneto,
                    'fechaalta' => $empleado->fechaalta,
                    'tipocontrato' => $empleado->tipocontrato,
                    'basecotizacionimss' => $empleado->basecotizacionimss,
                    'tipoempleado' => $empleado->tipoempleado,
                    'basepago' => $empleado->basepago,
                    'formapago' => $empleado->formapago,
                    'zonasalario' => $empleado->zonasalario,
                    'calculoptu' => $empleado->calculoptu ?? 1,
                    'calculoaguinaldo' => $empleado->calculoaguinaldo ?? 1,
                    'modificacionsalarioimss' => $empleado->modificacionsalarioimss,
                    'altaimss' => $empleado->altaimss,
                    'bajaimss' => $empleado->bajaimss,
                    'cambiocotizacionimss' => $empleado->cambiocotizacionimss,
                    'telefono' => $empleado->telefono,
                    'codigopostal' => $empleado->codigopostal,
                    'direccion' => $empleado->direccion,
                    'poblacion' => $empleado->poblacion,
                    'estado' => $empleado->estado,
                    'nombrepadre' => $empleado->nombrepadre,
                    'nombremadre' => $empleado->nombremadre,
                    'numeroafore' => $empleado->numeroafore,
                    'causabaja' => $empleado->causabaja,
                    'ClabeInterbancaria' => $empleado->ClabeInterbancaria,
                    'TipoRegimen' => $empleado->TipoRegimen,
                    'Subcontratacion' => $empleado->Subcontratacion,
                    'ExtranjeroSinCURP' => $empleado->ExtranjeroSinCURP,
                    'TipoPrestacion' => $empleado->TipoPrestacion,
                    'CorreoElectronico' => $empleado->CorreoElectronico,
                    'DiasVacTomadasAntesdeAlta' => $empleado->DiasVacTomadasAntesdeAlta,
                    'DiasPrimaVacTomadasAntesdeAlta' => $empleado->DiasPrimaVacTomadasAntesdeAlta,
                    'TipoSemanaReducida' => $empleado->TipoSemanaReducida,
                    'Teletrabajador' => $empleado->Teletrabajador,
                    'EntidadFederativa' => $empleado->EntidadFederativa,
                    'cestadoempleadoperiodo' => 'A_',
                    'fechabaja' => '1899-12-30',
                    'fechareingreso' => '1899-12-30',
                    'cfechasueldomixto' => '1899-12-30',
                    'csueldomixto' => '0',
                    'cidregistropatronal' => $empleado->cidregistropatronal,
                    'NumeroFonacot' => $empleado->NumeroFonacot,
                    'ajustealneto' => $empleado->ajustealneto,
                    'sueldobaseliquidacion' => $empleado->sueldobaseliquidacion ?? 0,
                ]);

                $empleadoNomina->save();
            }

            // 9️⃣ Buscar en EmpleadosPorPeriodo
            $cidPeriodo = Periodo::where('idtipoperiodo', $empleado->idtipoperiodo)
                ->where('afectado', 0)
                ->orderBy('idperiodo', 'asc')
                ->value('idperiodo');

            if ($cidPeriodo) {
                $empleadoPorPeriodo = EmpleadosPorPeriodo::where('idempleado', $empleado->idempleado)
                    ->where('idtipoperiodo', $empleado->idtipoperiodo)
                    ->where('cidperiodo', $cidPeriodo)
                    ->first();

                if ($empleadoPorPeriodo) {
                    $empleadoPorPeriodo->fill([
                        'sueldodiario' => $empleado->sueldodiario,
                        'modificacionsalarioimss' => $empleado->modificacionsalarioimss,
                        'bajaimss' => $empleado->bajaimss,
                        'altaimss' => $empleado->altaimss,
                        'cambiocotizacionimss' => $empleado->cambiocotizacionimss,
                        'codigopostal' => $empleado->codigopostal,
                        'direccion' => $empleado->direccion,
                        'poblacion' => $empleado->poblacion,
                        'estado' => $empleado->estado,
                    ]);
                    $empleadoPorPeriodo->save();
                }
            }

            DB::commit();

            return response()->json([
                'code' => 200,
                'message' => 'Empleado actualizado correctamente',
                'id' => $empleado->id,
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'code' => 500,
                'message' => 'Error al actualizar el empleado',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function updateNoFiscal(Request $request)
    {
        try {
            // 1️⃣ Validar datos de entrada
            $validated = $request->validate([
                'id' => 'required|integer|exists:nomina_gape_empleado,id',
                'id_nomina_gape_cliente' => 'required|integer|exists:nomina_gape_cliente,id',
                'id_nomina_gape_empresa' => 'required|integer|exists:nomina_gape_empresa,id',
                'fiscal' => 'required|boolean',
                'codigoempleado' => [
                    'required',
                    'string',
                    Rule::unique('nomina_gape_empleado', 'codigoempleado')
                        ->where(
                            fn($query) =>
                            $query->where('id_nomina_gape_cliente', $request->id_nomina_gape_cliente)
                                ->where('id_nomina_gape_empresa', $request->id_nomina_gape_empresa)
                        )
                        ->ignore($request->id, 'id'), // 👈 Ignora este mismo registro al validar
                ],
                'fechaalta' => 'required|date',
                'apellidopaterno' => 'required|string|max:84',
                'apellidomaterno' => 'required|string|max:83',
                'nombre' => 'required|string|max:85',
                'cuentacw' => 'required|string|max:31',
                'fecha_alta_gape' => 'required|date',
                'sueldo_real' => 'required|numeric|min:0',
                'sueldo_imss_gape' => 'required|numeric|min:0',
            ]);

            // 2️⃣ Buscar empleado existente
            $empleado = NominaGapeEmpleado::where('id', $validated['id'])
                ->where('id_nomina_gape_cliente', $validated['id_nomina_gape_cliente'])
                ->where('id_nomina_gape_empresa', $validated['id_nomina_gape_empresa'])
                ->first();

            if (!$empleado) {
                return response()->json([
                    'code' => 404,
                    'message' => 'Empleado no encontrado.',
                ], 404);
            }

            // 3️⃣ Definir valores por defecto (NOT NULL)
            $defaults = [
                'calculado' => false,
                'afectado' => false,
                'calculadoextraordinario' => false,
                'afectadoextraordinario' => false,
                'interfazcheqpaqw' => false,
                'modificacionneto' => false,
                'calculoptu' => false,
                'calculoaguinaldo' => false,
                'modificacionsalarioimss' => false,
                'altaimss' => false,
                'bajaimss' => false,
                'cambiocotizacionimss' => false,
                'Subcontratacion' => false,
                'ExtranjeroSinCURP' => false,
                'TipoPrestacion' => 1,
                'DiasVacTomadasAntesdeAlta' => 0,
                'DiasPrimaVacTomadasAntesdeAlta' => 0,
                'TipoSemanaReducida' => 0,
                'Teletrabajador' => 0,
                'EntidadFederativa' => 'MC', // valor por defecto, CDMX
            ];

            // 4️⃣ Unir datos validados con defaults (sin sobreescribir si vienen en request)
            $data = array_merge($defaults, $validated);

            // 5️⃣ Actualizar datos
            $empleado->update($data);

            return response()->json([
                'code' => 200,
                'message' => 'Empleado actualizado correctamente',
                'id' => $empleado->id,
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'code' => 422,
                'message' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'message' => 'Error al actualizar el empleado',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
