<?php

namespace App\Http\Services\Nomina\Export\Empleados;

class ConfigFormatoEmpleadosService
{
    /**
     * Retorna la configuración dinámica dependiendo si es fiscal o no fiscal.
     */
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

    public static function getConfig(bool $fiscal): array
    {
        return [

            [
                'key' => 'departamento',
                'titulo' => 'Departamento',
                'columna' => 'C',
                'fila_titulo' => 1,
                'fila_inicio' => 2,

                // UI / UX
                'ancho' => 25,
                'comentario' => 'Seleccione un departamento válido',

                // Validación
                'tipo' => 'combo',
                'requerido' => true,
                'validacion' => [
                    'tipo' => 'lista',
                    'mensaje' => 'Seleccione un departamento válido',
                ],

                // Datos dinámicos
                'fuente' => [
                    'tipo' => 'catalogo',
                    'origen' => 'departamentos',
                ],

                // Backend mapping
                'map_to' => 'id_departamento',
            ],

            // 👉 puedes seguir agregando más columnas aquí
        ];
    }
}