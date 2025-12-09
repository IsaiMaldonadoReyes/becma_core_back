<?php

namespace App\Http\Services\Nomina\Import\Incidencias;

class IncidenciasImportResult
{
    public array $errores = [];
    public array $filasValidas = [];

    public function addErrors(array $errors)
    {
        $this->errores = array_merge($this->errores, $errors);
    }

    public function addValidRow(int $row)
    {
        $this->filasValidas[] = $row;
    }
}
