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
        Schema::create('nomina_gape_banco_configuracion_esquema', function (Blueprint $table) {
            $table->id();
            $table->timestamps();

            $table->boolean('estado')->nullable();
            $table->unsignedBigInteger('usuario_creador')->nullable();
            $table->unsignedBigInteger('usuario_modificador')->nullable();

            $table->unsignedBigInteger('id_nomina_gape_banco')->nullable();
            $table->unsignedBigInteger('id_nomina_gape_cliente')->nullable();
            $table->unsignedBigInteger('id_nomina_gape_empresa')->nullable();
            $table->unsignedBigInteger('id_nomina_gape_esquema')->nullable();

            $table->boolean('activo_dispersion')->nullable();

            $table->string('azteca_cuenta_origen')->nullable();

            $table->string('banorte_cuenta_origen')->nullable();
            $table->string('banorte_clave_banco')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nomina_gape_banco_configuracion_esquema');
    }
};
