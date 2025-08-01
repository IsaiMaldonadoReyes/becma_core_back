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
        Schema::create('presupuesto_periodo', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->integer('id_empresa')->nullable();
            $table->integer('id_agente')->nullable();
            $table->integer('id_ejercicio')->nullable();
            $table->double('enero')->nullable();
            $table->double('febrero')->nullable();
            $table->double('marzo')->nullable();
            $table->double('abril')->nullable();
            $table->double('mayo')->nullable();
            $table->double('junio')->nullable();
            $table->double('julio')->nullable();
            $table->double('agosto')->nullable();
            $table->double('septiembre')->nullable();
            $table->double('octubre')->nullable();
            $table->double('noviembre')->nullable();
            $table->double('diciembre')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('presupuesto_periodo');
    }
};
