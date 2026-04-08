<?php

namespace App\Http\Services\Nomina\Export\Empleados;

class ConfigFormatoEmpleadosService
{
    /**Retorna la configuración dinámica dependiendo si es fiscal o no fiscal.
     * */
     
    public static function getConfig(bool $fiscal): array
    {
        return [
            true => [ // Formato Fiscal (Mixto)
                'path'       => 'plantillas/formato_carga_empleados_mixto.xlsx',
                'sheet_name' => 'Empleados',
                'query'      => '',
                'col_inicio' => 'A2',
                'freeze_cell' => 'A2',
                'fila_insert' => 2,
                'group_rows' => [],
                'auto_cols'  => [],
            ],

            false => [ // Formato Excedente (No Fiscal)
                'path'       => 'plantillas/formato_carga_empleados_excedente.xlsx',
                'sheet_name' => 'Empleados',
                'query'      => '',
                'col_inicio' => 'A2',
                'freeze_cell' => 'A2',
                'fila_insert' => 2,
                'group_rows' => [],
                'auto_cols'  => [],
            ],
        ][$fiscal];
    }

    /*
    const TIPOS = [
        'texto',
        'numero',
        'decimal',
        'fecha',
        'list',
        'boolean',
    ];
    */

    public static function getCellsConfig(): array
    {
        return [
            [
                'key'                           => 'codigo',
                'titulo'                        => 'Código',
                'columna'                       => 'A',
                'filaInicialTitulos'            => 1,
                'filaInicialInformacion'        => 2,
                'tipoDeColumna'                 => 'texto',
                'esRequerido'                   => true,
                'validacion'                    => [
                    'formato'                       => '@',
                    'ayudaCeldaTitulo'              => 'Ingresa el código a partir del último generado',
                    'ayudaCeldaTexto'               => 'Ingresa el código a partir del último generado',
                    'comentarioTexto'               => 'Ingresa el código a partir del último generado',
                ],
                'fuente'                        => [
                    'tipo'                          => 'catalogo',
                    'origen'                        => 'departamentos',
                ],
                'mapeoBD'                       => 'id_departamento',
            ],
            [
                'key'                           => 'codigo',
                'titulo'                        => 'Código',
                'columna'                       => 'A',
                'filaInicialTitulos'            => 1,
                'filaInicialInformacion'        => 2,
                'tipoDeColumna'                 => 'texto',
                'esRequerido'                   => true,
                'validacion'                    => [
                    'formato'                       => '@',
                    'ayudaCeldaTitulo'              => 'Ingresa el código a partir del último generado',
                    'ayudaCeldaTexto'               => 'Ingresa el código a partir del último generado',
                    'comentarioTexto'               => 'Ingresa el código a partir del último generado',
                ],
                'fuente'                        => [
                    'tipo'                          => 'catalogo',
                    'origen'                        => 'departamentos',
                ],
                'mapeoBD'                       => 'id_departamento',
            ],
        ];
    }
}