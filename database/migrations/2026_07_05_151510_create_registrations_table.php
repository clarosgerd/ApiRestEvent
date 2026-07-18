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
        Schema::create('registrations', function (Blueprint $table) {
            $table->id();
            $table->string('referencia', 30)->unique();
            $table->timestamp('fecha');
            $table->unsignedBigInteger('evento_id');
            $table->unsignedBigInteger('form_types_id');
            $table->unsignedBigInteger('persona_id')->nullable();
            $table->string('evento_nombre');
            $table->enum('tipo_pago', [
                'QR',
                'TRANSFERENCIA',
                'TIGO',
                'EFECTIVO'
            ]);
            $table->enum('pago_status', [
                'pending',
                'paid',
                'failed',
                'cancelled'
            ])->default('pending');
            $table->timestamps();
            $table->index('evento_id');
            $table->index('form_types_id');
            $table->index('fecha');
            $table->index('pago_status');
        });
       /*  Schema::table('registrations', function ($table) {
            $table->foreign('event_id')->references('id')->on('lara_eventos');
        });*/
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('registrations');
    }
};
