<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Interruptor por evento para el certificado automático de asistencia a
     * sesiones (07/10/2026) — pedido real del organizador. Hasta esta fecha
     * no había forma de apagar el envío (cron diario
     * `certificados:enviar-congreso` → EnviarCertificadosCongresoAction)
     * para un congreso puntual: la única opción era comentar la tarea
     * programada entera en routes/console.php, apagando TODOS los
     * congresos a la vez. Default true: ningún evento existente cambia de
     * comportamiento salvo que alguien apague el checkbox a mano — a
     * diferencia de certificado_solo_nombre (default false), este es un
     * apagador de algo que YA pasa hoy, no un flag que prende algo nuevo.
     */
    public function up(): void
    {
        Schema::table('eventos', function (Blueprint $table) {
            $table->boolean('certificado_asistencia_activo')->default(true)->after('certificado_solo_nombre');
        });
    }

    public function down(): void
    {
        Schema::table('eventos', function (Blueprint $table) {
            $table->dropColumn('certificado_asistencia_activo');
        });
    }
};
