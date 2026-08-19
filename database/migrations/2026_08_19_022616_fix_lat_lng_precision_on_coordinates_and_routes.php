<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Bug real (19/08/2026, pedido "el mapa no muestra la ubicación con
 * exactitud"). Las migraciones originales de `coordinates`/`routes`
 * declaraban `$table->float('lat', 10, 6)` — firma de Laravel <10
 * (total, decimales). En este proyecto (Laravel 12) `Blueprint::float()`
 * cambió a `float($column, $precision = 53)`: el `10` pasó a
 * interpretarse como el nuevo `$precision`, y el `6` (decimales) se
 * ignoró en silencio. Resultado real en BD (verificado con `SHOW CREATE
 * TABLE`): columna `float` de precisión simple (MySQL/MariaDB, ~7 dígitos
 * significativos totales), no `FLOAT(10,6)`. Para una lat/lng con 6
 * decimales (11cm de precisión GPS) eso alcanza a perder los últimos
 * dígitos por redondeo binario.
 *
 * Fix: `DECIMAL(10,6)` — precisión exacta (punto fijo, no binaria), sin
 * el problema de FLOAT/DOUBLE. `MODIFY COLUMN` en crudo porque este
 * proyecto no tiene `doctrine/dbal` instalado (requisito de
 * Schema::table()->lat()->change() en versiones anteriores de Laravel).
 *
 * Los valores YA guardados con la columna float no se pueden recuperar
 * con más precisión de la que ya perdieron al escribirse — este fix
 * evita que seguir escribiendo/leyendo pierda más precisión de acá en
 * adelante. Si un evento puntual quedó con coordenadas visiblemente mal,
 * hay que volver a cargarlas desde admin-eventos.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE coordinates MODIFY lat DECIMAL(10,6) NOT NULL, MODIFY lng DECIMAL(10,6) NOT NULL');
        DB::statement('ALTER TABLE routes MODIFY lat DECIMAL(10,6) NOT NULL, MODIFY lng DECIMAL(10,6) NOT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE coordinates MODIFY lat FLOAT NOT NULL, MODIFY lng FLOAT NOT NULL');
        DB::statement('ALTER TABLE routes MODIFY lat FLOAT NOT NULL, MODIFY lng FLOAT NOT NULL');
    }
};
