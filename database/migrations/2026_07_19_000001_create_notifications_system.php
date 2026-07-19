<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->json('notifications')->nullable()->after('remember_token');
        });

        Schema::table('registrations', function (Blueprint $table) {
            $table->json('notifications')->nullable()->after('pago_status');
            $table->timestamp('pending_payment_notified_at')->nullable()->after('notifications');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('notifications');
        });

        Schema::table('registrations', function (Blueprint $table) {
            $table->dropColumn(['notifications', 'pending_payment_notified_at']);
        });
    }
};
