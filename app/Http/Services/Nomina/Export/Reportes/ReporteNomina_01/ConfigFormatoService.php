<?php

namespace App\Http\Services\Nomina\Export\Reportes\ReporteNomina_01;

class ConfigFormatoService
{
    /**
     * Retorna la configuración dinámica dependiendo si es fiscal o no fiscal.
     */
    public static function getConfig(): array
    {
        return [
            'reporte' => [ // Formato Fiscal (Mixto)
                'path'       => 'plantillas/reportes/reporte_nomina_plantilla.xlsx',
                'sheet_name' => 'nomina',
            ],
        ];
    }
}
