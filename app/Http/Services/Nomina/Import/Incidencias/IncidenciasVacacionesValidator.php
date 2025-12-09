<?php

namespace App\Http\Services\Nomina\Import\Incidencias;

use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use App\Models\nomina\GAPE\Empleado;
use App\Models\nomina\GAPE\MovimientosDiasHorasVigente;

class IncidenciasVacacionesValidator
{
    public function validate(Worksheet $sheet, int $row, $request)
    {
        $issues = new IncidenciasValidationBag();

        $codigo = trim($sheet->getCell("A{$row}")->getValue());
        $empleado = Empleado::where("codigoempleado", $codigo)->first();

        if (!$empleado) return $issues;

        $idPeriodo = $request->idPeriodo;

        $diasVacaciones = 0;
        $vacRow = intval($sheet->getCell("K" . $row)->getValue());

        // (Aquí pones toda tu lógica existente de vacaciones)
        // Y si hay error:

        // $issues->add("Mensaje de error...", $row, "K");

        return $issues;
    }
}
