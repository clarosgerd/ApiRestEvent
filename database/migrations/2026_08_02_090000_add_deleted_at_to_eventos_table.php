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
            // Soft delete: DELETE /event/{id} ahora hace algo (antes el
            // controlador estaba vacío) — se prefiere soft delete a borrado
            // físico para poder auditar/recuperar un evento borrado por error.
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('eventos', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
