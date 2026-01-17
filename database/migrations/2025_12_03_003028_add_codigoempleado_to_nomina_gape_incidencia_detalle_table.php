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
        Schema::table('nomina_gape_incidencia_detalle', function (Blueprint $table) {
            //
            $table->string('codigo_empleado')->nullable();
            $table->double('cantidad_prima_vacacional')->nullable();
            $table->double('descuento')->nullable();
            $table->double('descuento_aportacion_caja_ahorro')->nullable();
            $table->double('descuento_prestamo_caja_ahorro')->nullable();
            $table->double('infonavit')->nullable();
            $table->double('fonacot')->nullable();
            $table->string('incapacidad_dias')->nullable();
            $table->double('anios_prima_vacacional')->nullable();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('nomina_gape_incidencia_detalle', function (Blueprint $table) {
            //
            $table->dropColumn('codigo_empleado');
            $table->dropColumn('cantidad_prima_vacacional');
            $table->dropColumn('descuento');
            $table->dropColumn('descuento_aportacion_caja_ahorro');
            $table->dropColumn('descuento_prestamo_caja_ahorro');
            $table->dropColumn('infonavit');
            $table->dropColumn('fonacot');
            $table->dropColumn('incapacidad_dias');
            $table->dropColumn('anios_prima_vacacional');
        });
    }
};
