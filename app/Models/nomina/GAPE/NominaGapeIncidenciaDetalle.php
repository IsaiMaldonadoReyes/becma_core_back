<?php

namespace App\Models\nomina\GAPE;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class NominaGapeIncidenciaDetalle extends Model
{
    //
    use HasFactory;

    protected $primaryKey = 'id';
    protected $table = 'nomina_gape_incidencia_detalle';

    protected $fillable = [
        'id_nomina_gape_incidencia',
        'id_empleado',
        'cantidad_faltas',
        'cantidad_incapacidad',
        'cantidad_vacaciones',
        'cantidad_prima_dominical',
        'cantidad_dias_retroactivos',
        'cantidad_dias_festivos',
        'comision',
        'bono',
        'horas_extra_doble_cantidad',
        'horas_extra_doble',
        'horas_extra_triple_cantidad',
        'horas_extra_triple',
        'pago_adicional',
        'premio_puntualidad',
        'codigo_empleado'
    ];

    public $timestamps = true;
}
