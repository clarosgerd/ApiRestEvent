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
        Schema::create('participantes', function (Blueprint $table) {
            $table->id();
            
            $table->unsignedBigInteger('registration_id');
           // $table->foreign('registration_id')->references('id')->on('registrations')->cascadeOnDelete();

          
          /* $table->foreignId('registration_id')
                ->constrained()
                ->cascadeOnDelete();*/


            $table->string('nombre');
            $table->string('apellido');
            $table->string('alias')->nullable();

            $table->enum('genero', [
                'Masculino',
                'Femenino',
                'Otro'
            ]);

            $table->string('tipo_documento',30);
            $table->string('numero_documento',50);
            $table->string('polera')->nullable();
            $table->decimal('precio_polera',10,2)->default(0);
            $table->date('fecha_nacimiento');
            $table->unsignedInteger('edad');
            $table->string('correo');
            $table->string('direccion');
            $table->string('ciudad');
            $table->string('telefono',30);
            $table->unsignedBigInteger('categoria');
            $table->decimal('precio_categoria',10,2)->default(0);
            $table->decimal('donacion',10,2)->default(0);
            $table->decimal('promo_descuento',10,2)->default(0);
            $table->string('promo_codigo')->nullable();
            $table->decimal('subtotal',10,2);
            $table->timestamps();
            $table->index('registration_id');
            $table->index('numero_documento');
            $table->index('correo');
            $table->index('categoria');
        });

        /*Schema::table('participantes', function ($table) {
            $table->foreign('registration_id')->references('id')->on('registrations');
        });*/
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('participantes');
    }
};
