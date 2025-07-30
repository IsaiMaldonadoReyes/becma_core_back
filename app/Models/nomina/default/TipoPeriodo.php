<?php

namespace App\Models\nomina\default;

use Illuminate\Database\Eloquent\Model;

// tipos de periodos

class TipoPeriodo extends Model
{
    //

    protected $connection = 'sqlsrv_dynamic';

    protected $primaryKey = 'idtipoperiodo';
    protected $table = 'nom10023';
}
