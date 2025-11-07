<?php

namespace App\Models\nomina\default;

use Illuminate\Database\Eloquent\Model;

class EmpleadosPorPeriodo extends Model
{
    protected $connection = 'sqlsrv_dynamic';
    protected $table = 'nom10034';

    // 🔑 Clave primaria compuesta (por compatibilidad usa solo 'idempleado')
    protected $primaryKey = 'idempleado';

    public $timestamps = false;

    // ✅ Campos permitidos para asignación masiva
    protected $fillable = [
        'idempleado',
        'idtipoperiodo',
        'cidperiodo',
        'iddepartamento',
        'idpuesto',
        'idturno',
        'estadocivil',
        'umf',
        'cuentapagoelectronico',
        'sucursalpagoelectronico',
        'bancopagoelectronico',
        'estadoempleado',
        'sueldodiario',
        'fechasueldodiario',
        'sueldovariable',
        'fechasueldovariable',
        'sueldopromedio',
        'fechasueldopromedio',
        'sueldointegrado',
        'fechasueldointegrado',
        'calculado',
        'afectado',
        'calculadoextraordinario',
        'afectadoextraordinario',
        'interfazcheqpaqw',
        'modificacionneto',
        'fechaalta',
        'cuentacw',
        'tipocontrato',
        'basecotizacionimss',
        'tipoempleado',
        'basepago',
        'formapago',
        'zonasalario',
        'modificacionsalarioimss',
        'altaimss',
        'bajaimss',
        'cambiocotizacionimss',
        'telefono',
        'codigopostal',
        'direccion',
        'poblacion',
        'estado',
        'numeroafore',
        'fechabaja',
        'causabaja',
        'sueldobaseliquidacion',
        'campoextra1',
        'campoextra2',
        'campoextra3',
        'fechareingreso',
        'ajustealneto',
        'timestamp',
        'cidregistropatronal',
        'cestadoempleadoperiodo',
        'ccampoextranumerico1',
        'ccampoextranumerico2',
        'ccampoextranumerico3',
        'ccampoextranumerico4',
        'ccampoextranumerico5',
        'cdiastrabajados',
        'cdiaspagados',
        'cdiascotizados',
        'cdiasausencia',
        'cdiasincapacidades',
        'cdiasvacaciones',
        'cdiaspropseptimos',
        'chorasextras1',
        'chorasextras2',
        'chorasextras3',
        'cfechasueldomixto',
        'csueldomixto',
        'cfechacorte',
        'CorreoElectronico',
        'ClabeInterbancaria',
        'TipoPrestacion',
        'TipoSemanaReducida',
        'Teletrabajador',
        'idLider',
        'checkColabora',
    ];
}
