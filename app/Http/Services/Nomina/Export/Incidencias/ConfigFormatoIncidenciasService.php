<?php

namespace App\Http\Services\Nomina\Export\Incidencias;

class ConfigFormatoIncidenciasService
{
    /**
     * Retorna la configuración dinámica dependiendo si es fiscal o no fiscal.
     */
    public static function getConfig(): array
    {
        return [
            'SUELDO_IMSS' => [ // Formato Fiscal (Mixto)
                'path'       => 'plantillas/formato_carga_incidencias_general.xlsx',
                'sheet_name' => 'SUELDO_IMSS',
                'query'      => 'datosQuerySueldoImss',
                'col_inicio' => 'A10',
                'freeze_cell' => 'C10',
                'fila_insert' => 11,
                'group_rows' => [1, 7],
                'auto_cols'  => ['A', 'L'],
            ],

            'ASIMILADOS' => [ // Formato Excedente (No Fiscal)
                'path'       => 'plantillas/formato_carga_incidencias_general.xlsx',
                'sheet_name' => 'ASIMILADOS',
                'query'      => 'datosQueryAsimilados',
                'col_inicio' => 'A10',
                'freeze_cell' => 'C10',
                'fila_insert' => 11,
                'group_rows' => [1, 7],
                'auto_cols'  => ['A', 'L'],
            ],

            'SINDICATO' => [ // Formato Excedente (No Fiscal)
                'path'       => 'plantillas/formato_carga_incidencias_general.xlsx',
                'sheet_name' => 'SINDICATO',
                'query'      => 'datosQuerySindicato',
                'col_inicio' => 'A10',
                'freeze_cell' => 'C10',
                'fila_insert' => 11,
                'group_rows' => [1, 7],
                'auto_cols'  => ['A', 'L'],
            ],

            'TARJETA_FACIL' => [ // Formato Excedente (No Fiscal)
                'path'       => 'plantillas/formato_carga_incidencias_general.xlsx',
                'sheet_name' => 'TARJETA_FACIL',
                'query'      => 'datosQueryTarjetaFacil',
                'col_inicio' => 'A10',
                'freeze_cell' => 'C10',
                'fila_insert' => 11,
                'group_rows' => [1, 7],
                'auto_cols'  => ['A', 'L'],
            ],

            'GASTOS_POR_COMPROBAR' => [ // Formato Excedente (No Fiscal)
                'path'       => 'plantillas/formato_carga_incidencias_general.xlsx',
                'sheet_name' => 'GASTOS_POR_COMPROBAR',
                'query'      => 'datosQueryGastosComprobar',
                'col_inicio' => 'A10',
                'freeze_cell' => 'C10',
                'fila_insert' => 11,
                'group_rows' => [1, 7],
                'auto_cols'  => ['A', 'L'],
            ],
        ];
    }
}
