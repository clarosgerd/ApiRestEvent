<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cargo de servicio por souvenir individual (01/09/2026) — el cargo de
     * servicio ya se podía calcular sobre inscripción + talleres
     * (fee_incluye_talleres, por evento), pero souvenirs siempre quedaban
     * afuera sin ninguna opción. Pedido del usuario: algunos souvenirs con
     * costo real (ej. una polera) sí deberían sumar al cargo, otros no
     * (ej. una medalla incluida) — un toggle por evento no alcanza, hace
     * falta uno por ítem.
     *
     * Default `false` (no aplica) para no cambiar el comportamiento de
     * ningún souvenir existente — la columna nace aditiva, opt-in
     * explícito por ítem.
     */
    public function up(): void
    {
        Schema::table('souvenirs', function (Blueprint $table) {
            $table->boolean('aplica_cargo_servicio')->default(false)->after('visible_participante');
        });
    }

    public function down(): void
    {
        Schema::table('souvenirs', function (Blueprint $table) {
            $table->dropColumn('aplica_cargo_servicio');
        });
    }
};
