<?php

namespace App\Console\Commands;

use App\Models\Evento;
use App\Models\Participante;
use App\Models\Registration;
use App\Models\RegistrationTotal;
use App\Services\RegistrationService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * ETL de datos históricos (2014-hoy) — paso 2: importación. Ver
 * `LegadoDescubrir` (paso 1) y `elascenso/event/brain/` (sesión
 * 10/08/2026) para el plan original completo — este comando retoma un
 * alcance más chico y concreto (17/09/2026): congresos puntuales cuyo
 * evento destino YA existe con sus categorías reales creadas a mano (no
 * hace falta el `categorias_map` del CSV del paso 1).
 *
 * Lee `inscrip`/`precios` de un schema legado (conexión `legado`,
 * reapuntada en runtime igual que `LegadoDescubrir`), solo filas
 * `ESTADO_INSCRIP='PAG'`, y crea/actualiza Registration+Participante+
 * RegistrationTotal en el evento destino. Idempotente por
 * `registrations.origen_legado` ("{schema}.inscrip#{INSCRIP}", único en
 * BD) — correr dos veces con los mismos parámetros actualiza en vez de
 * duplicar.
 *
 * La categoría se resuelve en runtime: `inscrip.PRECIO_COD` →
 * `precios.PRECIO` → `precios.TEXTO` → matchea por nombre (sin
 * mayúsculas/espacios) contra `categories.name` del evento destino. Sin
 * match → fila omitida (reportada en el resumen, no bloquea el resto).
 *
 * `--dry-run`: no escribe nada, solo cuenta y muestra el resumen.
 */
class LegadoImportar extends Command
{
    protected $signature = 'legado:importar
        {schema : Nombre del schema legado (ej. legado_inscrito_db)}
        {evento : ID del evento destino, ya debe existir con sus categorías creadas}
        {--dry-run : Solo cuenta y muestra el resumen, no escribe nada}';

    protected $description = 'Importa inscripciones PAG de un schema legado hacia un evento destino existente.';

    public function handle(RegistrationService $registrationService): int
    {
        $schema = $this->argument('schema');
        $eventoId = (int) $this->argument('evento');
        $dryRun = (bool) $this->option('dry-run');

        $evento = Evento::find($eventoId);
        if (! $evento) {
            $this->error("Evento {$eventoId} no existe.");

            return self::FAILURE;
        }

        $formType = $evento->formTypes->first();
        if (! $formType) {
            $this->error("El evento {$eventoId} no tiene ningún FormType — no se puede importar.");

            return self::FAILURE;
        }

        config(['database.connections.legado.database' => $schema]);
        DB::purge('legado');

        // select('*') a propósito: una de las columnas reales
        // ("ACEPTO CONDICIONES") trae un espacio en el nombre y no se usa
        // acá — listar columnas a mano obliga a escaparla sin necesidad.
        $filas = DB::connection('legado')->table('inscrip')
            ->where('ESTADO_INSCRIP', 'PAG')
            ->orderBy('INSCRIP')
            ->get();

        if ($filas->isEmpty()) {
            $this->warn("Sin filas ESTADO_INSCRIP='PAG' en {$schema}.inscrip.");

            return self::SUCCESS;
        }

        $precios = DB::connection('legado')->table('precios')->get()->keyBy('PRECIO');

        $categoriasPorNombre = $evento->categories()->get()
            ->keyBy(fn ($c) => mb_strtoupper(trim($c->name)));

        // Fallback por FACT1 (17/09/2026) — hallazgo real en OFTALMOLOGIA:
        // 98 de 188 filas PAG (más de la mitad) tienen PRECIO_COD=-1
        // (inscripción manual de oficina, TIPO_INSCRIPCION vacío/'OFI',
        // fuera del flujo web que setea el código de precio) y no
        // resuelven categoría por la vía normal. `FACT1` sí trae la
        // categoría real en texto para esas filas — mapeo confirmado con
        // el usuario contra la distribución real de valores. Específico
        // de este dataset (terminología propia de OFTALMOLOGIA), no un
        // mapeo genérico para cualquier legado futuro.
        $mapeoFact1 = [
            'ASOCIADA' => 'MEDICO MIEMBRO',
            'ASOCIADO' => 'MEDICO MIEMBRO',
            'NO ASOCIADA' => 'MEDICO NO MIEMBRO',
            'NO ASOCIADO' => 'MEDICO NO MIEMBRO',
            'EMERITO' => 'EMERITOS',
            'RESIDENTE' => 'RESIDENTES',
        ];

        // Prefijo corto y legible para la referencia (LEG-DB-6846,
        // LEG-PAGO-11222554) — determinístico y trazable al origen, a
        // diferencia del sync de COLABIOCLI (EXT-XXXXXXXX random), acá no
        // hace falta ocultar de qué congreso legado viene.
        $prefijoSchema = strtoupper(str_replace('legado_inscrito_', '', $schema));

        $creadas = 0;
        $actualizadas = 0;
        $omitidas = [];

        foreach ($filas as $fila) {
            $nombreCompleto = trim(($fila->NOMBRE ?? '').' '.($fila->APELLIDO ?? ''));

            $precio = $precios->get($fila->PRECIO_COD);
            $categoria = $precio ? $categoriasPorNombre->get(mb_strtoupper(trim($precio->TEXTO))) : null;

            if (! $categoria) {
                $fact1 = mb_strtoupper(trim((string) ($fila->FACT1 ?? '')));
                $nombreCategoriaFact1 = $mapeoFact1[$fact1] ?? null;
                $categoria = $nombreCategoriaFact1 ? $categoriasPorNombre->get($nombreCategoriaFact1) : null;
            }

            if (! $categoria) {
                $omitidas[] = [
                    'inscrip' => $fila->INSCRIP,
                    'nombre' => $nombreCompleto,
                    'motivo' => "Sin categoría resoluble (PRECIO_COD={$fila->PRECIO_COD}, FACT1=\"{$fila->FACT1}\")",
                ];

                continue;
            }

            // CI del legado a veces trae sufijo de departamento pegado
            // (ej. "826438. Cb", "2927237 SC") — se guarda solo el
            // número, decisión confirmada con el usuario (17/09/2026).
            $ciLimpio = preg_replace('/[^0-9]/', '', (string) $fila->CI);
            $numeroDocumento = $ciLimpio !== '' ? $ciLimpio : trim((string) $fila->EMAIL);
            $tipoDocumento = $ciLimpio !== '' ? 'CI' : 'EMAIL';

            if ($numeroDocumento === '') {
                $omitidas[] = [
                    'inscrip' => $fila->INSCRIP,
                    'nombre' => $nombreCompleto,
                    'motivo' => 'Sin CI ni correo',
                ];

                continue;
            }

            $genero = match (strtoupper(trim((string) $fila->SEXO))) {
                'M' => 'Masculino',
                'F' => 'Femenino',
                default => 'Otro',
            };

            $fechaNacimiento = filled($fila->FECHA_NAC) ? $fila->FECHA_NAC : '1900-01-01';
            $fechaInscripcion = filled($fila->FECHA_INSCRIP) ? Carbon::parse($fila->FECHA_INSCRIP) : now();
            $edad = max(0, Carbon::parse($fechaNacimiento)->diffInYears($fechaInscripcion));

            $origenLegado = "{$schema}.inscrip#{$fila->INSCRIP}";
            $referencia = "LEG-{$prefijoSchema}-{$fila->INSCRIP}";
            $costo = (float) $fila->COSTO;
            $yaExistia = Registration::where('origen_legado', $origenLegado)->exists();

            if ($dryRun) {
                $yaExistia ? $actualizadas++ : $creadas++;

                continue;
            }

            // Fecha real preservada — la inscripción histórica no debería
            // figurar como creada "hoy". Todo en un solo save(): la
            // columna `fecha` tiene ON UPDATE CURRENT_TIMESTAMP a nivel
            // de MySQL (confirmado con SHOW COLUMNS) — si se seteara en
            // un save() separado de created_at/updated_at, un segundo
            // UPDATE que no la toque explícitamente igual dispara ese
            // trigger y la pisa con la fecha de hoy. timestamps=false
            // antes del único save() para que Eloquent tampoco la pise.
            $registration = Registration::firstOrNew(['origen_legado' => $origenLegado]);
            $registration->fill([
                'referencia' => $referencia,
                'fecha' => $fechaInscripcion,
                'evento_id' => $evento->id,
                'form_types_id' => $formType->id,
                'evento_nombre' => $evento->nombre,
                'tipo_pago' => 'legado',
                'pago_status' => 'paid',
                'moneda_pago' => 'BOB',
                'total_pagado' => $costo,
            ]);
            $registration->timestamps = false;
            $registration->created_at = $fechaInscripcion;
            $registration->updated_at = $fechaInscripcion;
            $registration->save();

            Participante::updateOrCreate(
                ['registration_id' => $registration->id],
                [
                    'nombre' => trim((string) $fila->NOMBRE),
                    'apellido' => trim((string) $fila->APELLIDO),
                    'alias' => trim((string) $fila->CAMPO1) !== '' ? trim((string) $fila->CAMPO1) : null,
                    'genero' => $genero,
                    'tipo_documento' => $tipoDocumento,
                    'numero_documento' => $numeroDocumento,
                    'fecha_nacimiento' => $fechaNacimiento,
                    'edad' => $edad,
                    'correo' => trim((string) $fila->EMAIL),
                    'direccion' => '',
                    // PREG2 = ciudad, solo viene poblado en OFTALMOLOGIA.
                    'ciudad' => trim((string) ($fila->PREG2 ?? '')),
                    'telefono' => trim((string) $fila->CELULAR),
                    'categoria' => (string) $categoria->id,
                    'precio_categoria' => $costo,
                    'subtotal' => $costo,
                ]
            );

            RegistrationTotal::updateOrCreate(
                ['registration_id' => $registration->id],
                [
                    'inscripcion' => $costo,
                    'donacion' => 0,
                    'souvenirs' => 0,
                    'talleres' => 0,
                    'fee' => 0,
                    'descuento' => 0,
                    'descuento_registrante' => 0,
                    'grand_total' => $costo,
                    'costo_edicion_acumulado' => 0,
                ]
            );

            // Decisión ya confirmada antes de la pausa del 12/08 (ver
            // memoria project_etl_datos_historicos_legado) — crea/
            // actualiza la Persona con password=Hash::make(numero_documento)
            // para que pueda loguearse con su CI real y ver esto en "Mis
            // Resultados", mismo mecanismo que ya usa toda la app.
            $registrationService->syncPersonas($registration);

            $yaExistia ? $actualizadas++ : $creadas++;
        }

        $this->info(($dryRun ? '[DRY-RUN] ' : '')."Creadas: {$creadas} | Actualizadas: {$actualizadas} | Omitidas: ".count($omitidas));
        foreach ($omitidas as $o) {
            $this->warn("  omitida INSCRIP={$o['inscrip']} ({$o['nombre']}): {$o['motivo']}");
        }

        return self::SUCCESS;
    }
}
