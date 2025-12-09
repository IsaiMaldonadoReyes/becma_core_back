<?php

namespace App\Http\Services\Nomina\Import\Incidencias;

class IncidenciasValidationBag
{
    protected array $errors = [];

    public function add(string $message, int $row, string $column)
    {
        $this->errors[] = [
            'fila' => $row,
            'columna' => $column,
            'mensaje' => $message
        ];
    }

    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    public function get(): array
    {
        return $this->errors;
    }
}
