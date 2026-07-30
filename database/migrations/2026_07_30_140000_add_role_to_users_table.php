<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Nullable: la tabla `users` no tenía ningún rol hasta ahora (era
            // scaffolding de Laravel sin uso real). null = usuario sin
            // privilegios especiales; 'admin'/'superadmin' = recibe los
            // avisos operativos por correo (ver RecordatorioDashboardOrganizador).
            $table->string('role')->nullable()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};
