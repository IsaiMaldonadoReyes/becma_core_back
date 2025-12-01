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
        Schema::create('nomina_gape_incidencia_detalle', function (Blueprint $table) {
            $table->id();
            $table->timestamps();

            $table->boolean('estado')->nullable();
            $table->unsignedBigInteger('id_nomina_gape_incidencia')->nullable();

            $table->unsignedBigInteger('id_empleado')->nullable();

            $table->double('cantidad_faltas')->nullable();
            $table->double('cantidad_incapacidad')->nullable();
            $table->double('cantidad_vacaciones')->nullable();
            $table->double('cantidad_prima_dominical')->nullable();

            $table->double('cantidad_dias_retroactivos')->nullable();
            $table->double('cantidad_dias_festivos')->nullable();

            $table->double('comision')->nullable();
            $table->double('bono')->nullable();

            $table->double('horas_extra_doble_cantidad')->nullable();
            $table->double('horas_extra_doble')->nullable();

            $table->double('horas_extra_triple_cantidad')->nullable();
            $table->double('horas_extra_triple')->nullable();

            $table->double('pago_adicional')->nullable();
            $table->double('premio_puntualidad')->nullable();


        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nomina_gape_incidencia_detalle');
    }
};
