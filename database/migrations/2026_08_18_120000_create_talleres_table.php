<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Talleres de un congreso — agrupación opcional de sesiones_congreso con
 * modalidad REQUIRED/OPTIONAL y precio base. Las sesiones siguen viviendo
 * en `sesiones_congreso` con `taller_id` nullable (FK nullOnDelete) para
 * no romper el modelo existente (check-in, staff, ponentes, agenda).
 * Ver brain/PLAN-CONGRESOS-TALLERES-HORARIOS-IMPLEMENTACION.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('talleres', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evento_id')->constrained('eventos')->cascadeOnDelete();
            $table->string('nombre');
            $table->text('descripcion')->nullable();
            $table->enum('modalidad', ['REQUIRED', 'OPTIONAL']);
            $table->decimal('precio', 10, 2)->nullable();
            $table->unsignedInteger('orden')->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index(['evento_id', 'activo', 'orden'], 'talleres_evento_activo_orden_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('talleres');
    }
};