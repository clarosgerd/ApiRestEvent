<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promo_codes', function (Blueprint $table) {
            $table->boolean('usado')->default(false)->after('status');
            $table->unsignedBigInteger('registration_id')->nullable()->after('usado');
            $table->foreign('registration_id')->references('id')->on('registrations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('promo_codes', function (Blueprint $table) {
            $table->dropForeign(['registration_id']);
            $table->dropColumn(['usado', 'registration_id']);
        });
    }
};
