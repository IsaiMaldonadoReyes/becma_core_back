<?php

namespace App\Http\Services\Nomina\Import\Incidencias;

use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use App\Models\nomina\default\MovimientosDiasHorasVigente;
use App\Models\nomina\default\Periodo;

class IncidenciasPeriodoValidator
{
    public function validate(Worksheet $sheet, int $row, $request): IncidenciasValidationBag
    {
        $issues = new IncidenciasValidationBag();

        // ---------------------------------------------------------
        // 1) Recuperar datos del RowValidator
        // ---------------------------------------------------------
        if (!$request->attributes->has('_validator_row')) {
            return $issues;
        }

        $rowData = $request->attributes->get('_validator_row');

        /*
        dd([
            'row' => $row,
            'rowData' => $rowData,
            'request' => $request,
        ]);
        */

        $empleado      = $rowData['empleado'] ?? null;
        $tieneDatos    = $rowData['tieneDatos'] ?? false;
        $sumaDiasExcel = $rowData['sumaDiasExcel'] ?? 0;

        if (!$empleado || !$tieneDatos) {
            return $issues; // nada que validar
        }

        $idempleado = $empleado->idempleado;
        $codigoEmpleado = $sheet->getCell("A{$row}")->getValue();
        $idPeriodo = $request->idPeriodo;

        // ---------------------------------------------------------
        // 2) Obtener días de pago del periodo (misma lógica)
        // ---------------------------------------------------------
        $periodo = Periodo::select('diasdepago')
            ->where('idperiodo', $idPeriodo)
            ->first();

        if (!$periodo) {
            // Error en periodo (esto normalmente se valida antes)
            return $issues;
        }

        $diasDePago = intval($periodo->diasdepago);

        // ---------------------------------------------------------
        // 3) Validación: I+J+K > días del periodo
        // ---------------------------------------------------------
        if ($sumaDiasExcel > $diasDePago) {
            $issues->add(
                "La suma I+J+K ({$sumaDiasExcel}) excede los {$diasDePago} días del periodo.",
                $row,
                'I-K'
            );
        }

        // Si ya excede días del periodo, no tiene sentido validar lo demás
        if ($issues->hasErrors()) {
            return $issues;
        }

        // ---------------------------------------------------------
        // 4) Obtener incidencias reales capturadas en nom10010
        // ---------------------------------------------------------
        $totalIncidenciasReal = MovimientosDiasHorasVigente::from('nom10010 AS mhv')
            ->join('nom10001 AS emp', 'mhv.idempleado', '=', 'emp.idempleado')
            ->where('emp.codigoempleado', $codigoEmpleado)
            ->where('mhv.idperiodo', $idPeriodo)
            ->sum('mhv.valor');

        $totalIncidenciasReal = $totalIncidenciasReal ?? 0;

        // Días restantes disponibles
        $diasValidos = $diasDePago - $totalIncidenciasReal;

        // ---------------------------------------------------------
        // 5) Validación: I+J+K > días disponibles del empleado
        // ---------------------------------------------------------
        if ($sumaDiasExcel > $diasValidos) {
            $issues->add(
                "La suma I+J+K ({$sumaDiasExcel}) excede los días disponibles ({$diasValidos}).",
                $row,
                'I-K'
            );
        }

        return $issues;
    }
}
