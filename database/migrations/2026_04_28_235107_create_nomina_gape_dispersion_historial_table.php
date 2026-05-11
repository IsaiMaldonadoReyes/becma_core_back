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
        Schema::create('nomina_gape_dispersion_historial', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->unsignedBigInteger('usuario_creador')->nullable();
            $table->unsignedBigInteger('usuario_modificador')->nullable();

            $table->unsignedBigInteger('id_nomina_gape_cliente')->nullable();
            $table->unsignedBigInteger('id_nomina_gape_empresa')->nullable();

            $table->unsignedBigInteger('id_nomina_gape_banco_configuracion')->nullable();

            $table->string('ejercicio')->nullable();
            $table->string('periodo')->nullable();
            $table->string('folio_dispersion')->nullable();
            $table->double('monto_nomina')->nullable();
            $table->double('total_empleados')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nomina_gape_dispersion_historial');
    }
};
