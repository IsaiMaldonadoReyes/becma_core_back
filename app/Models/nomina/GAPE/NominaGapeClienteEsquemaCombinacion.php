<?php

namespace App\Models\nomina\GAPE;

use Illuminate\Database\Eloquent\Model;

class NominaGapeClienteEsquemaCombinacion extends Model
{
    //
    protected $primaryKey = 'id';
    protected $table = 'nomina_gape_cliente_esquema_combinacion';

    protected $fillable = [
        'estado',
        'id_nomina_gape_cliente',
        'id_nomina_gape_esquema',
        'combinacion',
        'tope',
        'orden',
        'id_nomina_gape_empresa',
    ];

    public $timestamps = true;

    protected $casts = [
        'estado' => 'boolean',
    ];
}
