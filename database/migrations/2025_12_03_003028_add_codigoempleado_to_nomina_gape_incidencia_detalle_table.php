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
        });
    }
};
