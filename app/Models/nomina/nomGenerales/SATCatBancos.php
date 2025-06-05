<?php

namespace App\Models\nomina\nomGenerales;

use Illuminate\Database\Eloquent\Model;

class SATCatBancos extends Model
{
    //
    protected $primaryKey = 'ClaveBanco';
    protected $table = 'SATCatBancos';

    protected $casts = [
        'ClaveBanco' => 'string',
    ];
}
