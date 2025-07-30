<?php

namespace App\Models\nomina\nomGenerales;

use Illuminate\Database\Eloquent\Model;

class SATCatTipoRegimen extends Model
{
    //

    protected $connection = 'sqlsrv_dynamic';

    protected $primaryKey = 'claveTipoRegimen';
    protected $table = 'SATCatTipoRegimen';

    protected $casts = [
        'claveTipoRegimen' => 'string',
    ];
}
