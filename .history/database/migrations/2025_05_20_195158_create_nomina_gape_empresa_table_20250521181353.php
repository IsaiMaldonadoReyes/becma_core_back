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
        Schema::create('nomina_gape_empresa', function (Blueprint $table) {
            $table->id();
            $table->timestamps();

            $table->boolean('sueldo_imss')->nullable();
            $table->boolean('prevision_social')->nullable();
            $table->boolean('fondos_sindical')->nullable();
            $table->boolean('tarjeta_facil')->nullable();
            $table->boolean('honorarios_asimilados')->nullable();
            $table->boolean('gastos_por_comprobar')->nullable();
            $table->integer('periodicidad')->nullable();
            $table->boolean('provisiones')->nullable();
            $table->boolean('couta_sindical')->nullable();

            $table->date('fecha_inicio_licencia')->nullable();
            $table->date('fecha_fin_licencia')->nullable();

            $table->string('base_datos')->nullable();
            $table->unsignedBigInteger('id_empresa_contpaq')->nullable();

            $table->date('fecha_creacion')->nullable();
            $table->date('fecha_modificacion')->nullable();
            $table->boolean('es_activa')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nomina_gape_empresa');
    }
};
