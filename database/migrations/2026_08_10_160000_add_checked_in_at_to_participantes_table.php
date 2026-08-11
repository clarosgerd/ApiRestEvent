<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Flujo de acreditación (check-in) escaneando el QR de referencia — ver
 * elascenso/event/brain/ (plan de sesión 10/08/2026). El QR ya existe
 * hace tiempo (`ReferenceQrService`) pero no tenía ningún consumidor;
 * esta columna es la base de datos para "quién ya se presentó".
 *
 * Sin backfill a propósito: todo arranca en NULL, es correcto — nadie
 * estaba acreditado antes de que exista esta columna.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('participantes', function (Blueprint $table) {
            $table->timestamp('checked_in_at')->nullable()->after('chip');
        });
    }

    public function down(): void
    {
        Schema::table('participantes', function (Blueprint $table) {
            $table->dropColumn('checked_in_at');
        });
    }
};
