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

            $table->boolean('estado')->nullable();
            $table->unsignedBigInteger('usuario_creador')->nullable();
            $table->unsignedBigInteger('usuario_modificador')->nullable();
            $table->unsignedBigInteger('id_nomina_gape_cliente')->nullable();
            $table->unsignedBigInteger('id_empresa_database')->nullable();

            $table->boolean('fiscal')->nullable();

            $table->string('razon_social')->nullable();
            $table->string('rfc')->nullable();
            $table->string('codigo_interno')->nullable();
            $table->string('correo_notificacion')->nullable();
        });

        Schema::dropIfExists('nomina_gape_empresa');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {

    }
};
