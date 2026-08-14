<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bodega de stock por evento — ver
 * ApiRestEvent/brain/api_rest_event/PLAN-BODEGA-STOCK-EVENTO-14082026.md.
 * Sigue de PRD-kit-tallas-stock-lista-espera.md (11/08); ese feature ya
 * daba a cada `Souvenir` (por form_type) su propio `item_stock`
 * independiente — "cupos separados por form_type" ya era el
 * comportamiento real. Lo que faltaba era una capa de catálogo
 * compartido para que varios `Souvenir` (uno por cada form_type que
 * ofrece el mismo ítem físico) se puedan identificar como "el mismo
 * ítem" sin re-tipear nombre/ícono/foto cada vez, y una pantalla para
 * verlo todo junto.
 *
 * `item_bodega` es el catálogo del EVENTO (no compartido entre eventos
 * del mismo organizador — decisión explícita del usuario). A propósito
 * NO tiene `price` ni cantidad de stock propia: eso vive en cada
 * `Souvenir` (la asignación puntual a un form_type), para no mezclar
 * "qué es el ítem" con "en qué términos comerciales lo ofrece cada
 * form_type".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_bodega', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evento_id')->constrained('eventos')->cascadeOnDelete();
            $table->string('nombre');
            $table->string('icon')->nullable();
            $table->string('foto_url')->nullable();
            $table->boolean('requiere_talla')->default(false);
            $table->boolean('requiere_sexo')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_bodega');
    }
};
