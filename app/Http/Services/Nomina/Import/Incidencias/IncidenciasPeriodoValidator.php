<?php

namespace App\Http\Services\Nomina\Import\Incidencias;

use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class IncidenciasPeriodoValidator
{
    public function validate(Worksheet $sheet, int $row, $request)
    {
        $issues = new IncidenciasValidationBag();

        // Aquí copias TODO tu bloque de validación
        // de días del periodo y días disponibles

        return $issues;
    }
}
