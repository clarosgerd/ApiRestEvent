<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agenda_items', function (Blueprint $table) {
            // Nullable: null = item general, visible sin importar el tipo de
            // formulario elegido (ej. apertura, cierre). Si viene seteado, el
            // item solo aplica a ese form_type (ej. agenda distinta para
            // Individual vs Grupal dentro del mismo evento).
            $table->unsignedBigInteger('form_type_id')->nullable()->after('event_id');
            $table->foreign('form_type_id')->references('id')->on('form_types')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('agenda_items', function (Blueprint $table) {
            $table->dropForeign(['form_type_id']);
            $table->dropColumn('form_type_id');
        });
    }
};
