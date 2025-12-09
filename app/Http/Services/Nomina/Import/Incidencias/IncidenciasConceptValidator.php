<?php

namespace App\Http\Services\Nomina\Import\Incidencias;

use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use App\Models\nomina\GAPE\MovimientosPDOVigente;
use App\Models\nomina\GAPE\Empleado;

class IncidenciasConceptValidator
{
    public function validate(Worksheet $sheet, int $row, $request)
    {
        $issues = new IncidenciasValidationBag();

        $codigoEmpleado = trim($sheet->getCell("A" . $row)->getValue());
        $idPeriodo = $request->idPeriodo;

        $empleado = Empleado::select("idempleado")
            ->where("codigoempleado", $codigoEmpleado)
            ->first();

        if (!$empleado) return $issues;

        $conceptosAValidar = [
            16 => ['col' => 'L', 'desc' => 'Días retroactivos'],
            10 => ['col' => 'M', 'desc' => 'Prima dominical'],
            11 => ['col' => 'N', 'desc' => 'Días festivos'],
        ];

        $conceptosYaExistentes = MovimientosPDOVigente::from('nom10008 AS movs')
            ->join('nom10004 AS con', 'movs.idconcepto', '=', 'con.idconcepto')
            ->where('movs.idempleado', $empleado->idempleado)
            ->where('movs.idperiodo', $idPeriodo)
            ->where('con.tipoconcepto', 'P')
            ->pluck('con.numeroconcepto')
            ->toArray();

        foreach ($conceptosAValidar as $numConcepto => $item) {

            $valor = intval($sheet->getCell($item['col'] . $row)->getValue());

            if ($valor > 0 && in_array($numConcepto, $conceptosYaExistentes)) {
                $issues->add(
                    "El concepto '{$item['desc']}' ya existe y no puede duplicarse.",
                    $row,
                    $item['col']
                );
            }
        }

        return $issues;
    }
}
