<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Componente nuevo en `registration_totals` para la suma de talleres
 * seleccionados por los participantes. Default 0 garantiza que eventos
 * sin talleres (legacy) no cambien su comportamiento. Ver
 * brain/PLAN-CONGRESOS-TALLERES-HORARIOS-IMPLEMENTACION.md.
 *
 * Fórmula nueva del grand_total:
 *   inscripcion + donacion + souvenirs + talleres + fee - descuento - descuento_registrante
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registration_totals', function (Blueprint $table) {
            $table->decimal('talleres', 10, 2)->default(0)->after('souvenirs');
        });
    }

    public function down(): void
    {
        Schema::table('registration_totals', function (Blueprint $table) {
            $table->dropColumn('talleres');
        });
    }
};