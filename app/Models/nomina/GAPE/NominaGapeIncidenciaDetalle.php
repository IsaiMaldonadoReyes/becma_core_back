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
        'cantidad_vacaciones',
        'cantidad_prima_vacacional',

        'cantidad_prima_dominical',
        'cantidad_dias_festivos',
        'comision',
        'bono',

        'horas_extra_doble_cantidad',
        'horas_extra_triple_cantidad',
        'premio_puntualidad',

        'pago_adicional',
        'descuento',

        'descuento_aportacion_caja_ahorro',
        'descuento_prestamo_caja_ahorro',

        'infonavit',
        'fonacot',

        'cantidad_incapacidad',
        'incapacidad_dias',

        'cantidad_dias_retroactivos',

        'horas_extra_doble',
        'horas_extra_triple',

        'anios_prima_vacacional',

        'codigo_empleado',

        'id_nomina_gape_esquema',
        'id_nomina_gape_combinacion',

        'pago_simple',
        'neto',
    ];

    public $timestamps = true;
}
