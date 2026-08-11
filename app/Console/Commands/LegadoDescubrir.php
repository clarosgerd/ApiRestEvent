<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ETL de datos históricos (2014-hoy) — paso 1: descubrimiento. Ver
 * elascenso/event/brain/ (sesión 10/08/2026) para el plan completo.
 *
 * Recorre cada schema `legado_*` (7 en local hoy — generaciones
 * sucesivas de la app vieja, cada una con una tabla por evento en vez de
 * una tabla `participantes` genérica), lista las tablas `inscrip*` con
 * filas reales, y genera un CSV borrador con una fila por tabla.
 *
 * La tabla `eventos` de esos schemas NO sirve como fuente de fecha real
 * (son 2 filas plantilla copiadas y pegadas, verificado a mano) — por eso
 * este comando infiere `fecha_min`/`fecha_max` del rango real de
 * `FECHA_INSCRIP` de cada tabla, y deja columnas vacías
 * (`nombre_evento_real`, `fecha_evento`, `lugar`, `tipo_evento`,
 * `categorias_map`) para que un humano las complete — no hay forma
 * confiable de inferir esto solo del nombre de la tabla (el mismo evento
 * real recurre entre schemas en años distintos, y los códigos numéricos
 * de categoría no tienen un lookup confiable).
 *
 * 100% lectura contra los schemas `legado_*` — no escribe nada en la BD
 * de ApiRestEvent. El CSV que genera es el input real de
 * `legado:importar` (siguiente paso, después de que el CSV se cure a mano).
 */
class LegadoDescubrir extends Command
{
    protected $signature = 'legado:descubrir
        {--patron=legado_% : patrón SQL LIKE para encontrar los schemas}
        {--salida=storage/app/legado-eventos-borrador.csv : ruta del CSV de salida, relativa a la raíz del proyecto}';

    protected $description = 'Recorre los schemas legado_* y genera un CSV borrador de eventos históricos para curar a mano.';

    public function handle(): int
    {
        $patron = $this->option('patron');

        config(['database.connections.legado.database' => '']);
        DB::purge('legado');

        $schemas = $this->listarSchemas($patron);

        if (empty($schemas)) {
            $this->error("No se encontró ningún schema que matchee '{$patron}'.");

            return self::FAILURE;
        }

        $this->info(count($schemas).' schema(s) encontrado(s): '.implode(', ', $schemas));

        $filas = [];
        foreach ($schemas as $schema) {
            $this->info("Escaneando {$schema}...");

            config(['database.connections.legado.database' => $schema]);
            DB::purge('legado');

            $tablas = DB::connection('legado')->select('
                SELECT TABLE_NAME, TABLE_ROWS
                FROM information_schema.TABLES
                WHERE TABLE_SCHEMA = ?
                  AND TABLE_NAME LIKE ?
                  AND TABLE_ROWS > 0
                ORDER BY TABLE_NAME
            ', [$schema, 'inscrip%']);

            foreach ($tablas as $t) {
                $filas[] = $this->filaParaTabla($schema, $t->TABLE_NAME, $t->TABLE_ROWS);
            }
        }

        if (empty($filas)) {
            $this->warn('No se encontró ninguna tabla inscrip* con filas en ningún schema.');

            return self::SUCCESS;
        }

        $this->escribirCsv($filas);

        $this->info(count($filas)." tablas encontradas en total. CSV borrador en: {$this->option('salida')}");
        $this->info('Completá a mano nombre_evento_real/fecha_evento/lugar/tipo_evento/categorias_map antes de correr legado:importar.');

        return self::SUCCESS;
    }

    /**
     * `SHOW DATABASES LIKE ?` con placeholder falla en este MariaDB
     * ("Syntax error... near '?'") — SHOW no soporta bind params en todos
     * los drivers/versiones. Se arma el literal a mano — `$patron` viene
     * de un flag de CLI para uso local del desarrollador, no de un
     * request HTTP, y de todas formas se escapa con `quote()`.
     */
    private function listarSchemas(string $patron): array
    {
        $pdo = DB::connection('legado')->getPdo();
        $literal = $pdo->quote($patron);
        $rows = DB::connection('legado')->select("SHOW DATABASES LIKE {$literal}");

        return array_map(fn ($row) => array_values((array) $row)[0], $rows);
    }

    private function filaParaTabla(string $schema, string $tabla, int $filas): array
    {
        $fechaMin = $fechaMax = null;

        try {
            if (Schema::connection('legado')->hasColumn($tabla, 'FECHA_INSCRIP')) {
                $rango = DB::connection('legado')->selectOne(
                    "SELECT MIN(FECHA_INSCRIP) AS min_fecha, MAX(FECHA_INSCRIP) AS max_fecha FROM `{$tabla}`"
                );
                $fechaMin = $rango->min_fecha;
                $fechaMax = $rango->max_fecha;
            }
        } catch (\Throwable $e) {
            // Tabla con estructura distinta a lo esperado (no tiene
            // FECHA_INSCRIP, o algo raro) — se deja vacío, no bloquea el
            // resto del descubrimiento. El humano completa fecha_evento
            // a mano en el CSV igual.
        }

        return [
            'schema' => $schema,
            'tabla' => $tabla,
            'filas' => $filas,
            'fecha_min' => $fechaMin,
            'fecha_max' => $fechaMax,
            'nombre_evento_real' => '',
            'fecha_evento' => '',
            'lugar' => '',
            'tipo_evento' => '',
            'categorias_map' => '',
        ];
    }

    private function escribirCsv(array $filas): void
    {
        $ruta = base_path($this->option('salida'));

        $handle = fopen($ruta, 'w');
        // BOM UTF-8: sin esto Excel en Windows (el destino habitual de
        // este archivo) muestra mal tildes/ñ en nombre_evento_real/lugar.
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, array_keys($filas[0]));
        foreach ($filas as $fila) {
            fputcsv($handle, $fila);
        }
        fclose($handle);
    }
}
