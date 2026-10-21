<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Souvenir obligatorio (22/10/2026, pedido real del usuario — revisando el
 * evento 90021 "PARAPETÍ INDOMABLE") — antes el único "opt-out por
 * defecto" era `incluido=true` (pre-marcado), pero el participante siempre
 * podía destildarlo igual (ver el `onclick` de `.souvenir-card` en
 * elascenso/event/index.php, que siempre hace toggle sin excepción). El
 * único mecanismo que de verdad fuerza un souvenir es "invisible"
 * (`visible_participante=false`), pero ese no admite `requiere_talla`, así
 * que no sirve para una polera.
 *
 * `obligatorio` es independiente de `incluido`/`visible_participante` — un
 * souvenir obligatorio sigue siendo VISIBLE (el participante elige su
 * talla/sexo normal) pero no se puede sacar del carrito. La enforcement
 * real de "la inscripción tiene que traerlo" vive del lado del proxy
 * (elascenso/event/api/_registro_validacion.php), no acá — mismo criterio
 * que el resto de souvenirs, que ApiRestEvent nunca revalida de forma
 * independiente (a diferencia de categorías).
 *
 * Default `false` — ningún souvenir existente cambia de comportamiento
 * hasta que el organizador lo marque.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('souvenirs', function (Blueprint $table) {
            $table->boolean('obligatorio')->default(false)->after('es_polera');
        });
    }

    public function down(): void
    {
        Schema::table('souvenirs', function (Blueprint $table) {
            $table->dropColumn('obligatorio');
        });
    }
};
