<?php

namespace App\Http\Services\Nomina\Import\Incidencias;

use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use App\Models\nomina\default\Empleado;
use App\Models\nomina\default\MovimientosDiasHorasVigente;

class IncidenciasVacacionesValidator
{
    public function validate(Worksheet $sheet, int $row, $request): IncidenciasValidationBag
    {
        $issues = new IncidenciasValidationBag();

        // ---------------------------------------------------------
        // 1) Recuperar datos previos del RowValidator
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
        ]);*/


        $empleado = $rowData['empleado'] ?? null;
        $tieneDatos = $rowData['tieneDatos'] ?? false;

        if (!$empleado || !$tieneDatos) {
            return $issues; // nada que validar
        }

        $idempleado = $empleado->idempleado;
        $idPeriodo  = $request->idPeriodo;

        // ---------------------------------------------------------
        // 2) Leer vacaciones del Excel (K)
        // ---------------------------------------------------------
        $vacacionesExcel = intval($sheet->getCell("K{$row}")->getValue());

        // Si no capturó vacaciones → no validar nada
        if ($vacacionesExcel <= 0) {
            return $issues;
        }

        // ---------------------------------------------------------
        // 3) Obtener DÍAS DE VACACIONES ASIGNADOS (por antigüedad)
        // ---------------------------------------------------------
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

        // ---------------------------------------------------------
        // 4) Vacaciones ya tomadas (nom10010 con mnemonico VAC)
        // ---------------------------------------------------------
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

        // ---------------------------------------------------------
        // 5) Vacaciones disponibles
        // ---------------------------------------------------------
        $vacacionesDisponibles = max(0, $diasVacaciones - $cantidadVacacionesTomadas);

        // ---------------------------------------------------------
        // 6) Validación final
        // ---------------------------------------------------------
        if ($vacacionesExcel > $vacacionesDisponibles) {
            $issues->add(
                "El empleado solo tiene {$vacacionesDisponibles} días de vacaciones disponibles, pero capturó {$vacacionesExcel}.",
                $row,
                'K'
            );
        }

        return $issues;
    }
}
