<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * QA visual encontró que `hasDonation`/`hasPromoCode` viven a nivel de
 * evento cuando deberían vivir a nivel de form_type (un evento con dos
 * tipos de formulario no puede permitir donación/promo en uno y no en el
 * otro) — ver brain/PLAN-MIGRACION-LARAVEL-BLADE-30072026.md (no, este es
 * de elascenso/event) / plan de esta sesión.
 *
 * Aditivo a propósito (Fase A): `eventos.hasDonation`/`hasPromoCode`
 * NO se tocan todavía — se agregan acá con backfill para que
 * `admin-eventos`/`elascenso/event`/`elascenso-blade` puedan migrar uno
 * por uno sin una ventana rota. La limpieza final (borrar de `eventos`)
 * es una migración separada, posterior, cuando los 3 repos ya lean de
 * `form_types`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('form_types', function (Blueprint $table) {
            // Mismo patrón que has_team/has_delivery (31/07) — snake_case,
            // boolean, default false.
            $table->boolean('has_donation')->default(false)->after('has_delivery');
            $table->boolean('has_promo_code')->default(false)->after('has_donation');
        });

        // Backfill: cada form_type hereda el valor que tenía su evento —
        // decisión confirmada con el usuario (no arrancar todo en false).
        // Probado antes contra event_testing, no directo contra la BD real
        // (ver feedback_dry_run_antes_de_correr_contra_bd_real en memoria).
        DB::statement('
            UPDATE form_types ft
            INNER JOIN eventos e ON e.id = ft.event_id
            SET ft.has_donation = e.hasDonation,
                ft.has_promo_code = e.hasPromoCode
        ');
    }

    public function down(): void
    {
        Schema::table('form_types', function (Blueprint $table) {
            $table->dropColumn(['has_donation', 'has_promo_code']);
        });
    }
};
