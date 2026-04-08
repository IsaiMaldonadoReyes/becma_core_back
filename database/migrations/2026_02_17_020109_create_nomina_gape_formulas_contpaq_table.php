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
        Schema::create('nomina_gape_formulas_contpaq', function (Blueprint $table) {
            $table->id();
            $table->timestamps();

            $table->string('numeroconceptoanterior')->nullable();
            $table->string('numeroconcepto')->nullable();

            $table->string('descripcionanterior')->nullable();
            $table->string('descripcion')->nullable();

            $table->string('titulo')->nullable();

            // Si las fórmulas pueden ser largas, mejor usar text
            $table->text('formulaimportetotal')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nomina_gape_formulas_contpaq');
    }
};
