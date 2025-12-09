<?php

namespace App\Http\Services\Nomina\Import\Incidencias;

use Illuminate\Http\Request;
use App\Http\Services\Nomina\Import\Incidencias\IncidenciasExcelReader;
use App\Http\Services\Nomina\Import\Incidencias\IncidenciasRowValidator;
use App\Http\Services\Nomina\Import\Incidencias\IncidenciasConceptValidator;
use App\Http\Services\Nomina\Import\Incidencias\IncidenciasVacacionesValidator;
use App\Http\Services\Nomina\Import\Incidencias\IncidenciasPeriodoValidator;
use App\Http\Services\Nomina\Import\Incidencias\IncidenciasImportResult;

class IncidenciasImporter
{
    public function procesar(Request $request): IncidenciasImportResult
    {
        $reader = new IncidenciasExcelReader($request->file('file'));
        $sheet = $reader->getSheet();
        $rows = $reader->getRowCount();

        $result = new IncidenciasImportResult();

        for ($row = 10; $row <= $rows; $row++) {

            // 1. Validación general
            $generalValidator = new IncidenciasRowValidator();
            $generalIssues = $generalValidator->validate($sheet, $row, $request);

            if ($generalIssues->hasErrors()) {
                $result->addErrors($generalIssues->get());
                continue;
            }

            // 2. Validación de conceptos
            $conceptValidator = new IncidenciasConceptValidator();
            $conceptIssues = $conceptValidator->validate($sheet, $row, $request);

            if ($conceptIssues->hasErrors()) {
                $result->addErrors($conceptIssues->get());
                continue;
            }

            // 3. Validación de vacaciones
            $vacValidator = new IncidenciasVacacionesValidator();
            $vacIssues = $vacValidator->validate($sheet, $row, $request);

            if ($vacIssues->hasErrors()) {
                $result->addErrors($vacIssues->get());
                continue;
            }

            // 4. Validación de días del periodo
            $periodValidator = new IncidenciasPeriodoValidator();
            $periodIssues = $periodValidator->validate($sheet, $row, $request);

            if ($periodIssues->hasErrors()) {
                $result->addErrors($periodIssues->get());
                continue;
            }

            // Si pasó todo:
            $result->addValidRow($row);
        }

        return $result;
    }
}
