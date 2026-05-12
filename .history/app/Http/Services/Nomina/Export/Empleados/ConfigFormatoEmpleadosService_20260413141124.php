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
                'key'                           => 'fechaReingreso',
                'titulo'                        => 'Fecha de reingreso',
                'columna'                       => 'D',
                'filaInicialTitulos'            => 1,
                'filaInicialInformacion'        => 2,
                'tipoDeColumna'                 => 'fecha',
                'validacion'                    => [
                    'esRequerido'                   => true,
                    'valorMinimoRequerido'          => '01/01/2000',
                    'valorMaximoRequerido'          => '01/04/2026',
                    'formatoEnExcel'                => 'dd/mm/yyyy',
                    'ayudaCeldaTitulo'              => 'Fecha de reingreso',
                    'ayudaCeldaTexto'               => 'Ingrese una fecha válida usando el formato: dd/mm/yyyy.',
                    'comentarioTexto'               => 'Fecha en que el empleado se reincorporó a la empresa',
                ],
                'mapeoOrigenBD'                       => [
                    'mapeoTabla'                        => '',
                    'mapeoCampo'                        => '',
                ],
                'mapeoDestinoBD'                      => [
                    'mapeoTabla'                        => 'adEmpleado',
                    'mapeoCampo'                        => 'fecha_reingreso',
                ],
            ],
            [
                'key'                           => 'tipoContrato',
                'titulo'                        => 'Tipo de contrato',
                'columna'                       => 'E',
                'filaInicialTitulos'            => 1,
                'filaInicialInformacion'        => 2,
                'tipoDeColumna'                 => 'combo',
                'validacion'                    => [
                    'esRequerido'                   => true,
                    'valorMinimoRequerido'          => '',
                    'valorMaximoRequerido'          => '',
                    'formatoEnExcel'                => '',
                    'ayudaCeldaTitulo'              => 'Formato requerido',
                    'ayudaCeldaTexto'               => 'Seleccione un tipo de contrato válido de la lista desplegable',
                    'comentarioTexto'               => 'Seleccione un tipo de contrato',
                ],
                'mapeoOrigenBD'                       => [
                    'mapeoTabla'                        => '',
                    'mapeoCampo'                        => '',
                ],
                'mapeoDestinoBD'                      => [
                    'mapeoTabla'                        => 'adEmpleado',
                    'mapeoCampo'                        => 'tipoContrato',
                ],
            ],
            
        ];
    }
}