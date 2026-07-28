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
        Schema::table('form_types', function (Blueprint $table) {
            // Precio del add-on "con polera" — antes vivía hardcodeado en 30
            // en el frontend (event/index.php) sin validación server-side.
            // Default 30 preserva el comportamiento actual hasta que se
            // edite explícitamente por form_type.
            $table->decimal('costo_polera', 10, 2)->default(30.00)->after('hasshirt');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('form_types', function (Blueprint $table) {
            $table->dropColumn('costo_polera');
        });
    }
};
