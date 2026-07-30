<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agenda_items', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->unsignedBigInteger('event_id');
            // Nullable: si no viene, el frontend asume evento.fecha_inicio (evento de un solo día).
            $table->date('fecha')->nullable();
            $table->time('hora_inicio');
            $table->time('hora_fin')->nullable();
            $table->string('titulo', 255);
            $table->text('descripcion')->nullable();
            // Solo aplica a sesiones de congreso/charla — queda null en items tipo carrera
            // (entrega de kits, calentamiento, premiación, after party).
            $table->string('ponente', 255)->nullable();
            $table->string('ponente_cargo', 255)->nullable();
            $table->string('sala', 255)->nullable();
            $table->string('icono', 10)->nullable(); // emoji, mismo patrón que tipos_evento.icono / form_types.icon
            $table->integer('orden')->nullable();
            $table->timestamps();
        });

        Schema::table('agenda_items', function (Blueprint $table) {
            $table->foreign('event_id')->references('id')->on('eventos')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agenda_items');
    }
};
