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
        Schema::create('nomina_gape_tipo_periodo', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->string('nombretipoperiodo')->nullable();
            $table->double('diasdelperiodo')->nullable();
            $table->double('diasdepago')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nomina_gape_tipo_periodo');
    }
};
