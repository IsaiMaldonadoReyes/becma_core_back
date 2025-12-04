<?php

namespace App\Models\nomina\default;

use Illuminate\Database\Eloquent\Model;

class MovimientosDiasHorasVigente extends Model
{
    //
    protected $connection = 'sqlsrv_dynamic';

    protected $primaryKey = 'IdMovtoDyH';
    protected $table = 'NOM10010';

    public $timestamps = false;
}
