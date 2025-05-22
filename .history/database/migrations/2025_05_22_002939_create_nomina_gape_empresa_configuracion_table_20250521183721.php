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
        Schema::create('nomina_gape_empresa_configuracion', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->boolean('estado')->nullable();

            $table->unsignedBigInteger('id_nomina_gape_empresa')->nullable();

            $table->string('periodo')->nullable();
            $table->double('uma_vigente')->nullable();
            $table->double('porcentaje_uma_subsidio')->nullable();
            $table->double('fee')->nullable();
            $table->double('isn')->nullable();
            $table->integer('base_fee')->nullable();
            $table->string('prima_riesgo')->nullable();
            $table->double('prima_riesgo_dato')->nullable();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nomina_gape_empresa_configuracion');
    }
};
