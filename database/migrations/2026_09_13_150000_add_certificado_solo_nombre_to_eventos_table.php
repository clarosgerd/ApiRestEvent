<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Certificado imprime solo nombre y apellido, sin título/alias ni el
     * párrafo de rol/fecha (pedido real de COLABIOCLI 2026). Default
     * false: ningún evento existente (ej. CIACRUZ) cambia su certificado.
     */
    public function up(): void
    {
        Schema::table('eventos', function (Blueprint $table) {
            $table->boolean('certificado_solo_nombre')->default(false)->after('color_hex');
        });
    }

    public function down(): void
    {
        Schema::table('eventos', function (Blueprint $table) {
            $table->dropColumn('certificado_solo_nombre');
        });
    }
};
