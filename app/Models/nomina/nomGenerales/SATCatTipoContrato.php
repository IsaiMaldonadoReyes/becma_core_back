<?php

namespace App\Models\nomina\nomGenerales;

use Illuminate\Database\Eloquent\Model;

class SATCatTipoContrato extends Model
{
    //

    protected $connection = 'sqlsrv_dynamic';

    protected $primaryKey = 'ClaveTipoContrato';
    protected $table = 'SATCatTipoContrato';

    protected $casts = [
        'ClaveTipoContrato' => 'string',
    ];
}
