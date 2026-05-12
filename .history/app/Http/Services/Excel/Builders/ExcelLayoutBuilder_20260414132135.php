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
                $texto = $column['validacion']['comentarioTexto'] ?? '';

                $this->buildRichTextFromString($richText, $texto);

                $comment->setWidth('200pt');
                $comment->setHeight('100pt');
            }
        }
    }

    private function autoSizeComment($comment, string $text)
    {
        // Normalizar saltos
        $text = str_replace('\n', "\n", $text);

        $lines = explode("\n", $text);

        $maxLength = 0;

        foreach ($lines as $line) {
            $length = mb_strlen($line);
            if ($length > $maxLength) {
                $maxLength = $length;
            }
        }

        $lineCount = count($lines);

        // Ajustes empíricos (puedes afinarlos)
        $width = max(120, $maxLength * 6);   // ancho dinámico
        $height = max(60, $lineCount * 15);  // alto dinámico

        $comment->setWidth($width . 'pt');
        $comment->setHeight($height . 'pt');
    }

    private function formatCommentText($text)
    {
        return str_replace('\n', "\n", $text);
    }

    private function buildRichTextFromString($richText, string $text)
    {
        // Normalizar saltos
        $text = str_replace('\n', "\n", $text);

        $lines = explode("\n", $text);

        foreach ($lines as $lineIndex => $line) {

            // Separar por bloques de ** **
            $parts = preg_split('/(\*\*.*?\*\*)/', $line, -1, PREG_SPLIT_DELIM_CAPTURE);

            foreach ($parts as $part) {

                if ($part === '') continue;

                $isBold = str_starts_with($part, '**') && str_ends_with($part, '**');

                $cleanText = $isBold
                    ? substr($part, 2, -2) // quitar **
                    : $part;

                $run = $richText->createTextRun($cleanText);

                if ($isBold) {
                    $run->getFont()->setBold(true);
                }
            }

            // Agregar salto de línea (excepto última línea)
            if ($lineIndex < count($lines) - 1) {
                $richText->createTextRun("\n");
            }
        }
    }
}
