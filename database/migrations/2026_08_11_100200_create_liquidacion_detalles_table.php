<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Consolidación financiera — ver 2026_08_11_100000_create_socios_table.php.
 *
 * `socio_nombre`/`porcentaje` son un snapshot al momento de liquidar — si
 * el socio se edita o se borra después, las liquidaciones ya confirmadas
 * no cambian de significado. `socio_id` usa nullOnDelete (no
 * cascadeOnDelete) para que borrar un socio nunca borre historial real de
 * reparto de plata.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('liquidacion_detalles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('liquidacion_id')->constrained('liquidaciones')->cascadeOnDelete();
            $table->foreignId('socio_id')->nullable()->constrained('socios')->nullOnDelete();
            $table->string('socio_nombre');
            $table->decimal('porcentaje', 5, 2);
            $table->decimal('monto', 10, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('liquidacion_detalles');
    }
};
