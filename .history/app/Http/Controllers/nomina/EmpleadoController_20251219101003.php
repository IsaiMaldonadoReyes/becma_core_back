<?php

namespace App\Http\Controllers\nomina;

use App\Http\Controllers\Controller;
use App\Http\Requests\nomina\gape\Empleado\StoreEmpleadoRequest;
use App\Http\Requests\nomina\gape\Empleado\StoreNoFiscalRequest;
use Illuminate\Http\Request;

// Importar modelos necesarios
use App\Models\nomina\GAPE\NominaGapeEmpleado; // Asegúrate de que este modelo exista
use App\Models\nomina\default\Empleado; // Asegúrate de que este modelo exista
use App\Models\nomina\default\Periodo; // Asegúrate de que este modelo exista
use App\Models\nomina\default\Departamento; // Asegúrate de que este modelo exista
use App\Models\nomina\default\Empresa;
use App\Models\nomina\default\EmpleadosPorPeriodo; // Asegúrate de que este modelo exista
use App\Models\nomina\GAPE\NominaGapeEmpresa;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use App\Http\Controllers\core\HelperController;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

use App\Http\Services\Nomina\Export\Empleados\ConfigFormatoEmpleadosService;

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

    public function noFiscalesEmpresaCliente(Request $request)
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

            $empleados = NominaGapeEmpleado::where('id_nomina_gape_cliente', $idNominaGapeCliente)
                ->where('id_nomina_gape_empresa', $idNominaGapeEmpresa)
                ->select(
                    'id',
                    'codigoempleado',
                    DB::raw("LTRIM(RTRIM(nombre)) + ' ' + LTRIM(RTRIM(apellidopaterno)) + ' ' + LTRIM(RTRIM(apellidomaterno)) AS nombrelargo"),
                    DB::raw("cuentacw AS rfc"),
                    DB::raw("FORMAT(fechaalta, 'dd-MM-yyyy') as fechaalta"),
                )
                ->get();
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

    public function fiscalesEmpresaCliente(Request $request)
    {
        try {
            // 1️⃣ Validar los campos obligatorios

            $validated = $request->validate([
                'idCliente' => 'required',
                'idEmpresa' => 'required',
                'idTipoPeriodo' => 'required',
                //'departamentoInicial' => 'required',
                //'departamentoFinal' => 'required',
            ]);

            $idNominaGapeEmpresa = $validated['idEmpresa'];
            $idTipoPeriodo = $validated['idTipoPeriodo'];
            //$departamentoInicial = $validated['departamentoInicial'];
            //$departamentoFinal = $validated['departamentoFinal'];

            $empleados = null;

            $conexion = $this->helperController->getConexionDatabaseNGE($idNominaGapeEmpresa, 'Nom');
            $this->helperController->setDatabaseConnection($conexion, $conexion->nombre_base);

            /*
            $departamentos = Departamento::whereBetween('iddepartamento', [
                $departamentoInicial,
                $departamentoFinal
            ])
                ->orderBy('iddepartamento') // o por nombre si quieres
                ->pluck('iddepartamento');  // devuelve un array de IDs
*/

            $empleados = Empleado::select(
                'idempleado',
                'codigoempleado',
                DB::raw("LTRIM(RTRIM(nombre)) + ' ' + LTRIM(RTRIM(apellidopaterno)) + ' ' + LTRIM(RTRIM(apellidomaterno)) AS nombrelargo"),
                DB::raw("rfc + SUBSTRING(CONVERT(char(10),fechanacimiento , 126), 3,2)
                      + SUBSTRING(CONVERT(char(10),fechanacimiento , 126), 6,2)
                      + SUBSTRING(CONVERT(char(10),fechanacimiento, 126), 9,2)
                      + homoclave AS rfc"),
                DB::raw("FORMAT(fechaalta, 'dd-MM-yyyy') as fechaalta")
            )
                ->where('idtipoperiodo', $idTipoPeriodo)
                //->whereIn('iddepartamento', $departamentos)  // 👈 AQUÍ USAS LOS IDs
                ->get();



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

    private function getStringFields(): array
    {
        return [
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
    }

    private function mapEmpleadoData($empleado): array
    {
        return [
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
            'campoextra1' => $empleado->campoextra1 ?? 0,
            'ccampoextranumerico1' => $empleado->ccampoextranumerico1 ?? 0,
            'ccampoextranumerico2' => $empleado->ccampoextranumerico2 ?? 0,
        ];
    }


    public function store(StoreEmpleadoRequest $request)
    {
        try {
            DB::beginTransaction();

            $validated = $request->validated();

            // 🔹 1️⃣ Inicializar campos booleanos con false si no existen
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
                if (!isset($validated[$field])) {
                    $validated[$field] = false;
                }
            }

            // 🔹 2️⃣ Crear empleado en tu tabla interna
            $empleado = NominaGapeEmpleado::create($validated);


            // 🔹 3️⃣ Conectarse a base dinámica
            $conexion = $this->helperController->getConexionDatabaseNGE($empleado->id_nomina_gape_empresa, 'Nom');
            $this->helperController->setDatabaseConnection($conexion, $conexion->nombre_base);

            // 🔹 4️⃣ Transacción anidada sobre la conexión dinámica
            DB::transaction(function () use ($empleado) {
                // 4.1️⃣ Obtener la configuración de empresa
                $empresa = Empresa::select('mascarillacodigo')
                    ->first();

                $mascara = $empresa->mascarillacodigo ?? 'XXXX';
                $longitud = substr_count($mascara, 'X');

                // 4.2️⃣ Obtener último código de empleado (bloqueado)
                $ultimoCodigo = Empleado::orderBy('codigoempleado', 'desc')
                    ->lockForUpdate()
                    ->value('codigoempleado');

                // 4.3️⃣ Generar código único
                $nuevoCodigo = $this->generarSiguienteCodigoSimple($ultimoCodigo, $longitud);

                // 4.4️⃣ Actualizar en modelo principal
                $empleado->codigoempleado = $nuevoCodigo;
                $empleado->save();

                // 🔹 4.5️⃣ Preparar datos para insertar en Empleado (base dinámica)
                $empleadoData = $this->mapEmpleadoData($empleado);

                // 🔹 4.6️⃣ Limpieza de campos nulos (texto)
                $stringFields = $this->getStringFields();
                foreach ($stringFields as $field) {
                    if (!isset($empleadoData[$field]) || is_null($empleadoData[$field])) {
                        $empleadoData[$field] = '';
                    }
                }

                // 🔹 4.7️⃣ Insertar en tabla Empleado (dinámica)
                $empleadoInsertado = Empleado::create($empleadoData);
                $idempleado = $empleadoInsertado->idempleado;

                // 🔹 4.8️⃣ Guardar id en tabla interna
                $empleado->update([
                    'idempleado' => $idempleado,
                    'rfc' => $empleado->curpi,
                ]);

                // 🔹 4.9️⃣ Buscar periodo activo
                $cidPeriodo = Periodo::where('idtipoperiodo', $empleado->idtipoperiodo)
                    ->where('afectado', 0)
                    ->orderBy('idperiodo', 'asc')
                    ->value('idperiodo');

                if (!$cidPeriodo) {
                    throw new \Exception("No se encontró un periodo activo para el tipo de periodo {$empleado->idtipoperiodo}");
                }

                // 🔹 4.10️⃣ Crear EmpleadosPorPeriodo (nom10034)
                $empleadoPeriodoData = array_merge($empleadoData, [
                    'idempleado' => $idempleado,
                    'idtipoperiodo' => $empleado->idtipoperiodo,
                    'cidperiodo' => $cidPeriodo,
                    'estadoempleado' => 'A',
                ]);

                foreach ($stringFields as $field) {
                    if (!isset($empleadoPeriodoData[$field]) || is_null($empleadoPeriodoData[$field])) {
                        $empleadoPeriodoData[$field] = '';
                    }
                }

                EmpleadosPorPeriodo::create($empleadoPeriodoData);
            });


            DB::commit();

            return response()->json([
                'code' => 200,
                'message' => 'Empleado creado correctamente',
                'id' => $empleado->id,
                'codigoempleado' => $empleado->codigoempleado,
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error en store empleado: ' . $e->getMessage());

            return response()->json([
                'code' => 500,
                'message' => 'Error al guardar empleado',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Genera el siguiente código correlativo de empleado sin usar prefijos.
     * Ejemplo: 0001 → 0002 → 0003, según la longitud de la máscara.
     */
    private function generarSiguienteCodigoSimple(?string $ultimo, int $longitud): string
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


    /**
     * Store a newly created resource in storage.
     */
    public function storexxx(StoreEmpleadoRequest $request)
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

    public function storeNoFiscal(StoreNoFiscalRequest $request)
    {
        try {
            // 🧩 1️⃣ Validar datos del request (ya manejado por tu FormRequest)
            $validated = $request->validated();

            $idEmpresa = $validated['id_nomina_gape_empresa'];
            $idCliente = $validated['id_nomina_gape_cliente'];

            // 🧩 2️⃣ Ejecutar todo dentro de una transacción para mantener coherencia
            $empleado = DB::transaction(function () use ($validated, $idEmpresa, $idCliente) {

                // 2.1️⃣ Bloquear la fila de la empresa (evita duplicidad de códigos concurrentes)
                $empresa = NominaGapeEmpresa::lockForUpdate()
                    ->select('id', 'mascara_codigo', 'codigo_inicial', 'codigo_actual')
                    ->where('id', $idEmpresa)
                    ->firstOrFail();

                // 2.2️⃣ Generar un código único disponible
                $codigoGenerado = $this->generarCodigoUnico($idEmpresa, $idCliente, $empresa);

                // 2.3️⃣ Valores por defecto
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
                    'EntidadFederativa' => 'MC',
                ];

                // 2.4️⃣ Unir datos finales
                $data = array_merge($defaults, $validated, [
                    'codigoempleado' => $codigoGenerado,
                ]);

                // 2.5️⃣ Crear empleado
                $empleado = NominaGapeEmpleado::create($data);

                // 2.6️⃣ Actualizar empresa con el nuevo código actual
                $empresa->update(['codigo_actual' => $codigoGenerado]);

                // Retornar el empleado para usar fuera de la transacción
                return $empleado;
            });

            // 🧩 3️⃣ Respuesta final al cliente
            return response()->json([
                'code' => 200,
                'message' => 'Empleado creado correctamente',
                'id' => $empleado->id,
                'codigoempleado' => $empleado->codigoempleado,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error en storeNoFiscal: ' . $e->getMessage());

            return response()->json([
                'code' => 500,
                'message' => 'Se generó un error al momento de guardar',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    /**
     * Genera un nuevo código de empleado único basado en la máscara y los códigos existentes.
     */
    private function generarCodigoUnico(int $idEmpresa, int $idCliente, $empresa): string
    {
        $mascara = $empresa->mascara_codigo ?? 'XXXX';
        $longitud = substr_count($mascara, 'X');

        if ($longitud < 1) {
            $longitud = 4; // valor por defecto
        }

        // Obtener código base
        $codigoBase = $empresa->codigo_actual ?? $empresa->codigo_inicial ?? str_pad('1', $longitud, '0', STR_PAD_LEFT);
        $codigoBase = preg_replace('/\D/', '', $codigoBase);
        $codigoBase = str_pad($codigoBase, $longitud, '0', STR_PAD_LEFT);

        $numero = intval($codigoBase);

        $maxIntentos = 9999;
        for ($i = 0; $i < $maxIntentos; $i++) {
            $numero++;
            $nuevoCodigo = str_pad($numero, $longitud, '0', STR_PAD_LEFT);

            $existe = NominaGapeEmpleado::where('id_nomina_gape_empresa', $idEmpresa)
                ->where('id_nomina_gape_cliente', $idCliente)
                ->where('codigoempleado', $nuevoCodigo)
                ->exists();

            if (!$existe) {
                return $nuevoCodigo;
            }
        }

        throw new \Exception('No se pudo generar un nuevo código único después de múltiples intentos.');
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
                /*if ($empleado && $empleadoGape) {
                    $empleado->fecha_alta_gape = $empleadoGape->fecha_alta_gape;
                    $empleado->sueldo_real = $empleadoGape->sueldo_real;
                    $empleado->sueldo_imss_gape = $empleadoGape->sueldo_imss_gape;
                }*/


                $empleado->ccampoextranumerico1 = number_format((float)$empleado->ccampoextranumerico1, 2, '.', '');
                $empleado->ccampoextranumerico2 = number_format((float)$empleado->ccampoextranumerico2, 2, '.', '');

                $empleado->sueldodiario = number_format((float)$empleado->sueldodiario, 2, '.', '');
                $empleado->sueldointegrado = number_format((float)$empleado->sueldointegrado, 2, '.', '');
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
                    'ccampoextranumerico1' => $empleado->ccampoextranumerico1  ?? 0,
                    'ccampoextranumerico2' => $empleado->ccampoextranumerico2 ?? 0,
                    'campoextra1' => $empleado->campoextra1 ?? 0,
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
                'campoextra1' => 'required',
                'ccampoextranumerico1' => 'required|numeric|min:0',
                'ccampoextranumerico2' => 'required|numeric|min:0',
                'ClabeInterbancaria' => 'nullable|digits_between:10,30|numeric',
                'codigopostal' => 'nullable|string|max:10',
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

    /** 
     * Descargar formato base para importación masiva de empleados
     */
    public function descargaFormato(
        Request $request,
        //IncidenciasQueryService $queryService,
        //ExportIncidenciasService $exporter
    ) {
        $validated = $request->validate([
            'fiscal' => 'required|boolean',
        ]);

        $fiscal = $validated['fiscal'];

        // 1. CONFIG
        $config = ConfigFormatoEmpleadosService::getConfig($fiscal);

        // Formato excel
        $spreadsheet = $this->loadTemplate($config['path']);
        $sheet = $this->getWorksheet($spreadsheet, $config['sheet_name']);

        // 2. DATOS
        //$dataRaw = $queryService->getData($config['query'], $request);

        /*$data = collect($dataRaw)
            ->map(fn($r) => (array)$r)
            ->toArray();*/


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


    private function loadTemplate(string $path)
    {
        $fullPath = storage_path("app/public/" . $path);

        if (!file_exists($fullPath)) {
            throw new \Exception("La plantilla no existe: {$fullPath}");
        }

        return IOFactory::load($fullPath);
    }

    private function getWorksheet($spreadsheet, string $sheetName): Worksheet
    {
        $sheet = $spreadsheet->getSheetByName($sheetName);

        if (!$sheet) {
            throw new \Exception("La hoja '{$sheetName}' no existe en el archivo Excel.");
        }

        return $sheet;
    }
}
