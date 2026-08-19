<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bandera configurable por evento para decidir si los talleres suman al
 * total del participante. Si `talleres_con_costo = false`, los talleres
 * siguen siendo seleccionables (con cupo/conflicto/requeridos) pero no
 * suman al grand_total. Ver T1–T7 en
 * brain/PLAN-CONGRESOS-TALLERES-HORARIOS-IMPLEMENTACION.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('eventos', function (Blueprint $table) {
            $table->boolean('talleres_con_costo')->default(false)->after('hasPromoCode');
        });
    }

    public function down(): void
    {
        Schema::table('eventos', function (Blueprint $table) {
            $table->dropColumn('talleres_con_costo');
        });
    }
};