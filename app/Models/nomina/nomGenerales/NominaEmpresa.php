<?php

namespace App\Models\nomina\nomGenerales;

use Illuminate\Database\Eloquent\Model;

class NominaEmpresa extends Model
{
    //

    protected $connection = 'sqlsrv_dynamic';

    protected $primaryKey = 'IDEmpresa';
    protected $table = 'NOM10000';
}
