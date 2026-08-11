<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ETL de datos históricos (2014-hoy) — ver elascenso/event/brain/ (sesión
 * 10/08/2026). Clave de idempotencia del importador: guarda algo como
 * "legado_inscrito_base.inscrip_poli#123" (schema + tabla + id original
 * de la fila fuente). `unique` (permitiendo NULL en los registros
 * normales, no históricos) es lo que hace posible `updateOrCreate` por
 * esta columna — correr el importador dos veces sobre la misma fila
 * fuente actualiza en vez de duplicar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->string('origen_legado')->nullable()->unique()->after('pay_order_number');
        });
    }

    public function down(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->dropColumn('origen_legado');
        });
    }
};
