<?php

namespace App\Http\Services\Nomina\Import\Incidencias;

class IncidenciasValidationBag
{
    public array $errors = [];
    public array $data = [];
    public bool $skipRow = false;

    public function add(string $message, int $row, string $column)
    {
        $this->errors[] = [
            'fila' => $row,
            'columna' => $column,
            'mensaje' => $message
        ];
    }

    public function markSkipRow(): void
    {
        $this->skipRow = true;
    }

    public function shouldSkipRow(): bool
    {
        return $this->skipRow;
    }

    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    public function get(): array
    {
        return $this->errors;
    }

    public function set(string $key, $value)
    {
        $this->data[$key] = $value;
    }

    public function getData(): array
    {
        return $this->data;
    }
}
