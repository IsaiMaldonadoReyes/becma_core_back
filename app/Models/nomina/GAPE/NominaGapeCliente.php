<?php

namespace App\Models\nomina\GAPE;

use Illuminate\Database\Eloquent\Model;

class NominaGapeCliente extends Model
{
    //
    protected $primaryKey = 'id';
    protected $table = 'nomina_gape_cliente';

    protected $fillable = [
        'nombre',
        'codigo',
        'telefono',
        'estado'
    ];

    public $timestamps = true;
}
