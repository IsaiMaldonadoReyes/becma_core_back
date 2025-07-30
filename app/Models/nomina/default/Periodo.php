<?php

namespace App\Models\nomina\default;

use Illuminate\Database\Eloquent\Model;

class Periodo extends Model
{
    //

    protected $connection = 'sqlsrv_dynamic';

    protected $primaryKey = 'idperiodo';
    protected $table = 'nom10002';
}
