<?php

namespace App\Http\Services\Excel\Builders;

use App\Http\Services\Excel\Factories\ColumnStrategyFactory;

class ExcelLayoutBuilder
{
    public function apply($sheet, array $cellsConfig)
    {
        foreach ($cellsConfig as $column) {

            $strategy = ColumnStrategyFactory::make($column['tipoDeColumna']);

            $col = $column['columna'];
            $rowStart = $column['filaInicialInformacion'];

            for ($row = $rowStart; $row <= 1000; $row++) {
                $cell = $col . $row;

                $strategy->apply($sheet, $cell, $column);
            }

            // 👉 Título
            $sheet->setCellValue(
                $column['columna'] . $column['filaInicialTitulos'],
                $column['titulo']
            );

            $headerCell = $column['columna'] . $column['filaInicialTitulos'];

            // 👉 COMENTARIO (solo encabezado)
            if (!empty($column['validacion']['comentarioTexto'])) {

                $comment = $sheet->getComment($headerCell);
                $comment->setAuthor('Sistema');

                $richText = $comment->getText();
                $richText->createTextRun(
                    $this->formatCommentText($column['validacion']['comentarioTexto'])
                );

                $comment->setWidth('200pt');
                $comment->setHeight('100pt');
            }
        }
    }

    private function formatCommentText($text)
    {
        return str_replace('\n', "\n", $text);
    }
}
