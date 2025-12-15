<?php

namespace App\Http\Services\Nomina\Export\Prenomina;

class ConfigFormatoPrenominaService
{
    /**
     * Retorna la configuración dinámica dependiendo si es fiscal o no fiscal.
     */
    public static function getConfig(bool $fiscal): array
    {
        return [
            true => [ // Formato Fiscal (Mixto)
                'path'       => 'plantillas/formato_prenomina.xlsx',
                'sheet_name' => 'prenomina',
                'fila_encabezado' => 11,
                'columna_inicio' => 'Q',
                'fila_inicio_datos' => 13,
                'col_inicio' => 'A12',
                'freeze_cell' => 'C12',
            ],

            false => [ // Formato Excedente (No Fiscal)
                'path'       => 'plantillas/formato_carga_incidencias_excedente.xlsx',
                'sheet_name' => 'incidencias',
                'query'      => 'datosQueryExcedente',
                'col_inicio' => 'C10',
                'fila_insert' => 11,
                'group_rows' => [1, 7],
                'auto_cols'  => ['A', 'T'],
            ],
        ][$fiscal];
    }
}
