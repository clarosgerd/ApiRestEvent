<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ver 2026_08_14_120000_create_item_bodega_table.php. Columna aditiva,
 * nullable — cada `Souvenir` existente sigue funcionando exactamente
 * igual (item_bodega_id=null = standalone, igual que hoy, sin vínculo a
 * ningún catálogo). `nullOnDelete()`: si se borra el ítem de bodega, la
 * asignación (Souvenir) NO se borra en cascada — solo se desvincula y
 * sigue operando standalone con su propio stock/precio.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('souvenirs', function (Blueprint $table) {
            $table->foreignId('item_bodega_id')
                ->nullable()
                ->after('form_types_id')
                ->constrained('item_bodega')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('souvenirs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('item_bodega_id');
        });
    }
};
