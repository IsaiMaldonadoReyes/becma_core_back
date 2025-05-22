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
        Schema::create('nomina_gape_dato_importacion_incidencia', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->boolean('estado')->nullable();

            $table->unsignedBigInteger('id_nomina_gape_caracteristica_importacion_incidencia')->nullable();
            $table->unsignedBigInteger('id_nomina_gape_empresa')->nullable();

            $table->string('dato_celda')->nullable();
            $table->string('celda')->nullable();

            $table->integer('id_periodo')->nullable();
            $table->integer('id_tipo_periodo')->nullable();

            $table->boolean('procesado')->nullable();
            $table->integer('id_contpaq')->nullable();


        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nomina_gape_dato_importacion_incidencia');
    }
};
