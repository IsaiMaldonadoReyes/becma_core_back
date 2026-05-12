<?php 

namespace App\Http\Services\Excel\Strategies\Export;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use App\Http\Services\Excel\Strategies\Contracts\ColumnStrategyInterface;

class ComboColumnStrategy implements ColumnStrategyInterface
{
    public function apply($sheet, $cell, $column)
    {
        $validation = $sheet->getCell($cell)->getDataValidation();

        $validation->setType(DataValidation::TYPE_LIST);
        $validation->setAllowBlank(!$column['validacion']['esRequerido']);
        $validation->setShowDropDown(true);

        // 👉 Aquí ya usas config dinámico
        $options = $column['options'] ?? [];

        $validation->setFormula1('"' . implode(',', $options) . '"');

        $validation->setErrorTitle('Valor inválido');
        $validation->setError('Seleccione una opción válida');

        // Prompt (ayuda)
        $validation->setPromptTitle($column['validacion']['ayudaCeldaTitulo'] ?? '');
        $validation->setPrompt($column['validacion']['ayudaCeldaTexto'] ?? '');
        $validation->setShowInputMessage(true);
    }
}