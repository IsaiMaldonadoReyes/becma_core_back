<?php

namespace App\Models\core;

use Illuminate\Database\Eloquent\Model;

class Sistema extends Model
{
    protected $primaryKey = 'id';
    protected $table = 'sistema';

    protected $fillable = [
        'nombre',
        'codigo',
        'descripcion',
        'estado'
    ];
}
