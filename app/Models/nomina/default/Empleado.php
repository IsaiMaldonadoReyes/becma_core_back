<?php

namespace App\Models\nomina\default;

use Illuminate\Database\Eloquent\Model;

class Empleado extends Model
{
    //

    protected $connection = 'sqlsrv_dynamic';

    protected $primaryKey = 'idempleado';
    protected $table = 'nom10001';

    public $timestamps = false;

    protected $casts = [
        'calculado' => 'boolean',
        'afectado' => 'boolean',
        'calculadoextraordinario' => 'boolean',
        'afectadoextraordinario' => 'boolean',
        'interfazcheqpaqw' => 'boolean',
        'modificacionneto' => 'boolean',
        'calculoptu' => 'boolean',
        'calculoaguinaldo' => 'boolean',
        'cambiocotizacionimss' => 'boolean',
        'Subcontratacion' => 'boolean',
        'ExtranjeroSinCURP' => 'boolean',


        'iddepartamento' => 'integer',
        'idpuesto' => 'integer',
        'idtipoperiodo' => 'integer',
        'idturno' => 'integer',
        'cidregistropatronal' => 'integer',
        'TipoPrestacion' => 'integer',
    ];

    protected $fillable = [
        'iddepartamento',
        'idpuesto',
        'idtipoperiodo',
        'idturno',
        'codigoempleado',
        'nombre',
        'apellidopaterno',
        'apellidomaterno',
        'nombrelargo',
        'fechanacimiento',
        'lugarnacimiento',
        'estadocivil',
        'sexo',
        'curpi',
        'curpf',
        'numerosegurosocial',
        'umf',
        'rfc',
        'homoclave',
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
        'telefono',
        'codigopostal',
        'direccion',
        'poblacion',
        'nombrepadre',
        'nombremadre',
        'numeroafore',
        'causabaja',
        'TipoRegimen',
        'CorreoElectronico',
        'ClabeInterbancaria',
        'calculoptu',
        'calculoaguinaldo',
        'modificacionsalarioimss',
        'altaimss',
        'bajaimss',
        'cambiocotizacionimss',
        'Subcontratacion',
        'ExtranjeroSinCURP',
        'TipoPrestacion',
        'DiasVacTomadasAntesdeAlta',
        'DiasPrimaVacTomadasAntesdeAlta',
        'TipoSemanaReducida',
        'Teletrabajador',
        'EntidadFederativa',
        'estado',
        'cestadoempleadoperiodo',
        'ajustealneto',
        'cidregistropatronal',
        'csueldomixto',
        'fechabaja',
        'fechareingreso',
        'cfechasueldomixto',
        'NumeroFonacot',
        'sueldobaseliquidacion',
        'campoextra1',
        'ccampoextranumerico1',
        'ccampoextranumerico2',
    ];
}
