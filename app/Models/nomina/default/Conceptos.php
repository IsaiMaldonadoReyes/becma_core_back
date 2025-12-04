<?php

namespace App\Models\nomina\default;

use Illuminate\Database\Eloquent\Model;

class Conceptos extends Model
{
    //
    protected $connection = 'sqlsrv_dynamic';

    protected $primaryKey = 'idconcepto';
    protected $table = 'nom10004';
}
