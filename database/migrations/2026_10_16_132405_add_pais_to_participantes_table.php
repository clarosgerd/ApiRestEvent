<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Pais (16/10/2026, pedido del usuario) — mismo criterio que
        // direccion/ciudad/telefono: string NOT NULL con default '' (no
        // `nullable()`) para no necesitar un valor "neutro" aparte cuando
        // un form_type lo oculta vía campos_ocultos; el default cubre las
        // filas existentes al agregar la columna.
        Schema::table('participantes', function (Blueprint $table) {
            $table->string('pais')->default('')->after('ciudad');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('participantes', function (Blueprint $table) {
            $table->dropColumn('pais');
        });
    }
};
