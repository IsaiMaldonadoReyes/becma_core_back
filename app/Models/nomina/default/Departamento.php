<?php

namespace App\Models\nomina\default;

use Illuminate\Database\Eloquent\Model;

class Departamento extends Model
{
    //

    protected $connection = 'sqlsrv_dynamic';

    protected $primaryKey = 'iddepartamento';
    protected $table = 'nom10003';
}
