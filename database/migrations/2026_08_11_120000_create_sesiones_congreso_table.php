<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agenda y sesiones de congreso — ver
 * ApiRestEvent/brain/api_rest_event/PRD-Agenda-sessiones-onlycongresos.md
 * y elascenso/event/brain/ (sesión 11/08/2026).
 *
 * Distinto de `agenda_items` (cronograma de exhibición, sin cupo ni
 * inscripción) — `agenda_item_id` es un vínculo opcional al ítem visual
 * si el organizador ya lo cargó, no una relación obligatoria.
 *
 * `requiere_inscripcion` se guarda para la fase futura de selección de
 * sesiones durante el registro del participante — todavía sin lógica
 * propia en esta ronda (solo config, no se usa para bloquear nada).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sesiones_congreso', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evento_id')->constrained('eventos')->cascadeOnDelete();
            // agenda_items.id es `int unsigned` (no bigint, a diferencia
            // del resto de las tablas) — foreignId() asume bigint y
            // rompe la FK (errno 150), por eso se declara el tipo exacto
            // a mano en vez de usar el helper.
            $table->unsignedInteger('agenda_item_id')->nullable();
            $table->foreign('agenda_item_id')->references('id')->on('agenda_items')->nullOnDelete();
            $table->string('titulo');
            $table->string('ponente')->nullable();
            $table->string('ponente_cargo')->nullable();
            $table->string('sala')->nullable();
            $table->date('fecha');
            $table->time('hora_inicio');
            $table->time('hora_fin');
            $table->unsignedInteger('cupo')->nullable();
            $table->boolean('requiere_inscripcion')->default(false);
            $table->boolean('activa')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sesiones_congreso');
    }
};
