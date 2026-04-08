<?php

namespace App\Models\nomina\GAPE;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class NominaGapeEmpresaPeriodoCombinacionParametrizacion extends Model
{
    //
    use HasFactory;

    protected $primaryKey = 'id';
    protected $table = 'nomina_gape_empresa_periodo_combinacion_parametrizacion';

    protected $fillable = [
        'estado',
        'id_nomina_gape_cliente',
        'id_nomina_gape_empresa',
        'id_nomina_gape_tipo_periodo',
        'idtipoperiodo',
        'id_nomina_gape_cliente_esquema_combinacion',
        'fee',
        'base_fee',
        'provisiones',
    ];

    public $timestamps = true;


    protected $casts = [
        'estado' => 'boolean',
    ];
}
