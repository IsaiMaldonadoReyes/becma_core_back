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
                'validacion'                    => [
                    'esRequerido'                   => true,
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
                'validacion'                    => [
                    'esRequerido'                   => true,
                    'valorMinimoRequerido'          => '6',
                    'valorMaximoRequerido'          => '6',
                    'formatoEnExcel'                => '@',
                    'ayudaCeldaTitulo'              => 'Formato requerido',
                    'ayudaCeldaTexto'               => 'El código debe llevar 6 caracteres',
                    'comentarioTexto'               => 'Ingresa un código valido',
                ],
                'mapeoOrigen'                        => [
                    'mapeoTabla'                          => 'catalogo',
                    'mapeoCampo'                        => 'departamentos',
                ],
                'mapeoDestino'                        => [
                    'mapeoTabla'                          => 'catalogo',
                    'mapeoCampo'                        => 'departamentos',
                ],
            ],
            [
                'key'                           => 'fechaAlta',
                'titulo'                        => 'Fecha de alta',
                'columna'                       => 'B',
                'filaInicialTitulos'            => 1,
                'filaInicialInformacion'        => 2,
                'tipoDeColumna'                 => 'fecha',
                'validacion'                    => [
                    'esRequerido'                   => true,
                    'valorMinimoRequerido'          => '01/01/2000',
                    'valorMaximoRequerido'          => '01/04/2026',
                    'formatoEnExcel'                => 'dd/mm/yyyy',
                    'ayudaCeldaTitulo'              => 'Formato requerido',
                    'ayudaCeldaTexto'               => 'Ingrese una fecha válida en formato dd/mm/yyyy',
                    'comentarioTexto'               => 'Ingresa la fecha de alta valida',
                ],
                'fuente'                        => [
                    'tipo'                          => '',
                    'origen'                        => 'departamentos',
                ],
                'mapeoBD'                       => 'fecha_alta',
            ],
        ];
    }
}