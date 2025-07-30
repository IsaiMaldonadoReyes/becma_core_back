<?php

namespace App\Models\nomina\nomGenerales;

use Illuminate\Database\Eloquent\Model;

class IMSSCatTipoSemanaReducida extends Model
{
    //

    protected $connection = 'sqlsrv_dynamic';

    protected $primaryKey = 'tipoSemanaReducida';
    protected $table = 'IMSSCatTipoSemanaReducida';
}
