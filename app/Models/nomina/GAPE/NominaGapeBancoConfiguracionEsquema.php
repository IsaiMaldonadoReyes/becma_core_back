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

    protected $casts = [
        'id' => 'integer',
        'activo_dispersion' => 'boolean',
    ];

    public function banco()
    {
        return $this->belongsTo(
            NominaGapeBanco::class,
            'id_nomina_gape_banco'
        );
    }

    public function esquema()
    {
        return $this->belongsTo(
            NominaGapeEsquema::class,
            'id_nomina_gape_esquema'
        );
    }
}
