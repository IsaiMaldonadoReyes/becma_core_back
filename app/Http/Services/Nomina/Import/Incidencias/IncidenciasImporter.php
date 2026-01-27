<?php

namespace App\Http\Services\Nomina\Import\Incidencias;

use PhpOffice\PhpSpreadsheet\IOFactory;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class IncidenciasImporter
{
    protected IncidenciasRowValidator $rowValidator;
    protected IncidenciasConceptValidator $conceptValidator;
    protected IncidenciasVacacionesValidator $vacacionesValidator;
    protected IncidenciasPeriodoValidator $periodoValidator;

    protected array $hojasSueldoImss = [
        'SUELDO_IMSS',
    ];

    protected array $hojasAsimilados = [
        'ASIMILADOS',
    ];

    protected array $hojasExcedente = [
        'SINDICATO',
        'TARJETA_FACIL',
        'GASTOS_POR_COMPROBAR',
    ];

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


    public function procesarHojaSueldoImss(Worksheet $sheet, Request $request): IncidenciasImportResult
    {
        $highestRow = $sheet->getHighestRow();

        $errores = [];
        $filasValidas = [];

        for ($row = 10; $row <= $highestRow; $row++) {

            $rowHasError = false;

            $issuesRow = $this->rowValidator->validateSueldoImss($sheet, $row, $request);

            if ($issuesRow->shouldSkipRow()) {
                continue;
            }

            if ($issuesRow->hasErrors()) {
                $errores = array_merge($errores, $issuesRow->errors);
                continue;
            }

            $request->attributes->set('_validator_row', $issuesRow->data);

            $issuesConcept = $this->conceptValidator->validate($sheet, $row, $request);
            if ($issuesConcept->hasErrors()) {
                $errores = array_merge($errores, $issuesConcept->errors);
                $rowHasError = true;
            }

            $issuesVac = $this->vacacionesValidator->validate($sheet, $row, $request);
            if ($issuesVac->hasErrors()) {
                $errores = array_merge($errores, $issuesVac->errors);
                $rowHasError = true;
            }

            $issuesPeriodo = $this->periodoValidator->validate($sheet, $row, $request);
            if ($issuesPeriodo->hasErrors()) {
                $errores = array_merge($errores, $issuesPeriodo->errors);
                $rowHasError = true;
            }

            if (!$rowHasError) {
                $filasValidas[] = $row;
            }
        }

        return new IncidenciasImportResult(
            sheet: $sheet,
            filasValidas: $filasValidas,
            errores: $errores
        );
    }

    public function procesarHojaAsimilados(Worksheet $sheet, Request $request): IncidenciasImportResult
    {
        $highestRow = $sheet->getHighestRow();

        $errores = [];
        $filasValidas = [];

        for ($row = 10; $row <= $highestRow; $row++) {

            $rowHasError = false;

            $issuesRow = $this->rowValidator->validateAsimilado($sheet, $row, $request);

            if ($issuesRow->shouldSkipRow()) {
                continue;
            }

            if ($issuesRow->hasErrors()) {
                $errores = array_merge($errores, $issuesRow->errors);
                continue;
            }

            $request->attributes->set('_validator_row', $issuesRow->data);

            if (!$rowHasError) {
                $filasValidas[] = $row;
            }
        }

        return new IncidenciasImportResult(
            sheet: $sheet,
            filasValidas: $filasValidas,
            errores: $errores
        );
    }

    public function procesarHojaExcedente(Worksheet $sheet, Request $request): IncidenciasImportResult
    {
        $highestRow = $sheet->getHighestRow();

        $errores = [];
        $filasValidas = [];

        for ($row = 10; $row <= $highestRow; $row++) {

            $rowHasError = false;

            $issuesRow = $this->rowValidator->validateExcedente($sheet, $row, $request);

            if ($issuesRow->shouldSkipRow()) {
                continue;
            }

            if ($issuesRow->hasErrors()) {
                $errores = array_merge($errores, $issuesRow->errors);
                continue;
            }

            $request->attributes->set('_validator_row', $issuesRow->data);

            if (!$rowHasError) {
                $filasValidas[] = $row;
            }
        }

        return new IncidenciasImportResult(
            sheet: $sheet,
            filasValidas: $filasValidas,
            errores: $errores
        );
    }

    public function procesarArchivo(Request $request): array
    {
        $path = $request->file('file')->getRealPath();
        $spreadsheet = IOFactory::load($path);

        $resultados = [];

        foreach ($spreadsheet->getAllSheets() as $sheet) {

            $nombreHoja = strtoupper($sheet->getTitle());

            if (in_array($nombreHoja, $this->hojasSueldoImss)) {
                $resultados[$nombreHoja] = $this->procesarHojaSueldoImss($sheet, $request);
            } elseif (in_array($nombreHoja, $this->hojasAsimilados)) {
                $resultados[$nombreHoja] = $this->procesarHojaAsimilados($sheet, $request);
            } elseif (in_array($nombreHoja, $this->hojasExcedente)) {
                $resultados[$nombreHoja] = $this->procesarHojaExcedente($sheet, $request);
            } else {
                // Hoja inesperada → error estructural
                $resultados[$nombreHoja] = new IncidenciasImportResult(
                    sheet: $sheet,
                    filasValidas: [],
                    errores: [[
                        'mensaje' => "La hoja '{$sheet->getTitle()}' no está permitida.",
                        'tipo' => 'estructura',
                    ]]
                );
            }
        }

        return $resultados; // ['Sueldo_imss' => IncidenciasImportResult, ...]
    }

    public function hojasExcel(Request $request): array
    {
        $path = $request->file('file')->getRealPath();
        $spreadsheet = IOFactory::load($path);

        $hojasExcel = [];

        foreach ($spreadsheet->getAllSheets() as $sheet) {
            $hojasExcel[] = $sheet->getTitle();
        }

        return $hojasExcel;
    }
}
