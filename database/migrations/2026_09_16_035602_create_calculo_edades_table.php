<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Aviso de numeración vs. género/edad real en entrega de kit (16/09/2026).
 * Respalda `categories.calculo_edad_id`, columna que ya existía desde la
 * migración original de `categories` (fillable + validada) pero sin ningún
 * catálogo real detrás — hoy un entero suelto sin significado. Mismo shape
 * que `generos`/`sexos` (catálogo simple, sin timestamps).
 *
 * Las 3 filas son las 3 formas de calcular la edad de un participante que
 * confirmó el usuario: al inscribirse (ya guardada en `participantes.edad`,
 * no se recalcula), al día del evento (`eventos.fecha_inicio` menos
 * `fecha_nacimiento`), y la que cumple en el año del evento ("edad de
 * pista", año del evento menos año de nacimiento — común en atletismo). Ver
 * App\Support\CalculoEdadResolver.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calculo_edades', function (Blueprint $table) {
            $table->tinyIncrements('id');
            $table->string('nombre', 80);
            $table->boolean('activo')->default(true);
        });

        DB::table('calculo_edades')->insert([
            ['nombre' => 'Edad al inscribirse', 'activo' => true],
            ['nombre' => 'Edad al día del evento', 'activo' => true],
            ['nombre' => 'Edad que cumple en el año del evento', 'activo' => true],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('calculo_edades');
    }
};
