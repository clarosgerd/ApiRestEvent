<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Souvenirs invisibles para el participante (22/08/2026) — souvenirs
     * de un form_type que se asignan automáticamente a todos sus
     * participantes al registrarse, sin pasar nunca por el formulario de
     * inscripción (el participante nunca los elige ni sabe que existen).
     * Pensado para el retiro en sitio: hoy la lista de souvenirs a
     * entregar en elascenso/delivery viene de lo que el participante
     * seleccionó/vio en el formulario, y no hay forma de que el
     * organizador incluya algo ahí sin exponerlo también al público.
     *
     * Default `true` (visible) para no cambiar el comportamiento de
     * ningún souvenir existente — la columna nace aditiva.
     */
    public function up(): void
    {
        Schema::table('souvenirs', function (Blueprint $table) {
            $table->boolean('visible_participante')->default(true)->after('incluido');
        });
    }

    public function down(): void
    {
        Schema::table('souvenirs', function (Blueprint $table) {
            $table->dropColumn('visible_participante');
        });
    }
};
