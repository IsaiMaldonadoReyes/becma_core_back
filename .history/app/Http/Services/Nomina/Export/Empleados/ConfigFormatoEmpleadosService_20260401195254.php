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
                'fila_inicial_encabezado'       => 1,
                'fila_inicial_informacion'      => 2,
                'comentario'                    => 'Ingresa el código a partir del último generado',
                'tipoColumna'                   => 'texto',
                'esRequerido'                   => true,
                'validacion'                    => [
                    'formato'                   => '@',
                    'ayudaCeldaTitulo'          => 'Ingresa el código a partir del último generado',
                    'ayudaCeldaTexto'           => 'Ingresa el código a partir del último generado',
                    'comentarioTexto'           => 'Ingresa el código a partir del último generado',
                ],
                'fuente'                        => [
                    'tipo'                          => 'catalogo',
                    'origen'                        => 'departamentos',
                ],
                'mapeoBD'                       => 'id_departamento',
            ],
            [
                'key'           => 'fecha_alta',
                'titulo'        => 'Fecha de alta',
                'columna'       => 'B',
                'fila_titulo'   => 1,
                'fila_inicial_informacion'   => 2,
                'comentario'    => 'Ingresa el código a partir del último generado',
                'tipoColumna'   => 'texto',
                'requerido'     => true,
                'validacion'    => [
                    'formato'           => '@',
                    'tituloCelda'       => 'Ingresa el código a partir del último generado',
                    'mensajeCelda'      => 'Ingresa el código a partir del último generado',
                    'mensajeComentario' => 'Ingresa el código a partir del último generado',
                ],
                'fuente'        => [
                    'tipo'              => 'catalogo',
                    'origen'            => 'departamentos',
                ],
                'map_to'        => 'id_departamento',
            ],
        ];
    }
}