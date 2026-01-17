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
        Schema::create('nomina_gape_combinacion_prevision', function (Blueprint $table) {
            $table->id();
            $table->timestamps();

            $table->unsignedBigInteger('nomina_gape_empresa_periodo_combinacion_parametrizacion')->nullable();
            $table->unsignedBigInteger('id_concepto')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nomina_gape_combinacion_prevision');
    }
};
