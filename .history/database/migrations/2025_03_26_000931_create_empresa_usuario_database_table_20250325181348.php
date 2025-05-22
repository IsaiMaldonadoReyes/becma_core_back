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
        Schema::create('empresa_usuario_database', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->boolean('estado')->nullable();
            $table->unsignedBigInteger('usuario_creador')->nullable();
            $table->unsignedBigInteger('usuario_modificador')->nullable();
            $table->unsignedBigInteger('id_empresa_database')->nullable();
            $table->unsignedBigInteger('id_empresa_usuario')->nullable();

            $table->foreign('usuario_creador')->references('id')->on('users');
            $table->foreign('usuario_modificador')->references('id')->on('users');
            $table->foreign('id_empresa_database')->references('id')->on('empresa_database');
            $table->foreign('id_empresa_usuario')->references('id')->on('empresa_usuario');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('empresa_usuario_database');
    }
};
