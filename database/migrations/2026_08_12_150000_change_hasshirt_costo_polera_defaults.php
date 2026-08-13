<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Deprecación del campo legacy de camiseta (hasshirt/costo_polera) — ver
 * elascenso/event/brain/ para el checklist de despliegue asociado.
 *
 * Desde la migración 2026_08_11_140400 (kit/tallas/stock), la polera de un
 * form_type se representa como un souvenir `incluido=true` — el campo
 * hasshirt/costo_polera queda de solo lectura por compatibilidad con
 * inscripciones históricas, pero NO debe seguir siendo el default para
 * form_types nuevos (ni vía FormTypeService::create(), que usa `??`, ni vía
 * EventoService::createFormTypes(), que no setea estos campos y hoy hereda
 * el default de columna). Cambiar el default acá cubre ambos paths con un
 * solo punto de verdad.
 *
 * Usa DB::statement() en vez de Schema::table()->change() porque este
 * proyecto no tiene doctrine/dbal instalado (requerido por ->change()).
 * No se borran las columnas — solo cambia su default para altas nuevas.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE form_types ALTER hasshirt SET DEFAULT 0');
        DB::statement('ALTER TABLE form_types ALTER costo_polera SET DEFAULT 0.00');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE form_types ALTER hasshirt SET DEFAULT 1');
        DB::statement('ALTER TABLE form_types ALTER costo_polera SET DEFAULT 30.00');
    }
};
