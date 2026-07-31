<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('participantes', function (Blueprint $table) {
            $table->boolean('quiere_delivery')->default(false)->after('equipo_id');
            // Nulo mientras quiere_delivery=false. Cuando el participante
            // opta por delivery, arranca en 'pendiente' y el organizador
            // (o la empresa de delivery, vía el dashboard firmado) lo va
            // avanzando: pendiente -> confirmado -> entregado, o cancelado.
            $table->string('estado_delivery')->nullable()->after('quiere_delivery');
            $table->index('estado_delivery');
        });
    }

    public function down(): void
    {
        Schema::table('participantes', function (Blueprint $table) {
            $table->dropIndex(['estado_delivery']);
            $table->dropColumn(['quiere_delivery', 'estado_delivery']);
        });
    }
};
