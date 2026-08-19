<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tarjeta de tipo de formulario simplificada (19/08/2026) — pedido:
 * mostrar solo nombre/descripción + un ícono o imagen. `imagen_url` es
 * opcional (nullable); si está vacía, el frontend sigue usando el emoji
 * de `icon` como hoy. Mismo criterio que `imagen_portada_url` en
 * `eventos`: URL pegada por el organizador, sin upload real de archivo
 * (no existe esa infraestructura en el proyecto todavía).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('form_types', function (Blueprint $table) {
            $table->string('imagen_url', 500)->nullable()->after('icon');
        });
    }

    public function down(): void
    {
        Schema::table('form_types', function (Blueprint $table) {
            $table->dropColumn('imagen_url');
        });
    }
};
