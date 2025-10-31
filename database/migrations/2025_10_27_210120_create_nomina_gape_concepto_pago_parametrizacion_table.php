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
        Schema::create('nomina_gape_concepto_pago_parametrizacion', function (Blueprint $table) {
            $table->id();
            $table->timestamps();

            $table->boolean('estado')->nullable();
            $table->unsignedBigInteger('usuario_creador')->nullable();
            $table->unsignedBigInteger('usuario_modificador')->nullable();

            $table->unsignedBigInteger('id_nomina_gape_cliente')->nullable();
            $table->unsignedBigInteger('id_nomina_gape_empresa')->nullable();

            $table->unsignedBigInteger('id_tipo_periodo')->nullable();
            $table->string('tipo_periodo_nombre')->nullable();

            $table->boolean('sueldo_imss')->nullable();
            $table->double('sueldo_imss_tope')->nullable();
            $table->integer('sueldo_imss_orden')->nullable();

            $table->boolean('prev_social')->nullable();
            $table->double('prev_social_tope')->nullable();
            $table->integer('prev_social_orden')->nullable();

            $table->boolean('fondos_sind')->nullable();
            $table->double('fondos_sind_tope')->nullable();
            $table->integer('fondos_sind_orden')->nullable();

            $table->boolean('tarjeta_facil')->nullable();
            $table->double('tarjeta_facil_tope')->nullable();
            $table->integer('tarjeta_facil_orden')->nullable();

            $table->boolean('hon_asimilados')->nullable();
            $table->double('hon_asimilados_tope')->nullable();
            $table->integer('hon_asimilados_orden')->nullable();

            $table->boolean('gastos_compro')->nullable();
            $table->double('gastos_compro_tope')->nullable();
            $table->integer('gastos_compro_orden')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nomina_gape_concepto_pago_parametrizacion');
    }
};
