<?php

namespace App\Models\nomina\default;

use Illuminate\Database\Eloquent\Model;

class TarjetaIncapacidad extends Model
{
    //
    protected $connection = 'sqlsrv_dynamic';

    protected $primaryKey = 'idtarjetaincapacidad';
    protected $table = 'nom10018';

    public $timestamps = false;

    protected $fillable = [
        'idtipoincidencia',
        'idempleado',
        'folio',
        'diasautorizados',
        'fechainicio',
        'descripcion',
        'incapacidadinicial',
        'ramoseguro',
        'tiporiesgo',
        'numerocaso',
        'fincaso',
        'porcentajeincapacidad',
        'controlmaternidad',
        'nombremedico',
        'matriculamedico',
        'circunstancia',
        'timestamp',
        'controlincapacidad',
        'secuelaconsecuencia',
    ];
}
