<?php

namespace App\Models\nomina\default;

use Illuminate\Database\Eloquent\Model;

class MovimientosPDOVigente extends Model
{
    //
    protected $connection = 'sqlsrv_dynamic';

    protected $primaryKey = 'IdMovtoPDO';
    protected $table = 'nom10008';

    public $timestamps = false;

    protected $fillable = [
        'idperiodo',
        'idempleado',
        'idconcepto',
        'idmovtopermanente',
        'importetotal',
        'valor',
        'importe1',
        'importe2',
        'importe3',
        'importe4',
        'importetotalreportado',
        'importe1reportado',
        'importe2reportado',
        'importe3reportado',
        'importe4reportado',
        'timestamp',
        'valorReportado',
    ];
}
