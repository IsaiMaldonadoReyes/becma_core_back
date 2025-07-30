<?php

namespace App\Models\nomina\default;

use Illuminate\Database\Eloquent\Model;

class RegistroPatronal extends Model
{
    //

    protected $connection = 'sqlsrv_dynamic';

    protected $primaryKey = 'cidregistropatronal';
    protected $table = 'nom10035';
}
