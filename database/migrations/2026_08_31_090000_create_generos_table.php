<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo de género de participante (31/08/2026) — ver
 * PLAN-GENERO-CATALOGO-CAMPOS-OPCIONALES-31082026.md. Antes el `<select>`
 * de género en elascenso/event estaba hardcodeado en el HTML con 4 opciones
 * (Masculino/Femenino/Non-binary/Prefer not to say), pero
 * `participantes.genero` es un ENUM que solo acepta
 * ('Masculino','Femenino','Otro') — las otras 2 opciones rompían el INSERT
 * si alguien las elegía (bug real, preexistente). Este catálogo NO es
 * `sexos` (esa tabla es de `categories.sexo_id`, un concepto distinto,
 * documentado como tal en `Sexo`/`SexoController` — no se toca acá).
 *
 * A propósito, mismo shape que `sexos`. Se seedean EXACTAMENTE los 3
 * valores que ya acepta el ENUM — no se migra ese ENUM en este paquete
 * (decisión explícita del usuario). Si se agrega desde el admin un género
 * con `nombre` distinto de estos 3, el INSERT en `participantes` va a
 * fallar — usar `activo=false` para ocultar una opción sin agregar una
 * nueva es la única forma segura de editar este catálogo hasta que se
 * migre el ENUM (fuera de alcance).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('generos', function (Blueprint $table) {
            $table->tinyIncrements('id');
            $table->string('nombre', 80);
            $table->boolean('activo')->default(true);
        });

        DB::table('generos')->insert([
            ['nombre' => 'Masculino', 'activo' => true],
            ['nombre' => 'Femenino', 'activo' => true],
            ['nombre' => 'Otro', 'activo' => true],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('generos');
    }
};
