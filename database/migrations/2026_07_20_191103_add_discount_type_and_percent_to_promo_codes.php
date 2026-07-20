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
        Schema::table('promo_codes', function (Blueprint $table) {
            // 'fixed_price' (comportamiento actual: price = precio final a pagar) o
            // 'percentage' (price se ignora, se usa discount_percent en su lugar).
            // Default 'fixed_price' para que los ~150 promo codes ya sembrados sigan
            // funcionando exactamente igual sin tocar sus datos.
            $table->string('discount_type', 20)->default('fixed_price')->after('price');
            $table->decimal('discount_percent', 4, 3)->nullable()->after('discount_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('promo_codes', function (Blueprint $table) {
            $table->dropColumn(['discount_type', 'discount_percent']);
        });
    }
};
