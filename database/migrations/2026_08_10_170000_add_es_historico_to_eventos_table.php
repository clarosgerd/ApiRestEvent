<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ETL de datos históricos (2014-hoy) — ver elascenso/event/brain/ (sesión
 * 10/08/2026). Distingue un evento migrado del sistema viejo de uno
 * creado por el organizador en la app nueva. No cambia ninguna pantalla
 * existente por sí solo — un evento histórico se ve igual que cualquier
 * otro en las listas/Mis Resultados, que es justo lo pedido.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('eventos', function (Blueprint $table) {
            $table->boolean('es_historico')->default(false)->after('destacado');
        });
    }

    public function down(): void
    {
        Schema::table('eventos', function (Blueprint $table) {
            $table->dropColumn('es_historico');
        });
    }
};
