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
        Schema::create('core_directorio', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->boolean('estado')->nullable();
            $table->unsignedBigInteger('usuario_creador')->nullable();
            $table->unsignedBigInteger('usuario_modificador')->nullable();
            $table->unsignedBigInteger('id_sistema')->nullable();
            $table->integer('id_padre')->nullable();
            $table->string('nombre')->nullable();
            $table->string('etiqueta')->nullable();
            $table->string('descripcion')->nullable();
            $table->string('ruta')->nullable();
            $table->string('icono')->nullable();
            $table->integer('orden')->nullable();

            $table->foreign('usuario_creador')->references('id')->on('users');
            $table->foreign('usuario_modificador')->references('id')->on('users');
            $table->foreign('id_sistema')->references('id')->on('sistema');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('core_directorio');
    }
};
