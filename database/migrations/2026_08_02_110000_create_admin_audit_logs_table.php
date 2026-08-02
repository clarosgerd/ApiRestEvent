<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Nombre distinto a propósito de la tabla `audit_logs` ya existente
     * (esa trackea costo_adicion en ediciones de inscripciones pagadas,
     * `RegistrationService` — no relacionado, ver
     * brain/PLAN-PANEL-ADMIN-EVENTOS-02082026.md §1.3).
     */
    public function up(): void
    {
        Schema::create('admin_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_user_id')->nullable()->constrained('admin_users')->nullOnDelete();
            $table->string('accion');
            $table->string('entidad');
            $table->unsignedBigInteger('entidad_id');
            $table->unsignedBigInteger('evento_id')->nullable();
            $table->json('datos_antes')->nullable();
            $table->json('datos_despues')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('evento_id');
            $table->index(['entidad', 'entidad_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_audit_logs');
    }
};
