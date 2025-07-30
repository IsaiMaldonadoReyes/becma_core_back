<?php

namespace App\Models\nomina\default;

use Illuminate\Database\Eloquent\Model;

class Puesto extends Model
{
    //

    protected $connection = 'sqlsrv_dynamic';

    protected $primaryKey = 'idpuesto';
    protected $table = 'nom10006';
}
