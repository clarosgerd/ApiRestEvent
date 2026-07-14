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
        Schema::create('registration_totals', function (Blueprint $table) {

            $table->id();

            $table->foreignId('registration_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->decimal('inscripcion',10,2)->default(0);
            $table->decimal('donacion',10,2)->default(0);
            $table->decimal('souvenirs',10,2)->default(0);
            $table->decimal('fee',10,2)->default(0);
            $table->decimal('descuento',10,2)->default(0);
            $table->decimal('grand_total',10,2)->default(0);

            $table->timestamps();

            $table->unique('registration_id');
        });

        
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('registration_totals');
    }
};
