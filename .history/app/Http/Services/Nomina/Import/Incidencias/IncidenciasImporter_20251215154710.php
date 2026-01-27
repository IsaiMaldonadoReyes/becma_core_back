<?php

namespace App\Http\Services\Nomina\Import\Incidencias;

use PhpOffice\PhpSpreadsheet\IOFactory;
use Illuminate\Http\Request;

class IncidenciasImporter
{
    protected IncidenciasRowValidator $rowValidator;
    protected IncidenciasConceptValidator $conceptValidator;
    protected IncidenciasVacacionesValidator $vacacionesValidator;
    protected IncidenciasPeriodoValidator $periodoValidator;

    public function __construct(
        IncidenciasRowValidator        $rowValidator,
        IncidenciasConceptValidator    $conceptValidator,
        IncidenciasVacacionesValidator $vacacionesValidator,
        IncidenciasPeriodoValidator    $periodoValidator
    ) {
        $this->rowValidator        = $rowValidator;
        $this->conceptValidator    = $conceptValidator;
        $this->vacacionesValidator = $vacacionesValidator;
        $this->periodoValidator    = $periodoValidator;
    }

    /**
     * Procesa el archivo Excel y devuelve:
     *  - $sheet
     *  - $errores
     *  - $filasValidas
     */
    public function procesar(Request $request): IncidenciasImportResult
    {
        // ======================================================
        // 1. CARGAR ARCHIVO
        // ======================================================
        $path = $request->file('file')->getRealPath();
        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getActiveSheet();

        $highestRow = $sheet->getHighestRow();

        $errores = [];
        $filasValidas = [];

        // ======================================================
        // 2. RECORRER FILA POR FILA (10...N)
        // ======================================================
        for ($row = 10; $row <= $highestRow; $row++) {

            $rowHasError = false; // ← Bandera para esta fila

            // --------------------------------------------------
            // 2.1 VALIDACIÓN GENERAL DE LA FILA
            // --------------------------------------------------
            $issuesRow = $this->rowValidator->validate($sheet, $row, $request);

            // Si debe ignorarse la fila → continue
            if ($issuesRow->shouldSkipRow()) {
                continue;
            }

            // Si tiene errores de formato o empleado → registrar errores
            if ($issuesRow->hasErrors()) {
                $errores = array_merge($errores, $issuesRow->errors);
                continue;
            }

            $request->attributes->set('_validator_row', $issuesRow->data);

            // --------------------------------------------------
            // 2.2 VALIDACIÓN DE CONCEPTOS (16,10,11)
            // --------------------------------------------------
            $issuesConcept = $this->conceptValidator->validate($sheet, $row, $request);

            if ($issuesConcept->hasErrors()) {
                $errores = array_merge($errores, $issuesConcept->errors);
                $rowHasError = true; // No hacemos continue
            }

            // --------------------------------------------------
            // 2.3 VALIDACIÓN DE VACACIONES
            // --------------------------------------------------
            $issuesVac = $this->vacacionesValidator->validate($sheet, $row, $request);

            if ($issuesVac->hasErrors()) {
                $errores = array_merge($errores, $issuesVac->errors);
                $rowHasError = true; // No hacemos continue
            }

            // --------------------------------------------------
            // 2.4 VALIDACIÓN DE DÍAS DEL PERIODO
            // --------------------------------------------------
            $issuesPeriodo = $this->periodoValidator->validate($sheet, $row, $request);

            if ($issuesPeriodo->hasErrors()) {
                $errores = array_merge($errores, $issuesPeriodo->errors);
                $rowHasError = true; // No hacemos continue
            }

            // --------------------------------------------------
            // 2.5 SI TODAS LAS VALIDACIONES PASARON
            // --------------------------------------------------
            if (!$rowHasError) {
                $filasValidas[] = $row;
            }
        }

        // ======================================================
        // 3. RETORNAR RESULTADO
        // ======================================================
        return new IncidenciasImportResult(
            sheet: $sheet,
            filasValidas: $filasValidas,
            errores: $errores
        );
    }
}
