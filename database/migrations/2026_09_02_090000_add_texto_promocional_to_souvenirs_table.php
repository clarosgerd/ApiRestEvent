<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Texto promocional por souvenir (02/09/2026) — pedido del usuario:
     * un campo de texto libre, opcional, para promocionar el ítem en el
     * formulario público (ej. "La mejor Coca-Cola bien fría"). Puramente
     * de marketing, no afecta precio ni disponibilidad ni ningún cálculo.
     *
     * Nullable, sin default — un souvenir existente sin este texto no
     * muestra nada nuevo, cero cambio de comportamiento.
     */
    public function up(): void
    {
        Schema::table('souvenirs', function (Blueprint $table) {
            $table->string('texto_promocional', 500)->nullable()->after('aplica_cargo_servicio');
        });
    }

    public function down(): void
    {
        Schema::table('souvenirs', function (Blueprint $table) {
            $table->dropColumn('texto_promocional');
        });
    }
};
