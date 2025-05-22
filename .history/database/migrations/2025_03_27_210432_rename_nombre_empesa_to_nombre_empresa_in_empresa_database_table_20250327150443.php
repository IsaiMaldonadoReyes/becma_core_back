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
        Schema::table('empresa_database', function (Blueprint $table) {
            $table->renameColumn('nombre_empesa', 'nombre_empresa');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('empresa_database', function (Blueprint $table) {
            $table->renameColumn('nombre_empresa', 'nombre_empesa');
        });
    }
};