<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reporte de poleras (03/09/2026) — bug real reportado por el usuario: el
 * "Reporte de poleras" (Sexo/Talla/Cantidad) leía `participantes.polera`,
 * un campo legacy que queda siempre en el string 'No shirt' para eventos
 * cuya polera está modelada como un souvenir normal (con
 * `requiere_talla=true`) — las tallas reales que el participante eligió
 * viven en `souvenir_participantes.talla`.
 *
 * No hay forma hoy de saber CUÁL souvenir de un form_type es "la polera"
 * para el reporte (un evento puede tener varios ítems con talla) — se
 * agrega este flag explícito, opt-in por ítem, para que el organizador lo
 * marque a mano en admin-eventos. Ver ReporteInscritosData::agruparPoleras().
 *
 * Default `false` a propósito — ningún souvenir existente cambia de
 * comportamiento hasta que el organizador lo marque.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('souvenirs', function (Blueprint $table) {
            $table->boolean('es_polera')->default(false)->after('texto_promocional');
        });
    }

    public function down(): void
    {
        Schema::table('souvenirs', function (Blueprint $table) {
            $table->dropColumn('es_polera');
        });
    }
};
