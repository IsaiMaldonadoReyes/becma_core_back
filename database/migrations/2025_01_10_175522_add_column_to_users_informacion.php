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
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('estado')->nullable();
            $table->string('nombre')->nullable();
            $table->string('apellido_parteno')->nullable();
            $table->string('apellido_materno')->nullable();
            $table->string('imagen')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('estado');
            $table->dropColumn('nombre');
            $table->dropColumn('apellido_parteno');
            $table->dropColumn('apellido_materno');
            $table->dropColumn('imagen');
        });
    }
};
