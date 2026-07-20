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
        Schema::table('registration_totals', function (Blueprint $table) {
            $table->decimal('descuento_registrante', 10, 2)->default(0)->after('descuento');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('registration_totals', function (Blueprint $table) {
            $table->dropColumn('descuento_registrante');
        });
    }
};
