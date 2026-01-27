<?php

namespace App\Http\Services\Nomina\Import\Incidencias;

use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use App\Models\nomina\default\Empleado;
use App\Models\nomina\default\EmpleadosPorPeriodo;
use App\Models\nomina\default\MovimientosDiasHorasVigente;
use App\Models\nomina\default\Periodo;
use App\Models\nomina\GAPE\NominaGapeEmpleado;
use Carbon\Carbon;

class IncidenciasRowValidator
{

    public function validateSueldoImss(Worksheet $sheet, int $row, $request): IncidenciasValidationBag
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
            $issues->add(
                "El empleado con código '{$codigoEmpleado}' no existe.",
                $row,
                'A',
                'nomina',
                'Información incorrecta',
                $codigoEmpleado
            );
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
                        $colLetter,
                        'formato',
                        'numerico',
                        $cellValue
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
                        $colLetter,
                        'formato',
                        'decimal',
                        $cellValue
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
                $issues->add(
                    "Debe capturar las fechas de incapacidad en AE{$row}.",
                    $row,
                    'AE',
                    'nomina',
                    'fechasInvalidas',
                    $cellValue
                );
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
                            $issues->add(
                                "La fecha '{$f}' no se pudo interpretar como fecha de Excel.",
                                $row,
                                'AE',
                                'nomina',
                                'fechasInvalidas',
                                $f
                            );
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
                        'AE',
                        'nomina',
                        'fechasInvalidas',
                        $valorAD
                    );
                }

                $empPeriodo = EmpleadosPorPeriodo::where('idempleado', $empleado->idempleado)
                    ->where('cidperiodo', $request->idPeriodo)
                    ->first();

                $periodo = Periodo::find($request->idPeriodo);

                $inicio = Carbon::parse($periodo->fechainicio)->startOfDay();
                $fin    = Carbon::parse($periodo->fechafin)->endOfDay();

                $fechaAltaEmpleado = $empPeriodo
                    ? Carbon::parse($empPeriodo->fechaalta)->startOfDay()
                    : null;

                $inicioHabil = null;

                if ($fechaAltaEmpleado) {

                    // 1️⃣ Entró ANTES del periodo → inicio del periodo
                    if ($fechaAltaEmpleado->lt($inicio)) {

                        $inicioHabil = $inicio;

                        // 2️⃣ Entró DENTRO del periodo → fecha de alta
                    } elseif (
                        $fechaAltaEmpleado->gte($inicio) &&
                        $fechaAltaEmpleado->lte($fin)
                    ) {

                        $inicioHabil = $fechaAltaEmpleado;

                        // 3️⃣ Entró DESPUÉS del periodo → no aplica
                    } else {
                        $inicioHabil = null;
                    }
                }

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
                        $issues->add(
                            "La fecha '{$fechaStr}' no tiene formato válido (dd/mm/yyyy).",
                            $row,
                            'AE',
                            'nomina',
                            'fechasInvalidas',
                            $fechaStr
                        );
                        continue;
                    }

                    // Validar periodo
                    if ($fecha->lt($inicioHabil) || $fecha->gt($fin)) {
                        $issues->add(
                            "La fecha '{$fechaStr}' está fuera del periodo ({$inicioHabil->format('d-m-Y')} al {$fin->format('d-m-Y')}).",
                            $row,
                            'AE',
                            'nomina',
                            'fechasInvalidas',
                            $fechaStr
                        );
                    }

                    // Validar duplicados
                    $key = $fecha->format('Y-m-d');

                    if (isset($fechasSet[$key])) {
                        $issues->add(
                            "La fecha '{$fechaStr}' está duplicada.",
                            $row,
                            'AE',
                            'nomina',
                            'fechasInvalidas',
                            $fechaStr
                        );
                    } else {
                        $fechasSet[$key] = true;
                    }

                    // Validar usadas previamente
                    if (in_array($key, $fechasUsadas)) {
                        $issues->add(
                            "La fecha '{$fechaStr}' ya fue usada previamente.",
                            $row,
                            'AE',
                            'nomina',
                            'fechasInvalidas',
                            $fechaStr
                        );
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

    public function validateAsimilado(Worksheet $sheet, int $row, $request): IncidenciasValidationBag
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
            $issues->add(
                "El empleado con código '{$codigoEmpleado}' no existe.",
                $row,
                'A',
                'nomina',
                'Información incorrecta',
                $codigoEmpleado
            );
            return $issues;
        }

        // ---------------------------------------------------------
        // 3. Columnas: enteros, decimales, omitidas
        // ---------------------------------------------------------
        $enteros = ['R'];

        $decimales = ['AI'];
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
                        $colLetter,
                        'formato',
                        'numerico',
                        $cellValue
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
                        $colLetter,
                        'formato',
                        'decimal',
                        $cellValue
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
        // 6. Guardar datos para otros validadores
        // ---------------------------------------------------------
        $issues->set('empleado', $empleado);
        $issues->set('sumaDiasExcel', $sumaDiasExcel);
        $issues->set('tieneDatos', $tieneDatos);

        return $issues;
    }

    public function validateExcedente(Worksheet $sheet, int $row, $request): IncidenciasValidationBag
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
        $empleado = NominaGapeEmpleado::select('idempleado')
            ->where('codigoempleado', $codigoEmpleado)
            ->first();

        if (!$empleado) {
            $issues->add(
                "El empleado con código '{$codigoEmpleado}' no existe.",
                $row,
                'A',
                'nomina',
                'Información incorrecta',
                $codigoEmpleado
            );
            return $issues;
        }

        // ---------------------------------------------------------
        // 3. Columnas: enteros, decimales, omitidas
        // ---------------------------------------------------------
        $enteros = ['R'];

        $decimales = ['AH'];
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
                        $colLetter,
                        'formato',
                        'numerico',
                        $cellValue
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
                        $colLetter,
                        'formato',
                        'decimal',
                        $cellValue
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
        // 6. Guardar datos para otros validadores
        // ---------------------------------------------------------
        $issues->set('empleado', $empleado);
        $issues->set('sumaDiasExcel', $sumaDiasExcel);
        $issues->set('tieneDatos', $tieneDatos);

        return $issues;
    }
}
