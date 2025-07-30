<?php

namespace App\Models\nomina\default;

use Illuminate\Database\Eloquent\Model;

class Empresa extends Model
{
    //

    protected $connection = 'sqlsrv_dynamic';

    protected $table = 'nom10000';
}
