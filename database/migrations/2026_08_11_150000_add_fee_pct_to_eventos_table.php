<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cargo de servicio configurable por evento — sesión 11/08/2026. El 5%
 * estaba hardcodeado en 4 lugares (`elascenso/event/api/_registro_validacion.php`,
 * el preview en `index.php`, y el texto literal "(5%)" en 3 idiomas +
 * la plantilla de email/PDF `emails/partials/totales.blade.php`) — nada
 * de eso era configurable. `fee_pct` es una fracción (0.05 = 5%), mismo
 * criterio que `form_types.descuento_registrante_pct`, no un entero de
 * porcentaje.
 *
 * `default(0.05)` a propósito: todo evento existente sigue cobrando
 * exactamente 5% sin ningún cambio de comportamiento hasta que
 * super_admin (ver decisión de scoping) edite el valor de un evento
 * puntual.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('eventos', function (Blueprint $table) {
            $table->decimal('fee_pct', 5, 4)->default(0.05)->after('color_hex');
        });
    }

    public function down(): void
    {
        Schema::table('eventos', function (Blueprint $table) {
            $table->dropColumn('fee_pct');
        });
    }
};
