<?php

namespace App\Models\nomina\GAPE;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class NominaGapeIncidencia extends Model
{
    //
    use HasFactory;

    protected $primaryKey = 'id';
    protected $table = 'nomina_gape_incidencia';

    protected $fillable = [
        'id_nomina_gape_cliente',
        'id_nomina_gape_empresa',
        'id_tipo_periodo',
        'id_periodo',
        'estado',
    ];

    public $timestamps = true;

    public function detalles()
    {
        return $this->hasMany(NominaGapeIncidenciaDetalle::class, 'id_nomina_gape_incidencia');
    }
}
