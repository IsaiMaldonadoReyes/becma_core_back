<?php 

namespace App\Http\Services\Excel\Strategies\Export;

use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use App\Http\Services\Excel\Strategies\Contracts\ColumnStrategyInterface;

class ComboColumnStrategy implements ColumnStrategyInterface
{
    public function apply($sheet, $cell, $column)
    {
        $validationConfig = $column['validacion'] ?? [];
        $options = $column['options'] ?? [];

        if (empty($options)) {
            return;
        }

        $validation = $sheet->getCell($cell)->getDataValidation();

        $validation->setType(DataValidation::TYPE_LIST);
        $validation->setErrorStyle(DataValidation::STYLE_STOP);
        $validation->setAllowBlank(!($validationConfig['esRequerido'] ?? false));
        $validation->setShowDropDown(true);
        $validation->setShowInputMessage(true);
        $validation->setShowErrorMessage(true);

        $validation->setErrorTitle('Valor inválido');
        $validation->setError('Seleccione una opción válida de la lista');

        $validation->setPromptTitle($validationConfig['ayudaCeldaTitulo'] ?? 'Seleccione una opción');
        $validation->setPrompt($validationConfig['ayudaCeldaTexto'] ?? 'Seleccione un valor válido de la lista');

        $validation->setFormula1('"' . implode(',', $options) . '"');
    }
}