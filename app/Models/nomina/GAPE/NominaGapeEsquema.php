<?php

namespace App\Models\nomina\GAPE;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class NominaGapeEsquema extends Model
{
    //

    use HasFactory;

    protected $primaryKey = 'id';
    protected $table = 'nomina_gape_esquema';

    protected $casts = [
        'contpaq' => 'boolean',
    ];
}
