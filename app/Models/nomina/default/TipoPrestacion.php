<?php

namespace App\Models\nomina\default;

use Illuminate\Database\Eloquent\Model;

class TipoPrestacion extends Model
{
    //

    protected $connection = 'sqlsrv_dynamic';

    protected $primaryKey = 'IDTabla';
    protected $table = 'nom10050';
}
