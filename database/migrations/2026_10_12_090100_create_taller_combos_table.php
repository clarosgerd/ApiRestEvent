<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Combos de talleres (12/10/2026, pedido del usuario) — ej. "Taller A +
 * Taller B por el precio de 1" o "por $300 fijo". El organizador elige
 * explícitamente qué talleres participan de cada combo (tabla pivote
 * `taller_combo_items`) y luego carga, aparte, el precio que ese combo
 * cobra por categoría (ver `taller_combo_precios` — el precio NO vive acá,
 * el mismo combo puede costar distinto según la categoría del
 * participante). Sustituye la suma de los precios individuales cuando el
 * participante elige todos los talleres miembro juntos en la misma
 * operación — ver App\Support\Taller\ResolverCombosTallerAction y el
 * comentario de `participante_taller_sesion.taller_combo_id` sobre "nunca
 * retroactivo".
 *
 * `fecha_desde`/`fecha_hasta` opcionales (a diferencia de los períodos de
 * precio de taller/categoría, que son obligatorios): un combo puede no
 * tener vigencia por fecha y estar activo indefinidamente mientras
 * `activo=true`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('taller_combos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evento_id')->constrained('eventos')->cascadeOnDelete();
            $table->string('nombre');
            $table->date('fecha_desde')->nullable();
            $table->date('fecha_hasta')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index(['evento_id', 'activo']);
        });

        Schema::create('taller_combo_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('taller_combo_id')->constrained('taller_combos')->cascadeOnDelete();
            $table->foreignId('taller_id')->constrained('talleres')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['taller_combo_id', 'taller_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('taller_combo_items');
        Schema::dropIfExists('taller_combos');
    }
};
