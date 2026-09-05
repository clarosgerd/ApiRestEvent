<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ingresos por ediciones de inscripciones pagadas (04/09/2026) — pedido
 * del usuario: el dashboard de ingresos no mostraba nada del dinero
 * cobrado al editar una inscripción ya pagada (agregar taller, subir
 * categoría). Investigado: la DIFERENCIA de precio de esas ediciones ya
 * queda reflejada en `inscripcion`/`souvenirs`/`talleres` (esas columnas
 * siempre guardan el estado ACTUAL, post-edición) — lo único que nunca
 * quedaba en ningún lado "de estado" era el cargo FIJO de edición
 * (`form_types.costo_edicion`), cobrado cada vez que se confirma una
 * edición pagada (ver `ActualizarInscripcionPagadaAction`).
 *
 * Esta columna acumula ese cargo fijo a través de todas las ediciones que
 * tuvo la inscripción — se puede sumar sin duplicar nada (a diferencia de
 * sumar el `costo_adicion` completo, que sí duplicaría la diferencia ya
 * contada en las columnas de arriba). Default 0: ediciones pasadas a este
 * cambio no se pueden reconstruir con precisión, quedan en 0 (limitación
 * conocida, no un bug).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registration_totals', function (Blueprint $table) {
            $table->decimal('costo_edicion_acumulado', 10, 2)->default(0)->after('grand_total');
        });
    }

    public function down(): void
    {
        Schema::table('registration_totals', function (Blueprint $table) {
            $table->dropColumn('costo_edicion_acumulado');
        });
    }
};
