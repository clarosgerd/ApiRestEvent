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
        Schema::table('organizadores', function (Blueprint $table) {
            // Días de recordatorio configurables por contrato/convenio (PRD:
            // "las variables de días de los recordatorios deben ser
            // configuradas según contrato convenio organizador") — defaults
            // = los valores que pide el PRD.
            $table->unsignedSmallInteger('dias_recordatorio_pendiente_1')->default(30)->after('activo');
            $table->unsignedSmallInteger('dias_recordatorio_pendiente_2')->default(15)->after('dias_recordatorio_pendiente_1');
            $table->unsignedSmallInteger('dias_gracia_reversion')->default(3)->after('dias_recordatorio_pendiente_2');
            $table->unsignedSmallInteger('dias_recordatorio_kit')->default(5)->after('dias_gracia_reversion');
            $table->unsignedSmallInteger('dia_envio_marketing')->default(15)->after('dias_recordatorio_kit');

            // Canal de WhatsApp — mutuamente excluyente, por eso un solo
            // campo en vez de dos booleans independientes.
            $table->enum('whatsapp_canal', ['ninguno', 'openwa', 'externo'])->default('ninguno')->after('dia_envio_marketing');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('organizadores', function (Blueprint $table) {
            $table->dropColumn([
                'dias_recordatorio_pendiente_1',
                'dias_recordatorio_pendiente_2',
                'dias_gracia_reversion',
                'dias_recordatorio_kit',
                'dia_envio_marketing',
                'whatsapp_canal',
            ]);
        });
    }
};
