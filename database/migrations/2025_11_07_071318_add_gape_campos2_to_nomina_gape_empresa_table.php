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
        Schema::table('nomina_gape_empresa', function (Blueprint $table) {
            //
            $table->string('mascara_codigo')->nullable();
            $table->string('codigo_inicial')->nullable();
            $table->string('codigo_actual')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('nomina_gape_empresa', function (Blueprint $table) {
            //
            $table->dropColumn('mascara_codigo');
            $table->dropColumn('codigo_inicial');
            $table->dropColumn('codigo_actual');
        });
    }
};
