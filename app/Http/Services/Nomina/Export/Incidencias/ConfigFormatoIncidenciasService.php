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
                'query_header'  => 'datosEncabezadoQueryContpaq',
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
                'query_header'  => 'datosEncabezadoQueryContpaq',
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
                'query_header'  => 'datosEncabezadoQueryExcedente',
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
                'query_header'  => 'datosEncabezadoQueryExcedente',
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
                'query_header'  => 'datosEncabezadoQueryExcedente',
                'col_inicio' => 'A10',
                'freeze_cell' => 'C10',
                'fila_insert' => 11,
                'group_rows' => [1, 7],
                'auto_cols'  => ['A', 'L'],
            ],
        ];
    }
}
