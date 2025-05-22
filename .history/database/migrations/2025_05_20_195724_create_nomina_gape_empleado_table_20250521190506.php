<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('nomina_gape_empleado', function (Blueprint $table) {
            $table->id();
            $table->timestamps();

            $table->boolean('estado')->nullable();
            $table->unsignedBigInteger('usuario_creador')->nullable();
            $table->unsignedBigInteger('usuario_modificador')->nullable();


            $table->increments('idempleado');
        $table->integer('iddepartamento')->nullable();
        $table->integer('idpuesto')->nullable();
        $table->integer('idtipoperiodo')->nullable();
        $table->integer('idturno')->nullable();
        $table->string('codigoempleado', 30)->nullable();
        $table->string('nombre', 85)->nullable();
        $table->binary('fotografia')->nullable();
        $table->string('apellidopaterno', 84)->nullable();
        $table->string('apellidomaterno', 83)->nullable();
        $table->string('nombrelargo', 254)->nullable();
        $table->dateTime('fechanacimiento')->nullable();
        $table->string('lugarnacimiento', 40)->nullable();
        $table->string('estadocivil', 1)->nullable();
        $table->string('sexo', 1)->nullable();
        $table->string('curpi', 6)->nullable();
        $table->string('curpf', 8)->nullable();
        $table->string('numerosegurosocial', 15)->nullable();
        $table->integer('umf')->nullable();
        $table->string('rfc', 4)->nullable();
        $table->string('homoclave', 4)->nullable();
        $table->string('cuentapagoelectronico', 20)->nullable();
        $table->string('sucursalpagoelectronico', 50)->nullable();
        $table->string('bancopagoelectronico', 3)->nullable();
        $table->string('estadoempleado', 1)->nullable();
        $table->float('sueldodiario')->nullable();
        $table->dateTime('fechasueldodiario')->nullable();
        $table->float('sueldovariable')->nullable();
        $table->dateTime('fechasueldovariable')->nullable();
        $table->float('sueldopromedio')->nullable();
        $table->dateTime('fechasueldopromedio')->nullable();
        $table->float('sueldointegrado')->nullable();
        $table->dateTime('fechasueldointegrado')->nullable();
        $table->boolean('calculado');
        $table->boolean('afectado');
        $table->boolean('calculadoextraordinario');
        $table->boolean('afectadoextraordinario')->nullable();
        $table->boolean('interfazcheqpaqw');
        $table->boolean('modificacionneto');
        $table->dateTime('fechaalta')->nullable();
        $table->string('cuentacw', 31)->nullable();
        $table->string('tipocontrato', 2)->nullable();
        $table->string('basecotizacionimss', 1)->nullable();
        $table->string('tipoempleado', 1)->nullable();
        $table->string('basepago', 1)->nullable();
        $table->string('formapago', 3)->nullable();
        $table->string('zonasalario', 1)->nullable();
        $table->boolean('calculoptu');
        $table->boolean('calculoaguinaldo');
        $table->boolean('modificacionsalarioimss');
        $table->boolean('altaimss');
        $table->boolean('bajaimss');
        $table->boolean('cambiocotizacionimss');
        $table->text('expediente')->nullable();
        $table->string('telefono', 20)->nullable();
        $table->string('codigopostal', 5)->nullable();
        $table->string('direccion', 60)->nullable();
        $table->string('poblacion', 60)->nullable();
        $table->string('estado', 3)->nullable();
        $table->string('nombrepadre', 60)->nullable();
        $table->string('nombremadre', 60)->nullable();
        $table->string('numeroafore', 50)->nullable();
        $table->dateTime('fechabaja')->nullable();
        $table->string('causabaja', 60)->nullable();
        $table->float('sueldobaseliquidacion')->nullable();
        $table->string('campoextra1', 40)->nullable();
        $table->string('campoextra2', 40)->nullable();
        $table->string('campoextra3', 40)->nullable();
        $table->dateTime('fechareingreso')->nullable();
        $table->float('ajustealneto')->nullable();
        $table->dateTime('timestamp')->nullable();
        $table->integer('cidregistropatronal')->nullable();
        $table->float('ccampoextranumerico1')->nullable();
        $table->float('ccampoextranumerico2')->nullable();
        $table->float('ccampoextranumerico3')->nullable();
        $table->float('ccampoextranumerico4')->nullable();
        $table->float('ccampoextranumerico5')->nullable();
        $table->string('cestadoempleadoperiodo', 4)->nullable();
        $table->dateTime('cfechasueldomixto')->nullable();
        $table->float('csueldomixto')->nullable();
        $table->string('NumeroFonacot', 20)->nullable();
        $table->string('CorreoElectronico', 60)->nullable();
        $table->string('TipoRegimen', 2)->nullable();
        $table->string('ClabeInterbancaria', 30)->nullable();
        $table->string('EntidadFederativa', 2);
        $table->boolean('Subcontratacion');
        $table->boolean('ExtranjeroSinCURP');
        $table->integer('TipoPrestacion');
        $table->float('DiasVacTomadasAntesdeAlta');
        $table->float('DiasPrimaVacTomadasAntesdeAlta');
        $table->integer('TipoSemanaReducida');
        $table->tinyInteger('Teletrabajador');
        $table->string('Equipo', 254)->nullable();
        $table->string('Insumo', 254)->nullable();
        $table->string('DireccionTeletrabajo', 254)->nullable();

            $table->boolean('carga_masiva')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nomina_gape_empleado');
    }
};
