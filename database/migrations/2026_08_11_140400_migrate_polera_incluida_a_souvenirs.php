<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Kit, tallas, stock y lista de espera — ver
 * 2026_08_11_140300_create_lista_espera_table.php.
 *
 * Migración de DATOS, no de schema: por cada `form_types` con
 * `hasshirt=true`, crea la fila `souvenirs` equivalente
 * (`incluido=true`) para que la polera del kit quede representada en el
 * mismo modelo que los ítems opcionales — ver la decisión de
 * unificación del PRD. `hasshirt` tiene `default(true)` desde que se
 * creó la tabla, así que esto corre para la gran mayoría de los
 * form_types existentes — es intencional y, a la vez, inofensivo: no se
 * crea ninguna fila en `item_stock` para estos ítems migrados, así que
 * quedan con "disponibilidad no controlada" (ver PRD sección 2) y no
 * cambian nada visible hasta que el organizador cargue stock o una foto
 * a mano desde el panel.
 *
 * Idempotente: si ya existe un souvenir incluido para ese form_type
 * (ej. si esta migración se corre dos veces por error contra la misma
 * BD), no duplica la fila.
 *
 * `hasshirt`/`costo_polera`/`requiere_talla` en `form_types` NO se
 * borran (quedan de solo lectura, por compatibilidad) — dejan de ser la
 * fuente de verdad pero no se tocan acá.
 */
return new class extends Migration
{
    public function up(): void
    {
        $formTypes = DB::table('form_types')
            ->where('hasshirt', true)
            ->get(['id', 'costo_polera']);

        foreach ($formTypes as $formType) {
            $yaExiste = DB::table('souvenirs')
                ->where('form_types_id', $formType->id)
                ->where('incluido', true)
                ->exists();

            if ($yaExiste) {
                continue;
            }

            DB::table('souvenirs')->insert([
                'form_types_id'  => $formType->id,
                'name'           => 'Polera de kit',
                'icon'           => '👕',
                'price'          => $formType->costo_polera ?? 0,
                'incluido'       => true,
                'foto_url'       => null,
                'requiere_talla' => true,
                'requiere_sexo'  => false,
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('souvenirs')
            ->where('incluido', true)
            ->where('name', 'Polera de kit')
            ->delete();
    }
};
