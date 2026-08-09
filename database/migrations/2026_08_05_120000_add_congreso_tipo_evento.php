<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * `tipos_evento` nació con 6 filas, todas disciplinas deportivas (Carrera
 * de Ruta, Trail Running, Ciclismo, Caminata, Triatlón, Natación) —
 * ninguna representa un evento no deportivo (congreso, taller, etc.).
 * `eventos.tipo_evento_id`/`subtipo_evento_id` son NOT NULL, así que un
 * evento de congreso necesita igual algún valor ahí — sin esta fila no
 * hay forma de distinguir "es un evento deportivo real" de "es un
 * congreso" a nivel de evento (antes de esto, `EventoService::create()`
 * hardcodeaba `tipo_evento_id=1` para todos, deportivos o no). Ver
 * brain/PLAN-ENDPOINT-CONSUMO-05082026.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        $tipoId = DB::table('tipos_evento')->insertGetId([
            'nombre' => 'Congreso / No aplica',
            'icono' => '🏛️',
            'activo' => true,
        ]);

        DB::table('subtipos_evento')->insert([
            'tipo_evento_id' => $tipoId,
            'nombre' => 'General',
            'activo' => true,
        ]);
    }

    public function down(): void
    {
        $tipoId = DB::table('tipos_evento')->where('nombre', 'Congreso / No aplica')->value('id');

        if ($tipoId) {
            DB::table('subtipos_evento')->where('tipo_evento_id', $tipoId)->delete();
            DB::table('tipos_evento')->where('id', $tipoId)->delete();
        }
    }
};
