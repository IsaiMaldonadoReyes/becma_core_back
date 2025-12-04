<?php

namespace App\Models\nomina\default;

use Illuminate\Database\Eloquent\Model;

class TipoIncidencia extends Model
{
    //
    protected $connection = 'sqlsrv_dynamic';

    protected $primaryKey = 'IdTipoIncidencia';
    protected $table = 'nom10022';

    public $timestamps = false;
}
