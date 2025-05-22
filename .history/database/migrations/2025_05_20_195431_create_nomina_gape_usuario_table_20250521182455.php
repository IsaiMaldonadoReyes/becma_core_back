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
        Schema::create('nomina_gape_usuario', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->unsignedBigInteger('id_empresa_usuario')->nullable();
            $table->unsignedBigInteger('id_nomina_gape_empresa')->nullable();

            $table->foreign('usuario_creador')->references('id')->on('users');
            $table->foreign('usuario_modificador')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nomina_gape_usuario');
    }
};
