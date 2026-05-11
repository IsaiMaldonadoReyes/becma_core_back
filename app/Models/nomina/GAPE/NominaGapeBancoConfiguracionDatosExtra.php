<?php

namespace App\Models\nomina\GAPE;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class NominaGapeBancoConfiguracionDatosExtra extends Model
{
    //
    use HasFactory;

    protected $primaryKey = 'id';
    protected $table = 'nomina_gape_banco_configuracion_datos_extra';

    protected $fillable = [
        'usuario_creador',
        'usuario_modificador',
        'id_nomina_gape_banco_configuracion',
        'cuenta',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_nomina_gape_banco_configuracion' => 'integer',
    ];
}
