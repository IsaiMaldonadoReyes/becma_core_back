<?php

namespace App\Http\Services\Nomina\Import\Incidencias;

use App\Models\nomina\default\Empleado;
use App\Models\nomina\default\Periodo;
use App\Models\nomina\default\Conceptos;
use App\Models\nomina\default\TipoIncidencia;
use App\Models\nomina\default\MovimientosPDOVigente;
use App\Models\nomina\default\MovimientosDiasHorasVigente;
use App\Models\nomina\default\TarjetaVacaciones;
use App\Models\nomina\default\TarjetaIncapacidad;
use App\Models\nomina\default\EmpleadosPorPeriodo;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Carbon\Carbon;

class IncidenciasNominaApplier
{

    protected array $hojasSueldoImss = [
        'SUELDO_IMSS',
    ];

    protected array $hojasAsimilados = [
        'ASIMILADOS',
    ];


    public function aplicar($sheet, $row, $idempleado, $idPeriodo, $nombreHoja)
    {
        try {

            if (in_array($nombreHoja, $this->hojasSueldoImss)) {

                // vacaciones, faltas, incapacidad
                $this->insertarIncidenciasEnNominaMixta(
                    $idempleado,
                    $idPeriodo,
                    intval($sheet->getCell("AD{$row}")->getValue()),
                    trim((string) $sheet->getCell("AE{$row}")->getValue()),
                    intval($sheet->getCell("M{$row}")->getValue()),
                    intval($sheet->getCell("N{$row}")->getValue())
                );

                $this->insertarIncidenciasConceptosEnNominaMixta(
                    $idempleado,
                    $idPeriodo,
                    intval($sheet->getCell("O{$row}")->getValue()),
                    intval($sheet->getCell("P{$row}")->getValue()),
                    intval($sheet->getCell("AG{$row}")->getValue())
                );
            } else if (in_array($nombreHoja, $this->hojasAsimilados)) {
                $this->insertarIncidenciasConceptosNeto(
                    $idempleado,
                    $idPeriodo,
                    intval($sheet->getCell("AI{$row}")->getValue()),
                );
            }
        } catch (\Throwable $e) {
        }
    }

    private function insertarIncidenciasConceptosNeto($idempleado, $idPeriodo, $neto)
    {
        $concepto = Conceptos::where('tipoconcepto', 'N')
            ->where('numeroconcepto', 0)
            ->value('idconcepto');

        if (!$concepto) return;

        //dd($idempleado, $idPeriodo, $concepto, $neto);
        $actualizados = MovimientosPDOVigente::where([
            'idempleado' => (int) $idempleado,
            'idperiodo'  => (int) $idPeriodo,
            'idconcepto' => (int) $concepto,
        ])->update([
            'importetotal' => (float) $neto,
            'importetotalreportado' => 1,
            'valor'        => 0,
        ]);

        if ($actualizados === 0) {
            MovimientosPDOVigente::create([
                'idempleado'           => (int) $idempleado,
                'idperiodo'            => (int) $idPeriodo,
                'idconcepto'           => (int) $concepto,
                'idmovtopermanente'    => 0,
                'importetotal'         => (float) $neto,
                'valor'                => 0,
                'importe1'             => 0,
                'importe2'             => 0,
                'importe3'             => 0,
                'importe4'             => 0,
                'importetotalreportado' => 1,
                'importe1reportado'    => 0,
                'importe2reportado'    => 0,
                'importe3reportado'    => 0,
                'importe4reportado'    => 0,
                'valorReportado'       => 0,
                'timestamp'            => now()
            ]);
        }
    }

    private function insertarIncidenciasConceptosEnNominaMixta($idempleado, $idPeriodo, $primaAniosVacacional, $primaDiasVacacional, $diasRetroactivo)
    {
        // ===============================
        // PRIMA VACACIONAL (CONCEPTO 20)
        // ===============================
        $valorPrimaVacacional =
            ($primaAniosVacacional > 0)
            ? $primaAniosVacacional
            : (($primaDiasVacacional > 0) ? $primaDiasVacacional : 0);

        if ($valorPrimaVacacional > 0) {
            $this->insertarPrimaVacacional(
                $idempleado,
                $idPeriodo,
                $primaAniosVacacional,
                $primaDiasVacacional
            );
        }

        // ===============================
        // RETROACTIVOS (CONCEPTO 16)
        // ===============================
        if ($diasRetroactivo > 0) {
            $this->insertarRetroactivos(
                $idempleado,
                $idPeriodo,
                $diasRetroactivo
            );
        }
    }

    private function insertarRetroactivos(
        $idempleado,
        $idPeriodo,
        $diasRetroactivo
    ) {

        /*$empleado = Empleado::from('nom10001 AS emp')
            ->join('nom10034 AS empPeriodo', function ($q) use ($idPeriodo) {
                $q->on('emp.idempleado', '=', 'empPeriodo.idempleado')
                    ->where('empPeriodo.cidperiodo', '=', $idPeriodo);
            })
            ->where('emp.idempleado', $idempleado)
            ->select('empPeriodo.sueldodiario')
            ->first();
        */
        $concepto = Conceptos::where('tipoconcepto', 'P')
            ->where('numeroconcepto', 16)
            ->value('idconcepto');

        if (!$concepto) return;

        //if (!$empleado) return;

        //$monto = $empleado->sueldodiario * $diasRetroactivo;

        $movs = new MovimientosPDOVigente();
        $movs->idempleado = $idempleado;
        $movs->idperiodo = $idPeriodo;
        $movs->idconcepto = $concepto;
        $movs->idmovtopermanente   = 0;
        //$movs->importetotal        = $monto;
        $movs->importetotal        = 0;

        $movs->valor               = $diasRetroactivo;
        $movs->importe1            = 0;
        $movs->importe2            = 0;
        $movs->importe3            = 0;
        $movs->importe4            = 0;

        $movs->importetotalreportado = 0;
        $movs->importe1reportado     = 0;
        $movs->importe2reportado     = 0;
        $movs->importe3reportado     = 0;
        $movs->importe4reportado     = 0;

        $movs->valorReportado        = 1;
        $movs->timestamp = now();
        $movs->save();
    }


    private function insertarPrimaVacacional(
        $idempleado,
        $idPeriodo,
        $primaAniosVacacional,
        $primaDiasVacacional
    ) {
        $concepto = Conceptos::where('tipoconcepto', 'P')
            ->where('numeroconcepto', 20)
            ->value('idconcepto');

        if (!$concepto) return;

        $antig = ($primaAniosVacacional > 0) ? $primaAniosVacacional : 1;

        $antiguedad = Empleado::from('nom10001 AS emp')
            ->join('nom10034 AS empPeriodo', function ($q) use ($idPeriodo) {
                $q->on('emp.idempleado', '=', 'empPeriodo.idempleado')
                    ->where('empPeriodo.cidperiodo', '=', $idPeriodo);
            })
            ->join('nom10050 AS tipoPres', 'empPeriodo.TipoPrestacion', '=', 'tipoPres.IDTabla')
            ->join('nom10051 AS antig', 'antig.IDTablaPrestacion', '=', 'tipoPres.IDTabla')
            ->where('emp.idempleado', $idempleado)
            ->where('antig.Antiguedad', $antig)
            ->orderBy('antig.fechainicioVigencia', 'DESC')
            ->select('antig.DiasVacaciones', 'empPeriodo.sueldodiario', 'antig.PorcentajePrima')
            ->first();

        if (!$antiguedad) return;

        $cantidadDiasVacaciones = ($primaAniosVacacional > 0) ? $antiguedad->DiasVacaciones : (($primaDiasVacacional > 0) ? $primaDiasVacacional : 0);

        $importeTotal =
            ($antiguedad->sueldodiario * $cantidadDiasVacaciones)
            * ($antiguedad->PorcentajePrima / 100);

        $movs = new MovimientosPDOVigente();
        $movs->idempleado = $idempleado;
        $movs->idperiodo = $idPeriodo;
        $movs->idconcepto = $concepto;
        $movs->idmovtopermanente   = 0;
        $movs->importetotal = $importeTotal;
        //$movs->importetotal = 0;

        //$movs->valor            = $cantidadDiasVacaciones;
        $movs->valor            = 0;
        $movs->importe1         = 0;
        //$movs->importe2         = 0;
        //$movs->importe3         = 0;
        $movs->importe2         = $importeTotal;
        $movs->importe3         = $importeTotal;
        $movs->importe4            = 0;

        $movs->importetotalreportado = 1;
        $movs->importe1reportado     = 0;
        $movs->importe2reportado     = 0;
        $movs->importe3reportado     = 0;
        $movs->importe4reportado     = 0;

        $movs->valorReportado        = 0;
        $movs->timestamp             = now();
        $movs->save();
    }


    private function insertarIncidenciasEnNominaMixta(
        $idempleado,
        $idPeriodo,
        $incapacidadCantidad,
        $incapacidadListDias,
        $faltas,
        $vacaciones
    ) {

        // 1. Obtener rango del periodo
        $periodo = Periodo::where('idperiodo', $idPeriodo)
            ->select('fechainicio', 'fechafin', 'ejercicio')
            ->first();

        if (!$periodo) return;

        $empPeriodo = EmpleadosPorPeriodo::where('idempleado', $idempleado)
            ->where('cidperiodo', $idPeriodo)
            ->first();

        $fechaInicio = Carbon::parse($periodo->fechainicio)->startOfDay();
        $fechaFin    = Carbon::parse($periodo->fechafin)->endOfDay();

        $fechaAltaEmpleado = $empPeriodo
            ? Carbon::parse($empPeriodo->fechaalta)->startOfDay()
            : null;

        $inicioHabil = null;

        if ($fechaAltaEmpleado) {

            // 1️⃣ Entró ANTES del periodo → inicio del periodo
            if ($fechaAltaEmpleado->lt($fechaInicio)) {

                $inicioHabil = $fechaInicio;

                // 2️⃣ Entró DENTRO del periodo → fecha de alta
            } elseif (
                $fechaAltaEmpleado->gte($fechaInicio) &&
                $fechaAltaEmpleado->lte($fechaFin)
            ) {

                $inicioHabil = $fechaAltaEmpleado;

                // 3️⃣ Entró DESPUÉS del periodo → no aplica
            } else {
                $inicioHabil = null;
            }
        }

        // 2. Generar lista completa del periodo
        $diasPeriodo = [];
        for ($f = $inicioHabil->copy(); $f <= $fechaFin; $f->addDay()) {
            $diasPeriodo[] = $f->format('Y-m-d');
        }

        // 3. Días ya usados
        $diasOcupados = MovimientosDiasHorasVigente::where('idperiodo', $idPeriodo)
            ->where('idempleado', $idempleado)
            ->pluck('fecha')
            ->map(fn($d) => Carbon::parse($d)->format('Y-m-d'))
            ->toArray();

        // 4. Días disponibles automáticos
        $diasDisponibles = array_values(array_diff($diasPeriodo, $diasOcupados));

        // 5. Obtener IDs
        $tipos = TipoIncidencia::whereIn('mnemonico', ['INC', 'FINJ', 'VAC'])
            ->pluck('idtipoincidencia', 'mnemonico');

        $idINC  = $tipos['INC']  ?? null;
        $idFINJ = $tipos['FINJ'] ?? null;
        $idVAC  = $tipos['VAC']  ?? null;

        // ================================================================
        // 🔶 6. INCAPACIDAD — insertar por días EXACTOS de AE
        // ================================================================
        if ($incapacidadCantidad > 0 && !empty($incapacidadListDias)) {

            // Separar, limpiar
            $listaRaw = array_filter(array_map('trim', explode(',', $incapacidadListDias)));
            $lista    = [];

            // NORMALIZAR FECHAS COMO EN EL VALIDADOR
            foreach ($listaRaw as $valor) {

                if (is_numeric($valor)) {
                    // Convertir número Excel
                    try {
                        $dt = ExcelDate::excelToDateTimeObject($valor);
                        $lista[] = $dt->format('Y-m-d');
                    } catch (\Throwable $e) {
                        continue;
                    }
                } else {
                    // Texto dd/mm/yyyy o dd-mm-yyyy
                    $valor = str_replace('-', '/', $valor);

                    try {
                        $dt = Carbon::createFromFormat('d/m/Y', $valor)->startOfDay();
                        $lista[] = $dt->format('Y-m-d');
                    } catch (\Throwable $e) {
                        continue;
                    }
                }
            }

            foreach ($lista as $fecha) {

                // Dentro del periodo
                if ($fecha < $fechaInicio->format('Y-m-d') || $fecha > $fechaFin->format('Y-m-d')) {
                    continue;
                }

                // No usada
                if (in_array($fecha, $diasOcupados)) {
                    continue;
                }

                // Crear tarjeta de incapacidad
                $tarjeta = new TarjetaIncapacidad();
                $tarjeta->idTipoIncidencia = $idINC;
                $tarjeta->idempleado = $idempleado;
                $tarjeta->folio = "inc_$fecha";
                $tarjeta->diasautorizados = 1;
                $tarjeta->fechainicio = $fecha;
                $tarjeta->descripcion = "";
                $tarjeta->incapacidadinicial = "";

                $tarjeta->ramoseguro = "G";
                $tarjeta->tiporiesgo = "";
                $tarjeta->numerocaso = 0;
                $tarjeta->fincaso = 0;
                $tarjeta->porcentajeincapacidad = 0;
                $tarjeta->controlmaternidad = 0;

                $tarjeta->nombremedico = "";
                $tarjeta->matriculamedico = "";
                $tarjeta->circunstancia = "";
                $tarjeta->timestamp = now();
                $tarjeta->controlincapacidad = 0;
                $tarjeta->secuelaconsecuencia = "";
                $tarjeta->save();

                // Insertar en Movimientos
                MovimientosDiasHorasVigente::insert([
                    'idperiodo' => $idPeriodo,
                    'idempleado' => $idempleado,
                    'idtipoincidencia' => $idINC,
                    'idtarjetaincapacidad' => $tarjeta->idtarjetaincapacidad,
                    'idtcontrolvacaciones' => 0,
                    'fecha' => $fecha,
                    'valor' => 1,
                    'timestamp' => now(),
                ]);
            }
        }

        // ================================================================
        // 🔄 Recalcular días usados después de insertar incapacidad
        // ================================================================
        $diasOcupados = MovimientosDiasHorasVigente::where('idperiodo', $idPeriodo)
            ->where('idempleado', $idempleado)
            ->pluck('fecha')
            ->map(fn($d) => Carbon::parse($d)->format('Y-m-d'))
            ->toArray();

        $diasDisponibles = array_values(array_diff($diasPeriodo, $diasOcupados));

        // ================================================================
        // 🔶 7. FALTAS – automático
        // ================================================================
        $this->insertarDiasAutomaticos(
            $faltas,
            $idFINJ,
            $idempleado,
            $idPeriodo,
            $periodo,
            $diasDisponibles
        );

        // ================================================================
        // 🔶 8. VACACIONES – automático
        // ================================================================
        $this->insertarDiasAutomaticos(
            $vacaciones,
            $idVAC,
            $idempleado,
            $idPeriodo,
            $periodo,
            $diasDisponibles,
            true
        );
    }

    /**
     * Inserta días automáticos (faltas o vacaciones)
     */
    private function insertarDiasAutomaticos($cantidad, $idTipo, $idempleado, $idPeriodo, $periodo, &$diasDisponibles, $esVacaciones = false)
    {
        if ($cantidad <= 0 || !$idTipo) return;

        for ($i = 0; $i < $cantidad; $i++) {

            if (empty($diasDisponibles)) return;

            $dia = array_shift($diasDisponibles);

            $idTarjetaVac = 0;

            if ($esVacaciones) {
                $tarjeta = new TarjetaVacaciones();
                $tarjeta->idempleado = $idempleado;
                $tarjeta->ejercicio = $periodo->ejercicio;
                $tarjeta->diasvacaciones = 1;
                $tarjeta->diasprimavacacional  = 0;
                $tarjeta->fechainicio = $dia;
                $tarjeta->fechafin = $dia;
                $tarjeta->diasdescanso         = "";
                $tarjeta->timestamp = now();
                $tarjeta->fechapago = $dia;
                $tarjeta->save();

                $idTarjetaVac = $tarjeta->idtcontrolvacaciones;
            }

            MovimientosDiasHorasVigente::insert([
                'idperiodo' => $idPeriodo,
                'idempleado' => $idempleado,
                'idtipoincidencia' => $idTipo,
                'idtarjetaincapacidad' => 0,
                'idtcontrolvacaciones' => $idTarjetaVac,
                'fecha' => $dia,
                'valor' => 1,
                'timestamp' => now(),
            ]);
        }
    }
}
