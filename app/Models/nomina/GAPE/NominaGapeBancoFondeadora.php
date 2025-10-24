<?php

namespace App\Models\nomina\GAPE;

use Illuminate\Database\Eloquent\Model;

class NominaGapeBancoFondeadora extends Model
{
    //
    //
    protected $primaryKey = 'id';
    protected $table = 'nomina_gape_banco_fondeadora';

    protected $fillable = [
        'id_nomina_gape_empresa',
        'activo_dispersion',
    ];

    public $timestamps = true;
}
