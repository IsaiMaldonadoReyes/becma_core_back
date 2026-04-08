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
        Schema::table('nomina_gape_formulas_contpaq', function (Blueprint $table) {
            //
            $table->text('formula_sin_faltas')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('nomina_gape_formulas_contpaq', function (Blueprint $table) {
            //
            $table->dropColumn('formula_sin_faltas');
        });
    }
};
