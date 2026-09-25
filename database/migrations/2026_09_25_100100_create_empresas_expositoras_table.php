<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SmartStand (25/09/2026) — cuenta de una empresa expositora en UN evento.
 * Actor con login propio (guard `expositores`), mismo molde que `clubes`.
 *
 * `registration_id` (único, nullable) es la inscripción de autoservicio que
 * originó la cuenta: su UNIQUE es lo que hace idempotente el alta automática
 * al confirmarse el pago. Null = alta manual del organizador.
 *
 * Tipos de FK verificados: eventos/registrations son BIGINT UNSIGNED
 * (foreignId ok), pero categories.id es INT UNSIGNED (increments) — con
 * foreignId() la FK rompería (errno 150).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('empresas_expositoras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evento_id')->constrained('eventos')->cascadeOnDelete();
            $table->foreignId('registration_id')->nullable()->unique()->constrained('registrations')->nullOnDelete();
            $table->string('nombre');
            $table->string('email');
            $table->string('password');
            $table->unsignedInteger('categoria_id')->nullable();
            $table->foreign('categoria_id')->references('id')->on('categories')->nullOnDelete();
            $table->string('stand')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamp('credenciales_enviadas_at')->nullable();
            $table->timestamps();

            $table->unique(['evento_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('empresas_expositoras');
    }
};
