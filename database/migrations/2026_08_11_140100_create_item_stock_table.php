<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kit, tallas, stock y lista de espera — ver
 * 2026_08_11_140000_add_kit_fields_to_souvenirs_table.php.
 *
 * Nombre de tabla nuevo ("item_stock", no "souvenir_stock") — ver la
 * decisión de terminología del PRD: todo lo nuevo se llama "item"/"ítem",
 * el modelo Souvenir existente no se toca. La columna `souvenir_id` sigue
 * llamándose así porque apunta a la tabla `souvenirs`, que no cambia de
 * nombre — es un detalle interno, no algo visible para el organizador.
 *
 * Una fila por combinación talla×sexo disponible de un ítem. Si el ítem
 * no requiere talla ni sexo (ej. una medalla), tiene una sola fila con
 * `talla=null, sexo=null`. `cantidad_total` NO es "cantidad disponible"
 * — es el total cargado; el disponible se calcula contando en vivo
 * (participantes con inscripción vigente que eligieron esa combinación),
 * mismo criterio que ya usa `form_types.cupo_total` — ver
 * RegistrationService::deactivateFormTypeIfCupoLleno(). Un souvenir sin
 * ninguna fila acá tiene "disponibilidad no controlada" (se comporta
 * como hoy, sin límite) — así los eventos existentes no se rompen al
 * desplegar esto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_stock', function (Blueprint $table) {
            $table->id();
            $table->foreignId('souvenir_id')->constrained('souvenirs')->cascadeOnDelete();
            $table->string('talla')->nullable();
            $table->enum('sexo', ['masculino', 'femenino', 'unisex'])->nullable();
            $table->unsignedInteger('cantidad_total');
            $table->timestamps();

            // MySQL trata NULL como distinto de NULL en un índice unique,
            // así que esto no evita 2 filas talla=null/sexo=null para el
            // mismo souvenir (item sin talla/sexo) — eso se valida en el
            // Request del controller, no acá.
            $table->unique(['souvenir_id', 'talla', 'sexo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_stock');
    }
};
