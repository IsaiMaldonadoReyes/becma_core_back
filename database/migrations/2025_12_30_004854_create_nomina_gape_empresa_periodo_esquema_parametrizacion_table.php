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
        Schema::create('nomina_gape_empresa_periodo_combinacion_parametrizacion', function (Blueprint $table) {
            $table->id();
            $table->timestamps();

            $table->boolean('estado')->nullable();

            $table->unsignedBigInteger('usuario_creador')->nullable();
            $table->unsignedBigInteger('usuario_modificador')->nullable();

            $table->unsignedBigInteger('id_nomina_gape_cliente')->nullable();
            $table->unsignedBigInteger('id_nomina_gape_empresa')->nullable();
            $table->unsignedBigInteger('id_nomina_gape_tipo_periodo')->nullable();
            $table->unsignedBigInteger('idtipoperiodo')->nullable();
            $table->unsignedBigInteger('id_nomina_gape_cliente_esquema_combinacion')->nullable();

            $table->double('fee')->nullable();
            $table->string('base_fee')->nullable();
            $table->string('provisiones')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nomina_gape_empresa_periodo_combinacion_parametrizacion');
    }
};
