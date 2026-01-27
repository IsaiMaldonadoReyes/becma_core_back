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

    protected $casts = [];
}
