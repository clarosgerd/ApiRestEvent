<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('participantes', function (Blueprint $table) {
            // Nulos al momento de la inscripción — se asignan entre la
            // inscripción y la entrega de kits (numeración manual o bulk,
            // ver brain/PLAN-RESULTADOS-EQUIPOS-31072026.md §1).
            $table->string('numero_corredor')->nullable()->after('numero_documento');
            $table->string('chip')->nullable()->after('numero_corredor');
            $table->index('numero_corredor');
            $table->index('chip');
        });
    }

    public function down(): void
    {
        Schema::table('participantes', function (Blueprint $table) {
            $table->dropIndex(['numero_corredor']);
            $table->dropIndex(['chip']);
            $table->dropColumn(['numero_corredor', 'chip']);
        });
    }
};
