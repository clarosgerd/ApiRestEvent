<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kit, tallas, stock y lista de espera — ver
 * 2026_08_11_140200_add_talla_sexo_to_souvenir_participantes_table.php.
 *
 * `souvenir_id`/`talla`/`sexo` nulos = la espera es por cupo general del
 * form_type lleno (no por un ítem puntual agotado). Quien se anota no
 * necesariamente tiene cuenta (`Persona`) todavía, por eso nombre/correo/
 * teléfono van sueltos en la fila y no como FK.
 *
 * Orden de promoción: FIFO por `created_at` dentro del mismo
 * form_types_id/souvenir_id/talla/sexo — ver
 * App\Actions\PromoverListaEsperaAction.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lista_espera', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evento_id')->constrained('eventos')->cascadeOnDelete();
            $table->foreignId('form_types_id')->constrained('form_types')->cascadeOnDelete();
            $table->foreignId('souvenir_id')->nullable()->constrained('souvenirs')->cascadeOnDelete();
            $table->string('talla')->nullable();
            $table->string('sexo')->nullable();
            $table->string('nombre');
            $table->string('correo');
            $table->string('telefono')->nullable();
            $table->enum('estado', ['pendiente', 'promovido', 'expirado', 'cancelado'])->default('pendiente');
            $table->timestamp('promovido_at')->nullable();
            $table->timestamps();

            $table->index(['form_types_id', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lista_espera');
    }
};
