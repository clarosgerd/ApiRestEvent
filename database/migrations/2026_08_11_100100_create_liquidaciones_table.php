<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Consolidación financiera — ver 2026_08_11_100000_create_socios_table.php.
 *
 * Una liquidación por evento (`evento_id` unique — mismo patrón de
 * idempotencia que `registrations.origen_legado`: no se liquida dos veces
 * el mismo evento). `monto_base`/`cantidad_inscripciones` son un snapshot
 * de lo que se calculó al momento de liquidar (suma de
 * `registration_totals.fee` de inscripciones `paid`), no un valor
 * recalculado en cada lectura.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('liquidaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evento_id')->unique()->constrained('eventos')->cascadeOnDelete();
            $table->decimal('monto_base', 10, 2);
            $table->unsignedInteger('cantidad_inscripciones');
            $table->foreignId('liquidado_por_admin_user_id')->nullable()->constrained('admin_users')->nullOnDelete();
            // useCurrent() en vez de dejar la columna sin default: sin un
            // default explícito, MySQL trata a la primera columna TIMESTAMP
            // NOT NULL de la tabla como "mágica" y le agrega
            // ON UPDATE CURRENT_TIMESTAMP solo — eso pisaría silenciosamente
            // la fecha real de liquidación en cualquier UPDATE futuro de la
            // fila (ej. al guardar `notas`).
            $table->timestamp('liquidado_en')->useCurrent();
            $table->text('notas')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('liquidaciones');
    }
};
