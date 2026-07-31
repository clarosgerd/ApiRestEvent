<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('form_types', function (Blueprint $table) {
            // Habilita la opción "delivery del kit" en el formulario de
            // inscripción (opt-in del participante, no automático) — ver
            // brain/PLAN-DELIVERY-31072026.md.
            $table->boolean('has_delivery')->default(false)->after('has_team');
        });
    }

    public function down(): void
    {
        Schema::table('form_types', function (Blueprint $table) {
            $table->dropColumn('has_delivery');
        });
    }
};
