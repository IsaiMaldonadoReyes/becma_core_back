<?php

namespace App\Http\Services\Nomina\Import\Incidencias;

use App\Models\nomina\GAPE\Empleado;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class IncidenciasRowValidator
{
    public function validate(Worksheet $sheet, int $row, $request)
    {
        $issues = new IncidenciasValidationBag();

        // Código empleado
        $codigo = trim((string)$sheet->getCell("A" . $row)->getValue());

        if ($codigo === "") {
            return $issues; // fila vacía, no la procesamos
        }

        // Existe?
        $empleado = Empleado::where("codigoempleado", $codigo)->first();
        if (!$empleado) {
            $issues->add("El empleado {$codigo} no existe.", $row, "A");
        }

        return $issues;
    }
}
