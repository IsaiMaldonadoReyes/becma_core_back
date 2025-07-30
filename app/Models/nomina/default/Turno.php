<?php

namespace App\Models\nomina\default;

use Illuminate\Database\Eloquent\Model;

class Turno extends Model
{
    //

    protected $connection = 'sqlsrv_dynamic';

    protected $primaryKey = 'idturno';
    protected $table = 'nom10032';
}
