<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reserva de cupo con seña (21/10/2026, pedido real del usuario — ver
 * afiche "PARAPETÍ INDOMABLE") — mismo patrón que `price_usd`: nullable,
 * null = esta categoría no ofrece reserva (comportamiento actual intacto).
 * Ver App\Support\PrecioVigenteData.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->decimal('price_reserva', 10, 2)->nullable()->after('price_usd');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('price_reserva');
        });
    }
};
