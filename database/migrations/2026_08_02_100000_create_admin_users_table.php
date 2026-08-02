<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_users', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('email')->unique();
            $table->string('password');
            // super_admin: ve/administra todos los eventos, gestiona usuarios.
            // admin: scoped a un único evento (evento_id abajo).
            $table->enum('rol', ['super_admin', 'admin']);
            $table->foreignId('evento_id')->nullable()->constrained('eventos')->nullOnDelete();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_users');
    }
};
