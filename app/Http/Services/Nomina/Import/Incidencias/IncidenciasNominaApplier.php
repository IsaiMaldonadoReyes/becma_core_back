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
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Carbon\Carbon;

class IncidenciasNominaApplier
{
    public function aplicar($sheet, $row, $idempleado, $idPeriodo)
    {
        try {
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
                intval($sheet->getCell("P{$row}")->getValue())
            );
        } catch (\Throwable $e) {
        }
    }

    private function insertarIncidenciasConceptosEnNominaMixta($idempleado, $idPeriodo, $primaAniosVacacional, $primaDiasVacacional)
    {
        // Mapeo numeroconcepto => valor capturado
        $valorPrimaVacacional =
            ($primaAniosVacacional > 0)
            ? $primaAniosVacacional
            : (($primaDiasVacacional > 0) ? $primaDiasVacacional : 0);

        $valores = [
            20 => $valorPrimaVacacional,
        ];

        // Buscar idconcepto reales
        $conceptos = Conceptos::select('idconcepto', 'numeroconcepto')
            ->where('tipoconcepto', 'P')
            ->whereIn('numeroconcepto', array_keys($valores))
            ->pluck('idconcepto', 'numeroconcepto');

        // Función para insertar un movimiento
        $insertar = function ($cantidad, $idConcepto) use ($idempleado, $idPeriodo, $primaAniosVacacional, $primaDiasVacacional) {
            // No insertar conceptos vacíos
            if ($cantidad <= 0) {
                return;
            }

            // Si no existe idConcepto → no insertarlo
            if (!$idConcepto) {
                return;
            }

            $antig = ($primaAniosVacacional > 0) ? $primaAniosVacacional : 1;

            $antiguedad = Empleado::from('nom10001 AS emp')
                ->join('nom10034 AS empPeriodo', function ($q) use ($idPeriodo) {
                    $q->on('emp.idempleado', '=', 'empPeriodo.idempleado')
                        ->where('empPeriodo.cidperiodo', '=', $idPeriodo);
                })
                ->join('nom10002 AS periodo', 'empPeriodo.cidperiodo', '=', 'periodo.idperiodo')
                ->join('nom10050 AS tipoPres', 'empPeriodo.TipoPrestacion', '=', 'tipoPres.IDTabla')
                ->join('nom10051 AS antig', 'antig.IDTablaPrestacion', '=', 'tipoPres.IDTabla')
                ->where('emp.idempleado', $idempleado)
                ->where('antig.Antiguedad', $antig)
                ->orderBy('antig.fechainicioVigencia', 'DESC')
                ->select('antig.DiasVacaciones', 'empPeriodo.sueldodiario', 'emp.codigoempleado', 'antig.PorcentajePrima')
                ->first();

            $importeTotal = 0;

            if ($antiguedad) {

                $diasAntiguedad = ($primaAniosVacacional > 0) ? $antiguedad->DiasVacaciones : $primaDiasVacacional;

                $importeTotal = ($antiguedad->sueldodiario * $diasAntiguedad) * ($antiguedad->PorcentajePrima / 100);
            }

            $importetotalreportado = 1;

            $movs = new MovimientosPDOVigente();

            $movs->idempleado          = $idempleado;
            $movs->idperiodo           = $idPeriodo;
            $movs->idconcepto          = $idConcepto;
            $movs->idmovtopermanente   = 0;
            $movs->importetotal        = $importeTotal;

            $movs->valor               = 0;
            $movs->importe1            = 0;
            $movs->importe2            = $importeTotal;
            $movs->importe3            = $importeTotal;
            $movs->importe4            = 0;

            // Reportado solo cuando hay valor
            $movs->importetotalreportado = $importetotalreportado;
            $movs->importe1reportado     = 0;
            $movs->importe2reportado     = 0;
            $movs->importe3reportado     = 0;
            $movs->importe4reportado     = 0;

            $movs->valorReportado        = 0;
            $movs->timestamp             = now();

            $movs->save();
        };

        // Ejecutar para cada concepto
        foreach ($valores as $num => $cantidad) {
            $insertar($cantidad, $conceptos[$num] ?? null);
        }
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

        $fechaInicio = Carbon::parse($periodo->fechainicio)->startOfDay();
        $fechaFin    = Carbon::parse($periodo->fechafin)->endOfDay();

        // 2. Generar lista completa del periodo
        $diasPeriodo = [];
        for ($f = $fechaInicio->copy(); $f <= $fechaFin; $f->addDay()) {
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


    private function insertarIncidenciasEnNominaMixta2(
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

        $fechaInicio = Carbon::parse($periodo->fechainicio);
        $fechaFin    = Carbon::parse($periodo->fechafin);

        // 2. Generar lista completa del periodo
        $diasPeriodo = [];
        for ($f = $fechaInicio->copy(); $f <= $fechaFin; $f->addDay()) {
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

        // 5. IDs de incidencias
        $tipos = TipoIncidencia::whereIn('mnemonico', ['INC', 'FINJ', 'VAC'])
            ->pluck('idtipoincidencia', 'mnemonico');

        $idINC  = $tipos['INC']  ?? null;
        $idFINJ = $tipos['FINJ'] ?? null;
        $idVAC  = $tipos['VAC']  ?? null;

        // ================================================================
        // 🔶 6. INCAPACIDAD — insertar por días EXACTOS de AE
        // ================================================================
        if ($incapacidadCantidad > 0 && !empty($incapacidadListDias)) {

            $lista = array_filter(array_map('trim', explode(',', $incapacidadListDias)));

            foreach ($lista as $dia) {
                // Normalizar formato
                try {
                    $fecha = Carbon::parse($dia)->format('Y-m-d');
                } catch (\Throwable $e) {
                    continue; // si falla, ignorar esa fecha
                }

                // Debe estar dentro del periodo
                if ($fecha < $fechaInicio->format('Y-m-d') || $fecha > $fechaFin->format('Y-m-d')) {
                    continue;
                }

                // Debe NO estar ya usado
                if (in_array($fecha, $diasOcupados)) {
                    continue;
                }

                // Crear tarjeta
                $tarjeta = new TarjetaIncapacidad();
                $tarjeta->idTipoIncidencia = $idINC;
                $tarjeta->idempleado = $idempleado;
                $tarjeta->folio = "inc_$fecha";
                $tarjeta->diasautorizados = 1;
                $tarjeta->fechainicio = $fecha;
                $tarjeta->descripcion           = "";
                $tarjeta->incapacidadinicial    = "";

                $tarjeta->ramoseguro    = "G";
                $tarjeta->tiporiesgo    = "";
                $tarjeta->numerocaso    = 0;
                $tarjeta->fincaso    = 0;
                $tarjeta->porcentajeincapacidad    = 0;
                $tarjeta->controlmaternidad    = 0;

                $tarjeta->nombremedico    = "";
                $tarjeta->matriculamedico    = "";
                $tarjeta->circunstancia    = "";
                $tarjeta->timestamp = now();
                $tarjeta->controlincapacidad   = 0;
                $tarjeta->secuelaconsecuencia   = "";
                $tarjeta->save();

                // Insertar en nom10010
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

        // 3. Días ya usados después de incapacidad

        $diasOcupados = MovimientosDiasHorasVigente::where('idperiodo', $idPeriodo)
            ->where('idempleado', $idempleado)
            ->pluck('fecha')
            ->map(fn($d) => Carbon::parse($d)->format('Y-m-d'))
            ->toArray();

        // 4. Días disponibles automáticos
        $diasDisponibles = array_values(array_diff($diasPeriodo, $diasOcupados));

        // ================================================================
        // 🔶 7. FALTAS – automático
        // ================================================================
        $this->insertarDiasAutomaticos($faltas, $idFINJ, $idempleado, $idPeriodo, $periodo, $diasDisponibles);

        // ================================================================
        // 🔶 8. VACACIONES – automático
        // ================================================================
        $this->insertarDiasAutomaticos($vacaciones, $idVAC, $idempleado, $idPeriodo, $periodo, $diasDisponibles, true);
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
                    $tarjetaControlInc->folio           = "inc_$dia";

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
}
