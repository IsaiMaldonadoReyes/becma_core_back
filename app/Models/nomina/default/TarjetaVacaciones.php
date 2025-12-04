<?php

namespace App\Models\nomina\default;

use Illuminate\Database\Eloquent\Model;

class TarjetaVacaciones extends Model
{
    //
    protected $connection = 'sqlsrv_dynamic';

    protected $primaryKey = 'idtcontrolvacaciones';
    protected $table = 'nom10014';

    public $timestamps = false;

    protected $fillable = [
        'idempleado',
        'ejercicio',
        'diasvacaciones',
        'diasprimavacacional',
        'fechainicio',
        'fechafin',
        'diasdescanso',
        'timestamp',
        'fechapago'
    ];
}
