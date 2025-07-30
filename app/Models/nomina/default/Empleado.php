<?php

namespace App\Models\nomina\default;

use Illuminate\Database\Eloquent\Model;

class Empleado extends Model
{
    //

    protected $connection = 'sqlsrv_dynamic';

    protected $primaryKey = 'idempleado';
    protected $table = 'nom10001';

}
