<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Estructura pedida por el usuario para que el software externo de
        // WhatsApp la lea (brain/PLAN-NOTIFICACIONES.md §2.4). `celular` se
        // ajusta de int(11) a unsignedBigInteger: un celular con código de
        // país (ej. 59171234567) desborda el tope de un INT de 32 bits.
        // Sin timestamps (created_at/updated_at) — usa `fecha` en su lugar,
        // igual que la estructura original.
        Schema::create('mensaje', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedBigInteger('celular')->nullable();
            $table->string('mensaje', 500)->nullable();
            $table->string('email', 100)->nullable();
            $table->string('tipo', 3);
            $table->integer('estado')->default(0);
            $table->timestamp('fecha')->useCurrent();
            $table->integer('prioridad')->default(5);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mensaje');
    }
};
