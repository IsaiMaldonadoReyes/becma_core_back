<?php

namespace App\Models\nomina\GAPE;

use Illuminate\Database\Eloquent\Model;

class NominaGapeCombinacionPrevision extends Model
{
    //
    protected $primaryKey = 'id';
    protected $table = 'nomina_gape_combinacion_prevision';

    protected $fillable = [
        'nomina_gape_empresa_periodo_combinacion_parametrizacion',
        'id_concepto',
    ];

    public $timestamps = true;

    protected $casts = [
    ];
}
