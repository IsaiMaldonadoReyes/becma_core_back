<?php

namespace App\Models\nomina\GAPE;

use Illuminate\Database\Eloquent\Model;

class NominaGapeBancoDispersion extends Model
{
    //
    protected $primaryKey = 'id';
    protected $table = 'nomina_gape_banco_dispersion';

    protected $fillable = [
        'id_nomina_gape_empresa',
        'fondeadora',
        'azteca_interbancario',
        'azteca_bancario',
        'banorte',
    ];
}
