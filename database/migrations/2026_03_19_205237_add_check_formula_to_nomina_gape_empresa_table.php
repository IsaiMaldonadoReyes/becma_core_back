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
            $table->boolean('formula_con_falta')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('nomina_gape_empresa', function (Blueprint $table) {
            //
            $table->dropColumn('formula_con_falta');
        });
    }
};
