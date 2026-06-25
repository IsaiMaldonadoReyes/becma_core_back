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
        Schema::table('core_directorio_rol', function (Blueprint $table) {
            //
            $table->integer('permiso')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('core_directorio_rol', function (Blueprint $table) {
            //
            $table->dropColumn('permiso');
        });
    }
};
