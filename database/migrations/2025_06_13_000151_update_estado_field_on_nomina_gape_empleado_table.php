<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;


return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        //
        Schema::table('nomina_gape_empleado', function (Blueprint $table) {
            // Renombrar el campo
            $table->renameColumn('estado', 'estado_empleado');
        });

        // Cambiar tipo a BIT (usando raw SQL porque BIT no es soportado directamente por Blueprint)
        DB::statement("ALTER TABLE nomina_gape_empleado ALTER COLUMN estado_empleado BIT");

        // Crear el nuevo campo 'estado' como string
        Schema::table('nomina_gape_empleado', function (Blueprint $table) {
            $table->string('estado')->nullable()->after('estado_empleado');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
        Schema::table('nomina_gape_empleado', function (Blueprint $table) {
            // Eliminar el nuevo campo
            $table->dropColumn('estado');
        });

        // Volver a tipo anterior si era BOOL o TINYINT(1)
        DB::statement("ALTER TABLE nomina_gape_empleado ALTER COLUMN estado_empleado TINYINT");

        // Renombrar de nuevo a 'estado'
        Schema::table('nomina_gape_empleado', function (Blueprint $table) {
            $table->renameColumn('estado_empleado', 'estado');
        });
    }
};
