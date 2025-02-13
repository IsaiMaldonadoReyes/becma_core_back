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
        Schema::create('conexion', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->boolean('estado')->nullable();
            $table->integer('id_empresa')->nullable();
            $table->integer('id_sistema')->nullable();
            $table->dateTime('fecha_inicio_licencia')->nullable();
            $table->dateTime('fecha_fin_licencia')->nullable();
            $table->string('usuario')->nullable();
            $table->string('password')->nullable();
            $table->string('ip')->nullable();
            $table->string('puerto')->nullable();
            $table->string('host')->nullable();
            $table->string('base_de_datos')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conexion');
    }
};
