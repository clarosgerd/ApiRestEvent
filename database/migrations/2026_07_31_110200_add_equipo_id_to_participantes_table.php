<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('participantes', function (Blueprint $table) {
            $table->unsignedBigInteger('equipo_id')->nullable()->after('categoria');
            $table->index('equipo_id');
        });

        Schema::table('participantes', function (Blueprint $table) {
            $table->foreign('equipo_id')->references('id')->on('equipos')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('participantes', function (Blueprint $table) {
            $table->dropForeign(['equipo_id']);
            $table->dropIndex(['equipo_id']);
            $table->dropColumn('equipo_id');
        });
    }
};
