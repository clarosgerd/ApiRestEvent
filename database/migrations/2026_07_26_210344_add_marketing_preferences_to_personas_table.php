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
        Schema::table('personas', function (Blueprint $table) {
            $table->boolean('acepta_marketing')->default(true)->after('token');
            $table->timestamp('ultimo_envio_marketing_at')->nullable()->after('acepta_marketing');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('personas', function (Blueprint $table) {
            $table->dropColumn(['acepta_marketing', 'ultimo_envio_marketing_at']);
        });
    }
};
