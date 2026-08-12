<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Presupuesto de un evento — ver 2026_08_11_110000_create_presupuesto_categorias_table.php.
 *
 * `tipo` va denormalizado acá (no solo en la categoría) — se valida en el
 * controller que coincida con el tipo real de `presupuesto_categoria_id`
 * al crear, pero queda fijo en la fila para que si alguien cambia el tipo
 * de una categoría vieja más adelante, los movimientos ya registrados no
 * cambien de significado retroactivamente (mismo criterio de snapshot que
 * `liquidacion_detalles.porcentaje`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('presupuesto_evento', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evento_id')->constrained('eventos')->cascadeOnDelete();
            $table->foreignId('presupuesto_categoria_id')->nullable()->constrained('presupuesto_categorias')->nullOnDelete();
            $table->enum('tipo', ['ingreso', 'gasto']);
            $table->decimal('monto', 10, 2);
            $table->string('moneda')->nullable();
            $table->date('fecha');
            $table->string('comprobante_url')->nullable();
            $table->foreignId('admin_user_id')->nullable()->constrained('admin_users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('presupuesto_evento');
    }
};
