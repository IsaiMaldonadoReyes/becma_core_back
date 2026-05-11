<?php

namespace App\Models\nomina\GAPE;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class NominaGapeDispersionHistorial extends Model
{
    //
    use HasFactory;

    protected $primaryKey = 'id';
    protected $table = 'nomina_gape_dispersion_historial';

    protected $fillable = [
        'usuario_creador',
        'usuario_modificador',
        'id_nomina_gape_cliente',
        'id_nomina_gape_empresa',
        'id_nomina_gape_banco_configuracion',
        'ejercicio',
        'periodo',
        'folio_dispersion',
        'monto_nomina',
        'total_empleados',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_nomina_gape_banco_configuracion' => 'integer',
        'id_nomina_gape_cliente' => 'integer',
        'id_nomina_gape_empresa' => 'integer',
        'ejercicio' => 'string',
        'periodo' => 'string',
        'folio_dispersion' => 'string',
        'monto_nomina' => 'double',
        'total_empleados' => 'double',
    ];
}
