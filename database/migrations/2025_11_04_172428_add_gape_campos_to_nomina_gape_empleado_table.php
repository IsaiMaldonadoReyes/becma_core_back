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
        Schema::table('nomina_gape_empleado', function (Blueprint $table) {
            //
            $table->string('id_nomina_gape_cliente')->nullable();
            $table->boolean('fiscal')->nullable();
            $table->dateTime('fecha_alta_gape')->nullable();
            $table->float('sueldo_real')->nullable();
            $table->float('sueldo_imss_gape')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('nomina_gape_empleado', function (Blueprint $table) {
            //
            $table->dropColumn('id_nomina_gape_cliente');
            $table->dropColumn('fiscal');
            $table->dropColumn('fecha_alta_gape');
            $table->dropColumn('sueldo_real');
            $table->dropColumn('sueldo_imss_gape');
        });
    }
};
