<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('form_types', function (Blueprint $table) {
            // Distinto de `permite_inscripcion_grupal` (que agrupa varios
            // participantes en una sola transacción de registro): esto es
            // una inscripción INDIVIDUAL que además declara pertenencia a
            // un equipo precargado por el organizador — ver
            // brain/PLAN-RESULTADOS-EQUIPOS-31072026.md §3.
            $table->boolean('has_team')->default(false)->after('permite_inscripcion_grupal');
        });
    }

    public function down(): void
    {
        Schema::table('form_types', function (Blueprint $table) {
            $table->dropColumn('has_team');
        });
    }
};
