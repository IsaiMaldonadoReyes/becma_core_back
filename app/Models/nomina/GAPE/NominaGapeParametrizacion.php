<?php

namespace App\Models\nomina\GAPE;

use Illuminate\Database\Eloquent\Model;

class NominaGapeParametrizacion extends Model
{
    //
    protected $primaryKey = 'id';
    protected $table = 'nomina_gape_parametrizacion';

    protected $fillable = [
        'estado',
        'id_nomina_gape_cliente',
        'id_nomina_gape_empresa',
        'id_tipo_periodo',
        'tipo_periodo_nombre',
        'clase_prima_riesgo',
        'clase_prima_riesgo_valor',
        'fee',

        'base_fee',
        'provisiones',
        'isn',

        'cuota_sindical',
    ];

    public $timestamps = true;

    protected $casts = [
        'estado' => 'boolean',
        'id_nomina_gape_cliente' => 'integer',
        'id_nomina_gape_empresa' => 'integer',

    ];
}
