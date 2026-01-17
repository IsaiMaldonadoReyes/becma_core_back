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
        Schema::create('nomina_gape_cliente_esquema_combinacion', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->boolean('estado')->nullable();
            $table->unsignedBigInteger('id_nomina_gape_cliente')->nullable();
            $table->unsignedBigInteger('id_nomina_gape_esquema')->nullable();
            $table->string('combinacion')->nullable();
            $table->float('tope')->nullable();
            $table->float('orden')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nomina_gape_cliente_esquema_combinacion');
    }
};
