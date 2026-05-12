<?php 

namespace App\Http\Services\Excel\Strategies\Export;

use App\Http\Services\Excel\Strategies\Contracts\ColumnStrategyInterface;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;

class DateColumnStrategy implements ColumnStrategyInterface
{
    public function apply($sheet, $cell, $column)
    {
        $validationConfig = $column['validacion'] ?? [];

        $validation = $sheet->getCell($cell)->getDataValidation();

        // Tipo fecha
        $validation->setType(DataValidation::TYPE_DATE);

        // Permitir o no vacío
        $validation->setAllowBlank(!($validationConfig['esRequerido'] ?? false));

        // Operador entre fechas
        $validation->setOperator(DataValidation::OPERATOR_BETWEEN);

        // Fechas desde config (formato: dd/mm/yyyy)
        $min = $validationConfig['valorMinimoRequerido'] ?? '01/01/2000';
        $max = $validationConfig['valorMaximoRequerido'] ?? '31/12/2100';

        // Convertir a formato Excel
        $minDate = $this->formatDateForExcel($min);
        $maxDate = $this->formatDateForExcel($max);

        $validation->setFormula1("DATE({$minDate})");
        $validation->setFormula2("DATE({$maxDate})");

        // Mensajes de error
        $validation->setErrorStyle(DataValidation::STYLE_STOP);
        $validation->setShowErrorMessage(true);
        $validation->setErrorTitle('Fecha inválida');
        $validation->setError(
            $validationConfig['ayudaCeldaTexto'] ?? 'Ingrese una fecha válida'
        );

        // Mensaje al seleccionar celda
        $validation->setPromptTitle(
            $validationConfig['ayudaCeldaTitulo'] ?? 'Formato requerido'
        );

        $validation->setPrompt(
            $validationConfig['ayudaCeldaTexto'] ?? 'Formato: dd/mm/yyyy'
        );

        $validation->setShowInputMessage(true);

        // 👉 FORMATO VISUAL EN EXCEL
        $sheet->getStyle($cell)
            ->getNumberFormat()
            ->setFormatCode($validationConfig['formatoEnExcel'] ?? 'dd/mm/yyyy');
    }

    /**
     * Convierte dd/mm/yyyy → yyyy,mm,dd para DATE()
     */
    private function formatDateForExcel(string $date): string
    {
        [$day, $month, $year] = explode('/', $date);

        return "{$year},{$month},{$day}";
    }
}