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
        Schema::create('nomina_gape_parametrizacion', function (Blueprint $table) {
            $table->id();
            $table->timestamps();

            $table->boolean('estado')->nullable();
            $table->unsignedBigInteger('usuario_creador')->nullable();
            $table->unsignedBigInteger('usuario_modificador')->nullable();

            $table->unsignedBigInteger('id_nomina_gape_cliente')->nullable();
            $table->unsignedBigInteger('id_nomina_gape_empresa')->nullable();

            $table->unsignedBigInteger('id_tipo_periodo')->nullable();
            $table->string('clase_prima_riesgo')->nullable();
            $table->double('clase_prima_riesgo_valor')->nullable();

            $table->double('fee')->nullable();
            $table->string('base_fee')->nullable();
            $table->string('provisiones')->nullable();

            $table->double('isn')->nullable();
            $table->string('cuota_sindical')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nomina_gape_parametrizacion');
    }
};
