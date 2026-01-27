<?php

namespace App\Http\Services\Nomina\Export\Prenomina;

class ConfigFormatoPrenominaService
{
    /**
     * Retorna la configuración dinámica dependiendo si es fiscal o no fiscal.
     */
    public static function getConfig(): array
    {
        return [
            'SUELDO_IMSS' => [ // Formato Fiscal (Mixto)
                'path'       => 'plantillas/formato_prenomina.xlsx',
                'sheet_name' => 'SUELDO_IMSS',
                'queries' => [
                    'detalle'       => 'datosSueldoImss_1',
                    'ingresos'      => 'datosSueldoImss_2',
                    'percepciones'  => 'datosSueldoImss_3',
                    'excedente'     => 'datosSueldoImss_4',
                    'provisiones'   => 'datosSueldoImss_5',
                    'carga_social'  => 'datosSueldoImss_6',
                    'totales_01'       => 'totalSueldoImss_01',
                    'totales_02'       => 'totalSueldoImss_02',
                    'totales_03'       => 'totalSueldoImss_03',
                    'totales_04'       => 'totalSueldoImss_04',
                    'totales_05'       => 'totalSueldoImss_05',
                ],
                'titulos' => [
                    'ingresos'      => 'NÓMINA EN BASE A INGRESOS REALES',
                    'percepciones'  => 'PERCEPCIONES',
                    'excedente'     => 'EXCEDENTE',
                    'provisiones'   => 'PROVISIONES',
                    'carga_social'  => 'CARGA SOCIAL',
                ],
                'fila_encabezado' => 11,
                'columna_inicio' => 'Q',
                'fila_inicio_datos' => 13,
                'col_inicio' => 'A12',
                'freeze_cell' => 'C12',
            ],
            'ASIMILADOS' => [
                'path'       => 'plantillas/formato_prenomina.xlsx',
                'sheet_name' => 'ASIMILADOS',
                'queries' => [
                    'detalle'       => 'datosAsimilados_1',
                    'ingresos'      => 'datosAsimilados_2',
                    'percepciones'  => 'datosAsimilados_3',
                    'excedente'     => 'datosAsimilados_4',
                    'provisiones'   => 'datosAsimilados_5',
                    'carga_social'  => 'datosAsimilados_6',
                    //'totales'       => 'datosTotalesAsimilados_7',
                    'totales_01'       => 'totalAsimilados_01',
                    'totales_02'       => 'totalAsimilados_02',
                    'totales_03'       => 'totalAsimilados_03',
                    'totales_04'       => 'totalAsimilados_04',
                    'totales_05'       => 'totalAsimilados_05',
                ],
                'titulos' => [
                    'ingresos'      => 'NÓMINA EN BASE A INGRESOS REALES',
                    'percepciones'  => 'PERCEPCIONES',
                    'excedente'     => 'EXCEDENTE',
                    'provisiones'   => 'PROVISIONES',
                    'carga_social'  => 'CARGA SOCIAL',
                ],
                'fila_encabezado' => 11,
                'columna_inicio' => 'Q',
                'fila_inicio_datos' => 13,
                'col_inicio' => 'A12',
                'freeze_cell' => 'C12',
            ],
            'SINDICATO' => [
                'path'       => 'plantillas/formato_prenomina.xlsx',
                'sheet_name' => 'SINDICATO',
                'queries' => [
                    'detalle'       => 'datosSindicato_1',
                    'ingresos'      => 'datosSindicato_2',
                    'excedente'     => 'datosSindicato_4',
                    'totales_01'    => 'totalSindicato_01',
                ],
                'titulos' => [
                    'ingresos'      => 'NÓMINA EN BASE A INGRESOS REALES',
                    'excedente'     => 'ESQUEMAS DE PAGO',
                ],
                'fila_encabezado' => 11,
                'columna_inicio' => 'Q',
                'fila_inicio_datos' => 13,
                'col_inicio' => 'A12',
                'freeze_cell' => 'C12',
            ],
            'TARJETA_FACIL' => [
                'path'       => 'plantillas/formato_prenomina.xlsx',
                'sheet_name' => 'TARJETA_FACIL',
                'queries' => [
                    'detalle'       => 'datosTarjetaFacil_1',
                    'ingresos'      => 'datosTarjetaFacil_2',
                    'excedente'     => 'datosTarjetaFacil_4',
                    'totales_01'    => 'totalTarjetaFacil_01',
                ],
                'titulos' => [
                    'ingresos'      => 'NÓMINA EN BASE A INGRESOS REALES',
                    'excedente'     => 'ESQUEMAS DE PAGO',
                ],
                'fila_encabezado' => 11,
                'columna_inicio' => 'Q',
                'fila_inicio_datos' => 13,
                'col_inicio' => 'A12',
                'freeze_cell' => 'C12',
            ],
            'GASTOS_POR_COMPROBAR' => [
                'path'       => 'plantillas/formato_prenomina.xlsx',
                'sheet_name' => 'GASTOS_POR_COMPROBAR',
                'queries' => [
                    'detalle'       => 'datosGastosPorComprobar_1',
                    'ingresos'      => 'datosGastosPorComprobar_2',
                    'excedente'     => 'datosGastosPorComprobar_4',
                    'totales_01'    => 'totalGastosPorComprobar_01',
                ],
                'titulos' => [
                    'ingresos'      => 'NÓMINA EN BASE A INGRESOS REALES',
                    'excedente'     => 'ESQUEMAS DE PAGO',
                ],
                'fila_encabezado' => 11,
                'columna_inicio' => 'Q',
                'fila_inicio_datos' => 13,
                'col_inicio' => 'A12',
                'freeze_cell' => 'C12',
            ],
        ];
    }
}
