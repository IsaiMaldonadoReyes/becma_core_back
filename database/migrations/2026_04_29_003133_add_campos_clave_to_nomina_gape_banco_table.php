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
        Schema::table('nomina_gape_banco', function (Blueprint $table) {
            //
            $table->string('clave_interna')->nullable();
            $table->boolean('requiere_cuenta_origen')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('nomina_gape_banco', function (Blueprint $table) {
            //
            $table->dropColumn('clave_interna');
            $table->dropColumn('requiere_cuenta_origen');
        });
    }
};
