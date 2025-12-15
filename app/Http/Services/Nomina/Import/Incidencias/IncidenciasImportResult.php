<?php

namespace App\Http\Services\Nomina\Import\Incidencias;


class IncidenciasImportResult
{
    public $sheet;
    public $filasValidas;
    public $errores;

    public function __construct($sheet, array $filasValidas, array $errores)
    {
        $this->sheet       = $sheet;
        $this->filasValidas = $filasValidas;
        $this->errores      = $errores;
    }
}
