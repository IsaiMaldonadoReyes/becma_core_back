<?php

namespace App\Models\nomina\GAPE;

use Illuminate\Database\Eloquent\Model;

class NominaGapeBancoAzteca extends Model
{
    //
    protected $primaryKey = 'id';
    protected $table = 'nomina_gape_banco_azteca';

    protected $fillable = [
        'id_nomina_gape_empresa',
        'activo_dispersion',
        'cuenta_origen',
        'tipo_banco',
    ];

    public $timestamps = true;
}
