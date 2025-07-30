<?php

namespace App\Models\nomina\nomGenerales;

use Illuminate\Database\Eloquent\Model;

class SATCatFormaPago extends Model
{
    //

    protected $connection = 'sqlsrv_dynamic';

    protected $primaryKey = 'Codigo';
    protected $table = 'SATCatFormaPago';
}
