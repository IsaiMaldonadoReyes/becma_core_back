<?php

namespace App\Models\nomina\GAPE;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class NominaGapeBancoConfiguracionEsquema extends Model
{
    //
    use HasFactory;

    protected $primaryKey = 'id';
    protected $table = 'nomina_gape_banco_configuracion_esquema';

    protected $fillable = [
        'estado',
        'id_nomina_gape_banco',
        'id_nomina_gape_cliente',
        'id_nomina_gape_empresa',
        'id_nomina_gape_esquema',
        'activo_dispersion',
        'azteca_cuenta_origen',
        'banorte_cuenta_origen',
    ];

    protected $casts = [
        'id' => 'integer',
        'activo_dispersion' => 'boolean',
    ];
}
