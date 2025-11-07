<?php

namespace App\Models\core;

use Illuminate\Database\Eloquent\Model;

class EmpresaDatabase extends Model
{
    //
    protected $fillable = [
        'id_conexion',
        'nombre_empresa',
        'nombre_base',
        'estado',
    ];
    protected $primaryKey = 'id';
    protected $table = 'empresa_database';

    protected $casts = [
        'id' => 'integer',
        'estado' => 'boolean',
    ];
}
