<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extiende `sesiones_congreso` para soportar la selección de talleres
 * durante la inscripción. `taller_id` agrupa la sesión en un taller
 * (nullOnDelete — borrar un taller no borra sus sesiones, quedan como
 * ponencias sueltas). `precio` es override opcional por sesión; si es
 * NULL, el precio efectivo se hereda del taller. Ver
 * brain/PLAN-CONGRESOS-TALLERES-HORARIOS-IMPLEMENTACION.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sesiones_congreso', function (Blueprint $table) {
            $table->foreignId('taller_id')
                ->nullable()
                ->after('evento_id')
                ->constrained('talleres')
                ->nullOnDelete();
            $table->decimal('precio', 10, 2)->nullable()->after('cupo');

            $table->index('taller_id', 'sesiones_congreso_taller_id_idx');
        });
    }

    public function down(): void
    {
        Schema::table('sesiones_congreso', function (Blueprint $table) {
            $table->dropForeign(['taller_id']);
            $table->dropIndex('sesiones_congreso_taller_id_idx');
            $table->dropColumn(['taller_id', 'precio']);
        });
    }
};