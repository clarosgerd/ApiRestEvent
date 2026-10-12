<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Combos de talleres (12/10/2026) — qué combo (si alguno) explica el
 * `discount`/`total` de esta fila. `nullOnDelete`: si el organizador borra
 * el combo después, las filas ya facturadas no se rompen, solo pierden la
 * referencia (su `discount`/`total` ya persistido no cambia).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('participante_taller_sesion', function (Blueprint $table) {
            $table->foreignId('taller_combo_id')->nullable()->after('taller_id')
                ->constrained('taller_combos')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('participante_taller_sesion', function (Blueprint $table) {
            $table->dropConstrainedForeignId('taller_combo_id');
        });
    }
};
