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
        Schema::table('nomina_gape_banco_banorte', function (Blueprint $table) {
            //
            $table->bigInteger('id_nomina_gape_cliente')->nullable();
            $table->bigInteger('id_nomina_gape_esquema')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('nomina_gape_banco_banorte', function (Blueprint $table) {
            //
            $table->dropColumn('id_nomina_gape_cliente');
            $table->dropColumn('id_nomina_gape_esquema');
        });
    }
};
