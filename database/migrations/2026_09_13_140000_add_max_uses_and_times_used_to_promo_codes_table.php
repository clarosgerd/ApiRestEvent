<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Promo code multi-uso (13/09/2026) — ver plan en la sesión de Claude Code
 * (brain interno no versionado en este repo para esta feature). `usado`
 * pasa a significar "agotado" (`times_used >= max_uses`), no "usado alguna
 * vez" — se sigue manteniendo físicamente y en sync para que los 3
 * lectores actuales (admin-eventos, elascenso/event, PromoCodeController::
 * promoCode()) no necesiten ningún cambio de código.
 *
 * `default(1)` en `max_uses` es lo que hace que los ~150 códigos ya
 * existentes sigan siendo de un solo uso sin ningún script de backfill
 * sobre esta tabla — puramente aditivo, cero cambio de comportamiento para
 * datos existentes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promo_codes', function (Blueprint $table) {
            $table->unsignedInteger('max_uses')->default(1)->after('registration_id');
            $table->unsignedInteger('times_used')->default(0)->after('max_uses');
        });
    }

    public function down(): void
    {
        Schema::table('promo_codes', function (Blueprint $table) {
            $table->dropColumn(['max_uses', 'times_used']);
        });
    }
};
