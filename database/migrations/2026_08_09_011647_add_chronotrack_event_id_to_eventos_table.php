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
        Schema::table('eventos', function (Blueprint $table) {
            // Id del evento en ChronoTrack (lo obtiene el organizador cuando
            // registra la carrera allá — no lo generamos ni administramos
            // nosotros). Nullable: la mayoría de los eventos no lo usan.
            // Ver brain/groovy-chasing-ladybug.md (sync de resultados).
            $table->string('chronotrack_event_id')->nullable()->after('color_hex');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('eventos', function (Blueprint $table) {
            $table->dropColumn('chronotrack_event_id');
        });
    }
};
