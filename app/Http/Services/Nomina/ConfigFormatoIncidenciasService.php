<?php

namespace App\Http\Services\Nomina;

class ConfigFormatoIncidenciasService
{
    /**
     * Retorna la configuración dinámica dependiendo si es fiscal o no fiscal.
     */
    public static function getConfig(bool $fiscal): array
    {
        return [
            true => [ // Formato Fiscal (Mixto)
                'path'       => 'plantillas/formato_carga_incidencias_mixto.xlsx',
                'sheet_name' => 'incidencias',
                'query'      => 'datosQueryMixto',
                'col_inicio' => 'A10',
                'freeze_cell' => 'C10',
                'fila_insert' => 11,
                'group_rows' => [1, 7],
                'auto_cols'  => ['A', 'L'],
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
