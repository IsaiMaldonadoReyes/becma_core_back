<?php

namespace App\Models\nomina\GAPE;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class NominaGapeConfiguracionGlobal extends Model
{
    //
    use HasFactory;
    protected $primaryKey = 'id';
    protected $table = 'nomina_gape_configuracion_global';

    protected $fillable = [
        'codigo_empleado_actual',
        'folio_dispersion_actual',
    ];

    protected $casts = [
        'id' => 'integer',
        'codigo_empleado_actual' => 'string',
        'folio_dispersion_actual' => 'double',
    ];
}
