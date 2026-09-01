<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ocultar Dirección/Ciudad/Teléfono/Alias por tipo de formulario
 * (01/09/2026) — ver PLAN-OCULTAR-CAMPOS-FORM-TYPE-01092026.md. Pedido
 * del usuario: "deberíamos colocar en form_type quitar esos campos de
 * dirección, ciudad, teléfono, etc. desde admin-eventos".
 *
 * Un solo array JSON en vez de una columna boolean por campo (mismo
 * criterio que `eventos.secciones_orden`) — más compacto, extensible sin
 * otra migración si se agregan más campos a la lista. Contacto de
 * emergencia queda fuera (ya tiene su propio flag,
 * `requiere_contacto_emergencia`). Default `[]` (nada oculto) — cero
 * cambio de comportamiento para los form_types existentes. No hace falta
 * relajar nada en la validación de participantes: `direccion`/`ciudad`/
 * `telefono`/`alias` ya son `nullable` desde el 31/08/2026 (ver
 * PLAN-GENERO-CATALOGO-CAMPOS-OPCIONALES-31082026.md).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('form_types', function (Blueprint $table) {
            $table->json('campos_ocultos')->nullable()->after('requiere_contacto_emergencia');
        });
    }

    public function down(): void
    {
        Schema::table('form_types', function (Blueprint $table) {
            $table->dropColumn('campos_ocultos');
        });
    }
};
