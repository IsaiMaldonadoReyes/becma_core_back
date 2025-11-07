<?php

namespace App\Models\nomina\GAPE;

use Illuminate\Database\Eloquent\Model;

class NominaGapeEmpresa extends Model
{
    //
    protected $primaryKey = 'id';
    protected $table = 'nomina_gape_empresa';

    protected $fillable = [
        'id_nomina_gape_cliente',
        'id_empresa_database',
        'fiscal',
        'razon_social',
        'rfc',
        'codigo_interno',
        'correo_notificacion',
        'estado',
        'mascara_codigo',
        'codigo_inicial',
        'codigo_actual',
    ];

    public $timestamps = true;

    protected $casts = [
        'estado' => 'boolean',
        'id_nomina_gape_cliente' => 'integer',
        'id_empresa_database' => 'integer',
    ];
}
