<?php

namespace App\Http\Services\Nomina\Import\Incidencias;

use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use App\Models\nomina\default\Empleado;
use App\Models\nomina\default\MovimientosDiasHorasVigente;
use App\Models\nomina\default\Periodo;
use Carbon\Carbon;

class IncidenciasRowValidator
{

    public function validate(Worksheet $sheet, int $row, $request): IncidenciasValidationBag
    {
        $issues = new IncidenciasValidationBag();

        // ---------------------------------------------------------
        // 1. Código de empleado
        // ---------------------------------------------------------
        $codigoEmpleado = trim((string)$sheet->getCell("A{$row}")->getValue());

        if ($codigoEmpleado === "") {
            $issues->markSkipRow();
            return $issues;
        }

        // ---------------------------------------------------------
        // 2. Validar existencia del empleado
        // ---------------------------------------------------------
        $empleado = Empleado::select('idempleado')
            ->where('codigoempleado', $codigoEmpleado)
            ->first();

        if (!$empleado) {
            $issues->add("El empleado con código '{$codigoEmpleado}' no existe.", $row, 'A');
            return $issues;
        }

        // ---------------------------------------------------------
        // 3. Columnas: enteros, decimales, omitidas
        // ---------------------------------------------------------
        $enteros = ['M', 'N', 'O', 'P', 'R', 'AD', 'AG'];

        $decimales = [];
        $startIndex = Coordinate::columnIndexFromString('Q');
        $endIndex   = Coordinate::columnIndexFromString('AC');

        for ($i = $startIndex; $i <= $endIndex; $i++) {
            $colLetter = Coordinate::stringFromColumnIndex($i);
            if ($colLetter !== 'R') {
                $decimales[] = $colLetter;
            }
        }

        $tieneDatos    = false;
        $sumaDiasExcel = 0;

        // ---------------------------------------------------------
        // 4. Validación general de columnas
        // ---------------------------------------------------------
        $todasColumnas = array_merge($enteros, $decimales);

        foreach ($todasColumnas as $colLetter) {

            $cellValue = trim((string)$sheet->getCell("{$colLetter}{$row}")->getValue());

            if ($cellValue !== "") {
                $tieneDatos = true;
            } else {
                continue;
            }

            // ENTEROS POSITIVOS
            if (in_array($colLetter, $enteros)) {

                if (!preg_match('/^[1-9]\d*$/', $cellValue)) {
                    $issues->add(
                        "El valor '{$cellValue}' en {$colLetter}{$row} debe ser un entero mayor a 0.",
                        $row,
                        $colLetter
                    );
                } else {
                    if (in_array($colLetter, ['M', 'N', 'AD'])) {
                        $sumaDiasExcel += intval($cellValue);
                    }
                }

                continue;
            }

            // DECIMALES
            if (in_array($colLetter, $decimales)) {

                if (!preg_match('/^\s*-?(?:\d+|\d*\.\d+)\s*$/', $cellValue)) {
                    $issues->add(
                        "El valor '{$cellValue}' en {$colLetter}{$row} debe ser decimal o entero.",
                        $row,
                        $colLetter
                    );
                }

                continue;
            }
        }

        if (!$tieneDatos) {
            $issues->markSkipRow();
            return $issues;
        }

        // ---------------------------------------------------------
        // 5. Validación de fechas AE (incapacidad)
        // ---------------------------------------------------------
        $valorAD = intval($sheet->getCell("AD{$row}")->getValue());
        $valorAE = trim((string)$sheet->getCell("AE{$row}")->getValue());

        if ($valorAD > 0) {

            if ($valorAE === "") {
                $issues->add("Debe capturar las fechas de incapacidad en AE{$row}.", $row, 'AE');
            } else {

                // Convertir "valorAE" a array de fechas separadas
                $fechasRaw = array_map('trim', explode(',', $valorAE));
                $fechas    = [];

                // NORMALIZACIÓN
                foreach ($fechasRaw as $f) {

                    if (is_numeric($f)) {
                        // Convertir número Excel a dd/mm/yyyy
                        try {
                            $dt = ExcelDate::excelToDateTimeObject($f);
                            $fechas[] = $dt->format('d/m/Y');
                        } catch (\Throwable $e) {
                            $issues->add("La fecha '{$f}' no se pudo interpretar como fecha de Excel.", $row, 'AE');
                        }
                    } else {
                        // Mantener string tal cual
                        $fechas[] = $f;
                    }
                }

                // Validar cantidad
                if (count($fechas) !== $valorAD) {
                    $issues->add(
                        "El número de fechas en AE{$row} debe coincidir con el valor de AD ({$valorAD}).",
                        $row,
                        'AE'
                    );
                }

                // Rango del periodo
                $periodo = Periodo::find($request->idPeriodo);

                $inicio = Carbon::parse($periodo->fechainicio)->startOfDay();
                $fin    = Carbon::parse($periodo->fechafin)->endOfDay();

                // Fechas ya usadas
                $fechasUsadas = MovimientosDiasHorasVigente::where('idempleado', $empleado->idempleado)
                    ->where('idperiodo', $request->idPeriodo)
                    ->pluck('fecha')
                    ->map(fn($f) => Carbon::parse($f)->format('Y-m-d'))
                    ->toArray();

                $fechasSet = [];

                foreach ($fechas as $fechaStr) {

                    // Validar formato
                    try {
                        $fecha = Carbon::createFromFormat('d/m/Y', $fechaStr)->startOfDay();
                    } catch (\Exception $e) {
                        $issues->add("La fecha '{$fechaStr}' no tiene formato válido (dd/mm/yyyy).", $row, 'AE');
                        continue;
                    }

                    // Validar periodo
                    if ($fecha->lt($inicio) || $fecha->gt($fin)) {
                        $issues->add(
                            "La fecha '{$fechaStr}' está fuera del periodo ({$inicio->format('d-m-Y')} al {$fin->format('d-m-Y')}).",
                            $row,
                            'AE'
                        );
                    }

                    // Validar duplicados
                    $key = $fecha->format('Y-m-d');

                    if (isset($fechasSet[$key])) {
                        $issues->add("La fecha '{$fechaStr}' está duplicada.", $row, 'AE');
                    } else {
                        $fechasSet[$key] = true;
                    }

                    // Validar usadas previamente
                    if (in_array($key, $fechasUsadas)) {
                        $issues->add("La fecha '{$fechaStr}' ya fue usada previamente.", $row, 'AE');
                    }
                }
            }
        }

        // ---------------------------------------------------------
        // 6. Guardar datos para otros validadores
        // ---------------------------------------------------------
        $issues->set('empleado', $empleado);
        $issues->set('sumaDiasExcel', $sumaDiasExcel);
        $issues->set('tieneDatos', $tieneDatos);

        return $issues;
    }


    public function validate3(Worksheet $sheet, int $row, $request): IncidenciasValidationBag
    {
        $issues = new IncidenciasValidationBag();

        // ---------------------------------------------------------
        // 1. Código de empleado
        // ---------------------------------------------------------
        $codigoEmpleado = trim((string)$sheet->getCell("A{$row}")->getValue());

        if ($codigoEmpleado === "") {
            $issues->markSkipRow();
            return $issues;
        }

        // ---------------------------------------------------------
        // 2. Validar existencia del empleado
        // ---------------------------------------------------------
        $empleado = Empleado::select('idempleado')
            ->where('codigoempleado', $codigoEmpleado)
            ->first();

        if (!$empleado) {
            $issues->add("El empleado con código '{$codigoEmpleado}' no existe.", $row, 'A');
            return $issues;
        }

        // ---------------------------------------------------------
        // COLUMNAS SEGÚN NUEVAS REGLAS
        // ---------------------------------------------------------

        // Enteros positivos
        $enteros = [
            'M', // faltas
            'N', // vacaciones
            'P', // prima vacacional
            'R', // dias festivos
            'AD', // incapacidad
            'AG'  // dias retroactivos
        ];

        // Decimales / enteros mixtos (Q–AC excepto R)
        $decimales = [];

        $startIndex = Coordinate::columnIndexFromString('Q');
        $endIndex   = Coordinate::columnIndexFromString('AC');

        for ($i = $startIndex; $i <= $endIndex; $i++) {
            $colLetter = Coordinate::stringFromColumnIndex($i);

            // Excluir la R del rango
            if ($colLetter === 'R') {
                continue;
            }

            $decimales[] = $colLetter;
        }

        // Excluir columna O
        $columnasOmitidas = ['O'];

        $tieneDatos = false;
        $sumaDiasExcel = 0;

        // ---------------------------------------------------------
        // 3. VALIDACIÓN DE FORMATO POR COLUMNA
        // ---------------------------------------------------------
        $todasColumnas = array_merge($enteros, $decimales);

        foreach ($todasColumnas as $colLetter) {

            if (in_array($colLetter, $columnasOmitidas)) {
                continue;
            }

            $cellValue = trim((string)$sheet->getCell("{$colLetter}{$row}")->getValue());

            if ($cellValue !== "" && $cellValue !== null) {
                $tieneDatos = true;
            } else {
                continue;
            }

            // ------------------ ENTEROS POSITIVOS
            if (in_array($colLetter, $enteros)) {

                if (!preg_match('/^[1-9]\d*$/', $cellValue)) {
                    $issues->add(
                        "El valor '{$cellValue}' en {$colLetter}{$row} debe ser un entero mayor a 0.",
                        $row,
                        $colLetter
                    );
                } else {
                    // Sumar solo M, N, AD
                    if (in_array($colLetter, ['M', 'N', 'AD'])) {
                        $sumaDiasExcel += intval($cellValue);
                    }
                }

                continue;
            }

            // ------------------ DECIMALES O ENTEROS
            if (in_array($colLetter, $decimales)) {

                if (!preg_match('/^\s*-?(?:\d+|\d*\.\d+)\s*$/', $cellValue)) {
                    $issues->add(
                        "El valor '{$cellValue}' en {$colLetter}{$row} debe ser decimal o entero.",
                        $row,
                        $colLetter
                    );
                }

                continue;
            }
        }

        // Si NO tiene datos → ignorar fila
        if (!$tieneDatos) {
            $issues->markSkipRow();
            return $issues;
        }

        // ---------------------------------------------------------
        // 4. VALIDACIÓN DE COLUMNA AE (FECHAS DE INCAPACIDAD)
        // ---------------------------------------------------------
        $valorAD = intval($sheet->getCell("AD{$row}")->getValue());
        $valorAE = trim((string)$sheet->getCell("AE{$row}")->getValue());

        if ($valorAD > 0) {

            if ($valorAE === "") {
                $issues->add("Debe capturar las fechas de incapacidad en AE{$row}.", $row, 'AE');
            } else {
                $fechas = array_map('trim', explode(',', $valorAE));

                // Validar cantidad
                if (count($fechas) !== $valorAD) {
                    $issues->add(
                        "El número de fechas en AE{$row} debe coincidir con el valor de AD ({$valorAD}).",
                        $row,
                        'AE'
                    );
                }

                // Validar formato y rango del periodo
                $periodo = Periodo::find($request->idPeriodo);

                $inicio = Carbon::parse($periodo->fechainicio)->startOfDay();
                $fin    = Carbon::parse($periodo->fechafin)->endOfDay();

                $fechasUsadas = MovimientosDiasHorasVigente::where('idempleado', $empleado->idempleado)
                    ->where('idperiodo', $request->idPeriodo)
                    ->pluck('fecha')
                    ->map(fn($f) => Carbon::parse($f)->format('Y-m-d'))
                    ->toArray();

                $fechasSet = [];

                foreach ($fechas as $fechaStr) {

                    $fechaStr = trim($fechaStr);

                    // Validar formato
                    try {
                        $fecha = Carbon::createFromFormat('d/m/Y', $fechaStr)->startOfDay();
                    } catch (\Exception $e) {
                        $issues->add("La fecha '{$fechaStr}' no tiene formato válido (dd/mm/yyyy).", $row, 'AE');
                        continue;
                    }

                    // Validar dentro del periodo
                    if ($fecha->lt($inicio) || $fecha->gt($fin)) {
                        $issues->add("La fecha '{$fechaStr}' está fuera del periodo ({$inicio->format('d-m-Y')} al {$fin->format('d-m-Y')}).", $row, 'AE');
                    }

                    // Validar duplicados en la misma lista
                    $key = $fecha->format('Y-m-d');
                    if (isset($fechasSet[$key])) {
                        $issues->add("La fecha '{$fechaStr}' está duplicada.", $row, 'AE');
                    } else {
                        $fechasSet[$key] = true;
                    }

                    // Validar que la fecha no esté usada antes
                    if (in_array($key, $fechasUsadas)) {
                        $issues->add("La fecha '{$fechaStr}' ya fue usada previamente.", $row, 'AE');
                    }
                }
            }
        }

        // ---------------------------------------------------------
        // 5. Guardar datos para los demás validadores
        // ---------------------------------------------------------
        $issues->set('empleado', $empleado);
        $issues->set('sumaDiasExcel', $sumaDiasExcel);
        $issues->set('tieneDatos', $tieneDatos);

        return $issues;
    }

    public function validate2(Worksheet $sheet, int $row, $request): IncidenciasValidationBag
    {
        $issues = new IncidenciasValidationBag();

        // ---------------------------------------------------------
        // 1. Leer código de empleado
        // ---------------------------------------------------------
        $codigoEmpleado = trim((string)$sheet->getCell("A{$row}")->getValue());

        // Si está vacío, ignorar fila (igual que tu código)
        if ($codigoEmpleado === "") {
            $issues->markSkipRow();
            return $issues;
        }

        // ---------------------------------------------------------
        // 2. Validar existencia del empleado
        // ---------------------------------------------------------
        $empleado = Empleado::select('idempleado')
            ->where('codigoempleado', $codigoEmpleado)
            ->first();

        if (!$empleado) {
            $issues->add(
                "El empleado con código '{$codigoEmpleado}' no existe.",
                $row,
                'A'
            );
            return $issues;
        }

        // ---------------------------------------------------------
        // PARÁMETROS QUE VIENEN DEL EXCEL
        // ---------------------------------------------------------
        $colInicioEnteros   = Coordinate::columnIndexFromString('I'); // enteros positivos
        $colFinEnteros      = Coordinate::columnIndexFromString('N');
        $colInicioDecimales = Coordinate::columnIndexFromString('O');
        $colFinDecimales    = Coordinate::columnIndexFromString('V');

        $tieneDatos = false;
        $sumaDiasExcel = 0;

        // ---------------------------------------------------------
        // 3. Recorrer columnas I–V para validar formato
        // ---------------------------------------------------------
        for ($col = $colInicioEnteros; $col <= $colFinDecimales; $col++) {

            $colLetter = Coordinate::stringFromColumnIndex($col);
            $cellValue = trim((string)$sheet->getCell("{$colLetter}{$row}")->getValue());

            // Detectar si hay datos capturados
            if ($cellValue !== "" && $cellValue !== null) {
                $tieneDatos = true;
            }

            // Si está vacío → permitido
            if ($cellValue === "" || $cellValue === null) {
                continue;
            }

            // ------------------ ENTEROS POSITIVOS (I–N)
            if ($col >= $colInicioEnteros && $col <= $colFinEnteros) {

                if (!preg_match('/^[1-9]\d*$/', $cellValue)) {
                    $issues->add(
                        "El valor '{$cellValue}' en {$colLetter}{$row} debe ser un entero mayor a 0.",
                        $row,
                        $colLetter
                    );
                } else {
                    // Suma de días (solo I, J, K)
                    if (in_array($colLetter, ['I', 'J', 'K'])) {
                        $sumaDiasExcel += intval($cellValue);
                    }
                }

                continue;
            }

            // ------------------ DECIMALES (O–V)
            if ($col >= $colInicioDecimales && $col <= $colFinDecimales) {

                if (!preg_match('/^\s*-?(?:\d+|\d*\.\d+)\s*$/', $cellValue)) {
                    $issues->add(
                        "El valor '{$cellValue}' en {$colLetter}{$row} debe ser decimal.",
                        $row,
                        $colLetter
                    );
                }

                continue;
            }
        }

        // Si NO tiene datos → ignorar fila
        if (!$tieneDatos) {
            $issues->markSkipRow();
            return $issues;
        }

        // ---------------------------------------------------------
        // 4. Guardar datos importantes para próximos validadores
        // ---------------------------------------------------------
        $issues->set('empleado', $empleado);
        $issues->set('sumaDiasExcel', $sumaDiasExcel);
        $issues->set('tieneDatos', $tieneDatos);

        return $issues;
    }
}
