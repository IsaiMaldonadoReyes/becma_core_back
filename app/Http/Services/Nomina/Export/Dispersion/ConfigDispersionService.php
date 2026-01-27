<?php

namespace App\Http\Services\Nomina\Export\Dispersion;

class ConfigDispersionService
{
    /**
     * Retorna la configuración dinámica dependiendo si es fiscal o no fiscal.
     */
    public static function getConfig(): array
    {
        return [
            'SUELDO_IMSS' => [ // Formato Fiscal (Mixto)
                'queries' => [
                    'detalle'       => 'detalle_sueldo_imss',
                ],
            ],
            'ASIMILADOS' => [
                'queries' => [
                    'detalle'       => 'detalle_asimilados',
                ],
            ],
            'SINDICATO' => [
                'queries' => [
                    'detalle'       => 'datosSindicato_1',
                ],
            ],
            'TARJETA_FACIL' => [
                'queries' => [
                    'detalle'       => 'datosTarjetaFacil_1',
                ],
            ],
            'GASTOS_POR_COMPROBAR' => [
                'queries' => [
                    'detalle'       => 'datosGastosPorComprobar_1',
                ],
            ],
        ];
    }
}
