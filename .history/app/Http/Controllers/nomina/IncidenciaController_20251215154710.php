<?php

namespace App\Http\Controllers\nomina;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Carbon\Carbon;

use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\HttpFoundation\StreamedResponse;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

use App\Models\nomina\GAPE\NominaGapeIncidencia;
use App\Models\nomina\GAPE\NominaGapeIncidenciaDetalle;

use App\Models\nomina\default\Periodo;
use App\Models\nomina\default\Empleado;
use App\Models\nomina\default\MovimientosDiasHorasVigente;
use App\Models\nomina\default\TipoIncidencia;
use App\Models\nomina\default\TarjetaVacaciones;
use App\Models\nomina\default\TarjetaIncapacidad;
use App\Models\nomina\default\MovimientosPDOVigente;
use App\Models\nomina\default\Conceptos;

use App\Http\Services\Nomina\Export\Incidencias\ConfigFormatoIncidenciasService;
use App\Http\Services\Nomina\Export\Incidencias\IncidenciasQueryService;
use App\Http\Services\Nomina\Export\Incidencias\ExportIncidenciasService;

use App\Http\Services\Nomina\Import\Incidencias\IncidenciasSaver;
use App\Http\Services\Nomina\Import\Incidencias\IncidenciasNominaApplier;

use App\Http\Services\Nomina\Import\Incidencias\IncidenciasImporter;

use App\Http\Services\Core\HelperService;

class IncidenciaController extends Controller
{

    public function descargaFormatoFiscal(
        Request $request,
        IncidenciasQueryService $queryService,
        ExportIncidenciasService $exporter
    ) {
        $validated = $request->validate([
            'fiscal' => 'required|boolean',
            'id_nomina_gape_empresa' => 'required',
            'id_tipo_periodo' => 'required_if:fiscal,true',
            'periodo_inicial' => 'required_if:fiscal,true',
        ]);

        $fiscal = $validated['fiscal'];

        // 1. CONFIG
        $config = ConfigFormatoIncidenciasService::getConfig($fiscal);

        // 2. DATOS
        $dataRaw = $queryService->getData($config['query'], $request);

        $data = collect($dataRaw)
            ->map(fn($r) => (array)$r)
            ->toArray();

        // 3. EXCEL
        $spreadsheet = $exporter->generarExcel($config, $data);

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

    public function uploadIncidenciasFiscales(
        Request $request,
        HelperService $helper,
        IncidenciasImporter $importer,
        IncidenciasSaver $saver,
        IncidenciasNominaApplier $applier
    ) {

        // VALIDACIÓN BÁSICA
        $validated = $request->validate([
            'file'          => 'required|file|mimes:xlsx,xls',
            'idCliente'     => 'required',
            'idEmpresa'     => 'required',
            'idTipoPeriodo' => 'nullable',
            'idPeriodo'     => 'nullable',
        ]);

        $idNominaGapeEmpresa = $validated['idEmpresa'];

        $conexion = $helper->getConexionDatabaseNGE($idNominaGapeEmpresa, 'Nom');
        $helper->setDatabaseConnection($conexion, $conexion->nombre_base);

        $result = $importer->procesar($request);

        if (!empty($result->errores)) {
            return response()->json([
                'errores' => $result->errores,
            ], 422);
        }

        // 2. GUARDAR MAESTRO
        $incidencia = $saver->guardarMaestro($request);

        // 3. GUARDAR DETALLE + APLICAR EN NOM100xx
        foreach ($result->filasValidas as $row) {

            $detalle = $saver->guardarDetalle($result->sheet, $row, $incidencia->id);

            $applier->aplicar(
                $result->sheet,
                $row,
                $detalle->id_empleado,
                $request->idPeriodo
            );
        }

        return response()->json([
            'ok'  => true,
            'msg' => "Incidencias procesadas correctamente.",
        ]);
    }

    public function uploadIncidenciasFiscales2(Request $request)
    {
        // ------------------------------
        // VALIDACIÓN INICIAL
        // ------------------------------
        $validated = $request->validate([
            'file' => 'required|file|mimes:xlsx,xls',
            'idCliente' => 'required',
            'idEmpresa' => 'required',
            'idTipoPeriodo' => 'nullable',
            'idPeriodo' => 'nullable',
        ]);

        $idNominaGapeEmpresa = $validated['idEmpresa'];
        $idPeriodo = $validated['idPeriodo'];

        // ------------------------------
        // CAMBIO DE CONEXIÓN
        // ------------------------------
        $conexion = $this->helperController->getConexionDatabaseNGE($idNominaGapeEmpresa, 'Nom');
        $this->helperController->setDatabaseConnection($conexion, $conexion->nombre_base);

        // ------------------------------
        // OBTENER díasdepago DEL PERIODO
        // ------------------------------
        $periodoSeleccionado = Periodo::where('idperiodo', $idPeriodo)->first();

        if (!$periodoSeleccionado) {
            return response()->json([
                'error' => 'El periodo seleccionado no existe.'
            ], 422);
        }

        $diasDePago = intval($periodoSeleccionado->diasdepago);

        // ------------------------------
        // LEER EXCEL
        // ------------------------------
        $path = $request->file('file')->getRealPath();
        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getActiveSheet();

        $highestRow = $sheet->getHighestRow();

        $errores = [];
        $filasValidas = [];

        // Configuración columnas
        $colCodigoEmpleado = 'A';

        // Rangos
        $colInicioEnteros     = Coordinate::columnIndexFromString('I'); // enteros positivos
        $colFinEnteros        = Coordinate::columnIndexFromString('N');
        $colInicioDecimales   = Coordinate::columnIndexFromString('O'); // decimales
        $colFinDecimales      = Coordinate::columnIndexFromString('V');

        // ------------------------------
        // VALIDACIÓN POR FILA
        // ------------------------------
        for ($row = 10; $row <= $highestRow; $row++) {

            $filaCorrecta = true;
            $sumaDiasExcel = 0; // I+J+K
            $tieneDatos = false;

            // Código empleado
            $codigoEmpleado = trim((string)$sheet->getCell($colCodigoEmpleado . $row)->getValue());

            if ($codigoEmpleado === "") {
                continue; // ignorar fila completamente
            }

            // Validar existencia del empleado
            $empleado = Empleado::select('idempleado')
                ->where('codigoempleado', $codigoEmpleado)
                ->first();

            if (!$empleado) {
                $errores[] = [
                    'fila' => $row,
                    'columna' => 'A',
                    'valor' => $codigoEmpleado,
                    'mensaje' => "El empleado con código '{$codigoEmpleado}' no existe."
                ];
                $filaCorrecta = false;
            }

            // --------------------------------------------
            // UNIFICAR VALIDACIÓN + DETECCIÓN DE DATOS
            // --------------------------------------------
            for ($col = $colInicioEnteros; $col <= $colFinDecimales; $col++) {

                $colLetter = Coordinate::stringFromColumnIndex($col);
                $cellValue = trim((string)$sheet->getCell($colLetter . $row)->getValue());

                // Detectar si la fila tiene valores capturados
                if ($cellValue !== "" && $cellValue !== null) {
                    $tieneDatos = true;
                }

                // Si está vacío se permite
                if ($cellValue === "" || $cellValue === null) continue;

                // ------------------- ENTEROS POSITIVOS (I–N)
                if ($col >= $colInicioEnteros && $col <= $colFinEnteros) {

                    if (!preg_match('/^[1-9]\d*$/', $cellValue)) {
                        $errores[] = [
                            'agrupador' => 'formato',
                            'tipo' => 'numerico',
                            'fila' => $row,
                            'columna' => $colLetter,
                            'valor' => $cellValue,
                            'mensaje' => "El valor '{$cellValue}' en {$colLetter}{$row} debe ser un entero mayor a 0."
                        ];
                        $filaCorrecta = false;
                    } else {
                        if (in_array($colLetter, ['I', 'J', 'K'])) {
                            $sumaDiasExcel += intval($cellValue);
                        }
                    }

                    continue;
                }

                // ------------------- DECIMALES (O–V)
                if ($col >= $colInicioDecimales && $col <= $colFinDecimales) {

                    if (!preg_match('/^\s*-?(?:\d+|\d*\.\d+)\s*$/', $cellValue)) {
                        $errores[] = [
                            'agrupador' => 'formato',
                            'tipo' => 'decimal',
                            'fila' => $row,
                            'columna' => $colLetter,
                            'valor' => $cellValue,
                            'mensaje' => "El valor '{$cellValue}' en {$colLetter}{$row} debe ser decimal."
                        ];
                        $filaCorrecta = false;
                    }

                    continue;
                }
            }

            // Ignorar filas vacías
            if (!$tieneDatos) continue;

            // ---------------------------------------------------------------
            // 🔵 VALIDACIÓN DE VACACIONES (COLUMNA K) — POR ANTIGÜEDAD
            // ---------------------------------------------------------------
            if ($filaCorrecta && $empleado) {

                $idempleado = $empleado->idempleado;

                // Valores de Excel
                $valorRetroactivo     = intval($sheet->getCell('L' . $row)->getValue()); // concepto 16
                $valorPrimaDominical  = intval($sheet->getCell('M' . $row)->getValue()); // concepto 10
                $valorDiasFestivos    = intval($sheet->getCell('N' . $row)->getValue()); // concepto 11


                // Mapeo concepto → valor Excel → columna Excel
                $conceptosAValidar = [
                    16 => ['valor' => $valorRetroactivo,    'col' => 'L', 'descripcion' => 'Días retroactivos'],
                    10 => ['valor' => $valorPrimaDominical, 'col' => 'M', 'descripcion' => 'Prima dominical'],
                    11 => ['valor' => $valorDiasFestivos,   'col' => 'N', 'descripcion' => 'Días festivos'],
                ];

                // Obtener los conceptos YA CAPTURADOS en nom10008
                $conceptosYaExistentes = MovimientosPDOVigente::from('nom10008 AS movs')
                    ->join('nom10004 AS con', 'movs.idconcepto', '=', 'con.idconcepto')
                    ->where('movs.idempleado', $idempleado)
                    ->where('movs.idperiodo', $idPeriodo)
                    ->where('con.tipoconcepto', 'P')
                    ->whereIn('con.numeroconcepto', [16, 10, 11])
                    ->pluck('con.numeroconcepto')
                    ->toArray();

                // Validamos cada concepto
                foreach ($conceptosAValidar as $numConcepto => $item) {

                    $valor = $item['valor'];
                    $col   = $item['col'];
                    $desc  = $item['descripcion'];

                    if ($valor > 0) {

                        if (in_array($numConcepto, $conceptosYaExistentes)) {
                            $errores[] = [
                                'agrupador' => 'nomina',
                                'tipo' => 'conceptosRepetidos',
                                'fila'    => $row,
                                'columna' => $col,
                                'valor'   => $valor,
                                'mensaje' => "El concepto '{$desc}' (concepto {$numConcepto}) ya existe en la nómina del empleado y no puede duplicarse."
                            ];

                            $filaCorrecta = false;
                        }
                    }
                }

                // 1. Días de vacaciones asignados según antigüedad
                $diasVacaciones = Empleado::from('nom10001 AS emp')
                    ->join('nom10034 AS empPeriodo', function ($q) use ($idPeriodo) {
                        $q->on('emp.idempleado', '=', 'empPeriodo.idempleado')
                            ->where('empPeriodo.cidperiodo', '=', $idPeriodo);
                    })
                    ->join('nom10002 AS periodo', 'empPeriodo.cidperiodo', '=', 'periodo.idperiodo')
                    ->join('nom10050 AS tipoPres', 'empPeriodo.TipoPrestacion', '=', 'tipoPres.IDTabla')
                    ->join('nom10051 AS antig', 'antig.IDTablaPrestacion', '=', 'tipoPres.IDTabla')
                    ->where('emp.idempleado', $idempleado)
                    ->orderBy('antig.fechainicioVigencia', 'DESC')
                    ->select('antig.DiasVacaciones')
                    ->first();

                $diasVacaciones = $diasVacaciones->DiasVacaciones ?? 0;

                // 2. Vacaciones ya tomadas del empleado
                $cantidadVacacionesTomadas = MovimientosDiasHorasVigente::from('nom10010 AS mhv')
                    ->join('nom10022 AS ti', 'mhv.idtipoincidencia', '=', 'ti.idtipoincidencia')
                    ->where('ti.mnemonico', 'VAC')
                    ->where('mhv.idempleado', $idempleado)
                    ->where('mhv.fecha', '>', function ($sub) use ($idempleado, $idPeriodo) {
                        $sub->select('fechaalta')
                            ->from('nom10034')
                            ->where('idempleado', $idempleado)
                            ->where('cidperiodo', $idPeriodo);
                    })
                    ->sum('mhv.valor');

                $cantidadVacacionesTomadas = $cantidadVacacionesTomadas ?? 0;

                // 3. Vacaciones disponibles reales
                $vacacionesDisponibles = max(0, $diasVacaciones - $cantidadVacacionesTomadas);

                // 4. Días capturados en Excel (K)
                $vacacionesExcel = intval($sheet->getCell('K' . $row)->getValue());

                if ($vacacionesExcel > $vacacionesDisponibles) {
                    $errores[] = [
                        'agrupador' => 'nomina',
                        'tipo' => 'vacaciones',
                        'fila' => $row,
                        'columna' => 'K',
                        'valor' => $vacacionesExcel,
                        'mensaje' => "El empleado solo tiene {$vacacionesDisponibles} días de vacaciones disponibles, pero capturó {$vacacionesExcel}."
                    ];
                    $filaCorrecta = false;
                }

                if ($sumaDiasExcel > $diasDePago) {
                    $errores[] = [
                        'agrupador' => 'nomina',
                        'tipo' => 'diasPeriodo',
                        'fila' => $row,
                        'columna' => 'I-K',
                        'valor' => $sumaDiasExcel,
                        'mensaje' => "La suma I+J+K ({$sumaDiasExcel}) excede los {$diasDePago} días del periodo."
                    ];
                    $filaCorrecta = false;
                }

                $totalIncidenciasReal = MovimientosDiasHorasVigente::from('nom10010 AS mhv')
                    ->join('nom10001 AS emp', 'mhv.idempleado', '=', 'emp.idempleado')
                    ->where('emp.codigoempleado', $codigoEmpleado)
                    ->where('mhv.idperiodo', $idPeriodo)
                    ->sum('mhv.valor');

                $diasValidos = $diasDePago -  $totalIncidenciasReal;

                if ($sumaDiasExcel > $diasValidos) {
                    $errores[] = [
                        'agrupador' => 'nomina',
                        'tipo' => 'diasDisponibles',
                        'fila' => $row,
                        'columna' => 'I-K',
                        'valor' => $sumaDiasExcel,
                        'mensaje' => "La suma I+J+K ({$sumaDiasExcel}) excede los dias disponibles ({$diasValidos})."
                    ];
                    $filaCorrecta = false;
                }
            }

            if ($filaCorrecta) {
                $filasValidas[] = $row;
            }
        }

        // ------------------------------
        // ERRORES ENCONTRADOS
        // ------------------------------

        if (!empty($errores)) {

            // Estructura final
            $resultado = [];

            // Agrupar por agrupador
            foreach ($errores as $err) {

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

            // Mantener formato JSON de respuesta
            return response()->json([
                'code' => 422,
                'errors' => array_values($resultado), // limpiar índices
                'errorsRaw' => $errores, // opcional: crudos para debug
            ], 422);
        }

        // ------------------------------
        // INSERTAR MAESTRO
        // ------------------------------
        $incidencia = NominaGapeIncidencia::create([
            'estado' => 1,
            'id_nomina_gape_cliente' => $request->idCliente,
            'id_nomina_gape_empresa' => $request->idEmpresa,
            'id_tipo_periodo' => $request->idTipoPeriodo,
            'id_periodo' => $request->idPeriodo,
        ]);

        // ------------------------------
        // INSERTAR DETALLE
        // ------------------------------
        foreach ($filasValidas as $row) {

            $codigo = trim((string)$sheet->getCell('A' . $row)->getValue());
            $empleado = Empleado::where('codigoempleado', $codigo)->first();

            NominaGapeIncidenciaDetalle::create([
                'estado' => 1,
                'id_nomina_gape_incidencia' => $incidencia->id,
                'id_empleado' => $empleado->idempleado ?? null,

                'cantidad_incapacidad'        => floatval($sheet->getCell('I' . $row)->getValue()),
                'cantidad_faltas'             => floatval($sheet->getCell('J' . $row)->getValue()),
                'cantidad_vacaciones'         => floatval($sheet->getCell('K' . $row)->getValue()),
                'cantidad_dias_retroactivos'  => floatval($sheet->getCell('L' . $row)->getValue()),
                'cantidad_prima_dominical'    => floatval($sheet->getCell('M' . $row)->getValue()),
                'cantidad_dias_festivos'      => floatval($sheet->getCell('N' . $row)->getValue()),
                'comision'                    => floatval($sheet->getCell('O' . $row)->getValue()),
                'bono'                        => floatval($sheet->getCell('P' . $row)->getValue()),
                'horas_extra_doble_cantidad'  => floatval($sheet->getCell('Q' . $row)->getValue()),
                'horas_extra_doble'           => floatval($sheet->getCell('R' . $row)->getValue()),
                'horas_extra_triple_cantidad' => floatval($sheet->getCell('S' . $row)->getValue()),
                'horas_extra_triple'          => floatval($sheet->getCell('T' . $row)->getValue()),
                'pago_adicional'              => floatval($sheet->getCell('U' . $row)->getValue()),
                'premio_puntualidad'          => floatval($sheet->getCell('V' . $row)->getValue()),

                'codigo_empleado' => $codigo
            ]);

            $this->insertarIncidenciasEnNomina(
                $empleado->idempleado,
                $idPeriodo,
                intval($sheet->getCell('I' . $row)->getValue()), // incapacidad
                intval($sheet->getCell('J' . $row)->getValue()), // faltas
                intval($sheet->getCell('K' . $row)->getValue())  // vacaciones
            );

            $this->insertarIncidenciasConceptosEnNomina(
                $empleado->idempleado,
                $idPeriodo,
                intval($sheet->getCell('L' . $row)->getValue()), // retroactivos
                intval($sheet->getCell('M' . $row)->getValue()), // primaDominical
                intval($sheet->getCell('N' . $row)->getValue())  // diasFestivos
            );
        }

        return response()->json([
            'code' => 200,
            'message' => 'Datos obtenidos correctamente',
        ]);
    }

    private function insertarIncidenciasConceptosEnNomina($idempleado, $idPeriodo, $retroactivos, $primaDominical, $diasFestivos)
    {
        // Mapeo numeroconcepto => valor capturado
        $valores = [
            16 => $retroactivos,
            10 => $primaDominical,
            11 => $diasFestivos,
        ];

        // Buscar idconcepto reales
        $conceptos = Conceptos::select('idconcepto', 'numeroconcepto')
            ->where('tipoconcepto', 'P')
            ->whereIn('numeroconcepto', array_keys($valores))
            ->pluck('idconcepto', 'numeroconcepto');

        // Función para insertar un movimiento
        $insertar = function ($cantidad, $idConcepto) use ($idempleado, $idPeriodo) {

            // No insertar conceptos vacíos
            if ($cantidad <= 0) {
                return;
            }

            // Si no existe idConcepto → no insertarlo
            if (!$idConcepto) {
                return;
            }

            $movs = new MovimientosPDOVigente();

            $movs->idempleado          = $idempleado;
            $movs->idperiodo           = $idPeriodo;
            $movs->idconcepto          = $idConcepto;
            $movs->idmovtopermanente   = 0;
            $movs->importetotal        = 0;

            $movs->valor               = $cantidad;
            $movs->importe1            = 0;
            $movs->importe2            = 0;
            $movs->importe3            = 0;
            $movs->importe4            = 0;

            // Reportado solo cuando hay valor
            $movs->importetotalreportado = 1;
            $movs->importe1reportado     = 0;
            $movs->importe2reportado     = 0;
            $movs->importe3reportado     = 0;
            $movs->importe4reportado     = 0;

            $movs->valorReportado        = 1;
            $movs->timestamp             = now();

            $movs->save();
        };

        // Ejecutar para cada concepto
        foreach ($valores as $num => $cantidad) {
            $insertar($cantidad, $conceptos[$num] ?? null);
        }
    }

    private function insertarIncidenciasEnNomina($idempleado, $idPeriodo, $incapacidad, $faltas, $vacaciones)
    {
        // 1. Obtener rango del periodo
        $periodo = Periodo::where('idperiodo', $idPeriodo)
            ->select('fechainicio', 'fechafin', 'ejercicio')
            ->first();

        if (!$periodo) return;

        $fechaInicio = Carbon::parse($periodo->fechainicio);
        $fechaFin    = Carbon::parse($periodo->fechafin);

        // 2. Generar lista completa de días
        $diasPeriodo = [];
        for ($f = $fechaInicio->copy(); $f <= $fechaFin; $f->addDay()) {
            $diasPeriodo[] = $f->format('Y-m-d');
        }

        // 3. Días ya usados en nom10010
        $diasOcupados = MovimientosDiasHorasVigente::where('idperiodo', $idPeriodo)
            ->where('idempleado', $idempleado)
            ->pluck('fecha')
            ->map(fn($d) => Carbon::parse($d)->format('Y-m-d'))
            ->toArray();

        // 4. Obtener días disponibles
        $diasDisponibles = array_values(array_diff($diasPeriodo, $diasOcupados));

        //------------------------------------
        // 5. Obtener IDs de incidencias
        //------------------------------------
        $tipos = TipoIncidencia::whereIn('mnemonico', ['INC', 'FINJ', 'VAC'])
            ->pluck('idtipoincidencia', 'mnemonico');

        $idINC  = $tipos['INC']  ?? null;
        $idFINJ = $tipos['FINJ'] ?? null;
        $idVAC  = $tipos['VAC']  ?? null;

        //------------------------------------
        // Helper para insertar X días de un tipo
        //------------------------------------
        $insertarDias = function ($cantidad, $idTipoIncidencia, $esVacaciones = false, $esIncapacidad = false) use (&$diasDisponibles, $idempleado, $idPeriodo, $periodo) {

            if (!$idTipoIncidencia) return;

            for ($i = 0; $i < $cantidad; $i++) {

                if (empty($diasDisponibles)) return; // No hay días para asignar

                $dia = array_shift($diasDisponibles); // tomar primer día disponible

                $idTarjetaVac = 0;
                $idTarjetaInc = 0;

                if ($esVacaciones) {

                    $tarjetaControlVac = new TarjetaVacaciones();

                    $tarjetaControlVac->idempleado           = $idempleado;
                    $tarjetaControlVac->ejercicio            = $periodo->ejercicio;
                    $tarjetaControlVac->diasvacaciones       = 1;   // 1 día por registro
                    $tarjetaControlVac->diasprimavacacional  = 0;
                    $tarjetaControlVac->fechainicio          = $dia;
                    $tarjetaControlVac->fechafin             = $dia;
                    $tarjetaControlVac->diasdescanso         = "";
                    $tarjetaControlVac->timestamp            = now();
                    $tarjetaControlVac->fechapago            = $dia;

                    $tarjetaControlVac->save();

                    // ID para insertarlo en nom10010
                    $idTarjetaVac = $tarjetaControlVac->idtcontrolvacaciones;
                }

                if ($esIncapacidad) {

                    $tarjetaControlInc = new TarjetaIncapacidad();

                    $tarjetaControlInc->idTipoIncidencia           = $idTipoIncidencia;
                    $tarjetaControlInc->idempleado           = $idempleado;
                    $tarjetaControlInc->folio           = "";

                    $tarjetaControlInc->diasautorizados       = 1;
                    $tarjetaControlInc->fechainicio           = $dia;
                    $tarjetaControlInc->descripcion           = "";
                    $tarjetaControlInc->incapacidadinicial    = "";

                    $tarjetaControlInc->ramoseguro    = "G";
                    $tarjetaControlInc->tiporiesgo    = "";
                    $tarjetaControlInc->numerocaso    = 0;
                    $tarjetaControlInc->fincaso    = 0;
                    $tarjetaControlInc->porcentajeincapacidad    = 0;
                    $tarjetaControlInc->controlmaternidad    = 0;

                    $tarjetaControlInc->nombremedico    = "";
                    $tarjetaControlInc->matriculamedico    = "";
                    $tarjetaControlInc->circunstancia    = "";

                    $tarjetaControlInc->timestamp            = now();
                    $tarjetaControlInc->controlincapacidad   = 0;
                    $tarjetaControlInc->secuelaconsecuencia   = "";
                    $tarjetaControlInc->save();

                    // ID para insertarlo en nom10010
                    $idTarjetaInc = $tarjetaControlInc->idtarjetaincapacidad;
                }

                MovimientosDiasHorasVigente::insert([
                    'idperiodo'            => $idPeriodo,
                    'idempleado'           => $idempleado,
                    'idtipoincidencia'     => $idTipoIncidencia,
                    'idtarjetaincapacidad' => $idTarjetaInc,
                    'idtcontrolvacaciones' => $idTarjetaVac,
                    'fecha'                => $dia,
                    'valor'                => 1,
                    'timestamp'            => now(),
                ]);
            }
        };

        //------------------------------------
        // 6. INSERTAR INCIDENCIAS SEGÚN EXCEL
        //------------------------------------
        $insertarDias($incapacidad, $idINC, false, true);
        $insertarDias($faltas,      $idFINJ, false, false);
        $insertarDias($vacaciones,  $idVAC, true, false);
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
