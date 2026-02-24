<?php

namespace App\Models\nomina\default;

use Illuminate\Database\Eloquent\Model;

class Conceptos extends Model
{
    //
    protected $connection = 'sqlsrv_dynamic';

    protected $primaryKey = 'idconcepto';
    protected $table = 'nom10004';

    public $timestamps = false;

    protected $fillable = [
        'numeroconcepto',
        'tipoconcepto',
        'descripcion',
        'especie',
        'automaticoglobal',
        'automaticoliquidacion',
        'imprimir',
        'articulo86',
        'leyendaimporte1',
        'leyendaimporte2',
        'leyendaimporte3',
        'leyendaimporte4',
        'cuentacw',
        'tipomovtocw',
        'contracuentacw',
        'contabcuentacw',
        'contabcontracuentacw',
        'leyendavalor',
        'formulaimportetotal',
        'formulaimporte1',
        'formulaimporte2',
        'formulaimporte3',
        'formulaimporte4',
        'timestamp',
        'FormulaValor',
        'CuentaGravado',
        'CuentaExentoDeduc',
        'CuentaExentoNoDeduc',
        'ClaveAgrupadoraSAT',
        'MetodoDePago',
        'TipoClaveSAT',
        'TipoHoras',
    ];
}
