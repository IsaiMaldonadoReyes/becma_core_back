<?php

namespace App\Models\nomina\GAPE;

use Illuminate\Database\Eloquent\Model;

class NominaGapeBancoBanorte extends Model
{
    //
    protected $primaryKey = 'id';
    protected $table = 'nomina_gape_banco_banorte';

    protected $fillable = [
        'id_nomina_gape_empresa',
        'activo_dispersion',
        'cuenta_origen',
        'clave_banco',
    ];
}
