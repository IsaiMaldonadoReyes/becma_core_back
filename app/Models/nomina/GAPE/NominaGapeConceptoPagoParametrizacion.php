<?php

namespace App\Models\nomina\GAPE;

use Illuminate\Database\Eloquent\Model;

class NominaGapeConceptoPagoParametrizacion extends Model
{
    //
    protected $primaryKey = 'id';
    protected $table = 'nomina_gape_concepto_pago_parametrizacion';

    protected $fillable = [
        'id_nomina_gape_cliente',
        'id_nomina_gape_empresa',
        'id_tipo_periodo',
        'tipo_periodo_nombre',
        'sueldo_imss',
        'sueldo_imss_tope',
        'sueldo_imss_orden',

        'prev_social',
        'prev_social_tope',
        'prev_social_orden',

        'fondos_sind',
        'fondos_sind_tope',
        'fondos_sind_orden',

        'tarjeta_facil',
        'tarjeta_facil_tope',
        'tarjeta_facil_orden',

        'hon_asimilados',
        'hon_asimilados_tope',
        'hon_asimilados_orden',

        'gastos_compro',
        'gastos_compro_tope',
        'gastos_compro_orden',

        'estado',
    ];

    public $timestamps = true;

    protected $casts = [
        'estado' => 'boolean',
        'id_nomina_gape_cliente' => 'integer',
        'id_nomina_gape_empresa' => 'integer',
        'id_tipo_periodo' => 'integer',
    ];
}
