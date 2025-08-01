<?php

namespace App\Models\comercial;

use Illuminate\Database\Eloquent\Model;

class PresupuestoPeriodo extends Model
{
    //
    protected $table = "presupuesto_periodo";

    protected $fillable = [
        'id_empresa',
        'id_agente',
        'id_ejercicio',
        'enero',
        'febrero',
        'marzo',
        'abril',
        'mayo',
        'junio',
        'julio',
        'agosto',
        'septiembre',
        'octubre',
        'noviembre',
        'diciembre',
        'id_marca',
        'marca'
    ];
}
