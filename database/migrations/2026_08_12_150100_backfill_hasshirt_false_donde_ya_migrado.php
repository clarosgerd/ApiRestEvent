<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Completa lo que 2026_08_11_140400_migrate_polera_incluida_a_souvenirs.php
 * dejó a medias: esa migración creó el souvenir "Polera de kit" pero nunca
 * apagó el flag legacy `hasshirt` en form_types — por eso el frontend
 * seguía mostrando la sección fija "Con/Sin polera" AL MISMO TIEMPO que la
 * tarjeta de souvenir migrada (duplicado reportado 12/08).
 *
 * Idempotente: solo apaga `hasshirt` en los form_types que ya tienen su
 * souvenir "Polera de kit" equivalente — no toca form_types sin souvenir
 * migrado (no debería haber ninguno, pero por las dudas no se asume).
 *
 * `hasshirt`/`costo_polera` NO se borran (quedan de solo lectura, por
 * compatibilidad con inscripciones históricas que sí usaron el campo
 * legacy) — este es un apagado de flag, no un DROP.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('form_types')
            ->where('hasshirt', true)
            ->whereIn('id', function ($query) {
                $query->select('form_types_id')
                    ->from('souvenirs')
                    ->where('incluido', true)
                    ->where('name', 'Polera de kit');
            })
            ->update(['hasshirt' => false]);
    }

    public function down(): void
    {
        DB::table('form_types')
            ->where('hasshirt', false)
            ->whereIn('id', function ($query) {
                $query->select('form_types_id')
                    ->from('souvenirs')
                    ->where('incluido', true)
                    ->where('name', 'Polera de kit');
            })
            ->update(['hasshirt' => true]);
    }
};
