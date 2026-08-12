<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kit, tallas, stock y lista de espera — ver
 * 2026_08_11_140100_create_item_stock_table.php.
 *
 * Hoy `souvenir_participantes` no guarda qué talla/sexo eligió el
 * participante — no hay manera de saber qué fila de `item_stock` se
 * consumió. Nullable: souvenirs sin talla/sexo (o inscripciones viejas,
 * anteriores a esta migración) simplemente quedan null en ambas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('souvenir_participantes', function (Blueprint $table) {
            $table->string('talla')->nullable()->after('souvenir_id');
            $table->string('sexo')->nullable()->after('talla');
        });
    }

    public function down(): void
    {
        Schema::table('souvenir_participantes', function (Blueprint $table) {
            $table->dropColumn(['talla', 'sexo']);
        });
    }
};
