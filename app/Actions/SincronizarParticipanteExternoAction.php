<?php

namespace App\Actions;

use App\Models\Answer;
use App\Models\Evento;
use App\Models\FormType;
use App\Models\FormularioCampos;
use App\Models\Participante;
use App\Models\Registration;
use App\Models\RegistrationTotal;
use App\Models\Souvenir;
use App\Models\SouvenirParticipante;
use App\Models\Taller;
use App\Models\ParticipanteTallerSesion;
use App\Support\Taller\ResolverPrecioTallerData;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Sync de participantes de un congreso externo (07/09/2026, rediseñado
 * 12/09/2026) — ver brain/PLAN-SYNC-CONGRESO-EXTERNO-07092026.md. Primer
 * caso: COLABIOCLI 2026, cuyas inscripciones viven en un Google Sheet propio
 * del organizador (fuera de nuestro sistema), sincronizadas acá solo para
 * poder reusar Retiro en sitio (entrega de kit — que ya incluye la
 * credencial, así que también cubre acreditación) — NO para consolidación
 * de balance.
 *
 * A propósito NO reusa `CrearInscripcionAction`: esa valida precio contra
 * `categories`, stock, fee%, moneda, promo — nada de eso aplica, no es una
 * transacción real nuestra (el dinero ya se cobró en la plataforma del
 * organizador). Este Action inserta directo, con su propia validación
 * mínima.
 *
 * `$formType` lo resuelve el controller según de qué hoja vino la fila (el
 * archivo real del congreso tiene 2 productos distintos: "Congresista" y
 * "Curso Pre-Congreso" — una misma persona puede estar en ambos). Por eso el
 * upsert está scopeado por `(evento, form_type, numero_documento)`, no solo
 * evento+documento — así las dos inscripciones de una misma persona no se
 * pisan entre sí.
 *
 * `numero_documento`: su formulario no pide documento de identidad — se usa
 * el CORREO cuando viene. `INSCRIPCIONES LIBERADAS` (invitados VIP, sin
 * costo) a veces no trae correo — ahí se usa `nombre+apellido` normalizado
 * como fallback. Es también la clave de idempotencia: reenviar la misma
 * fila (o el sheet completo, sin filtrar "lo nuevo") actualiza en vez de
 * duplicar.
 *
 * `nombre_certificado` (12/09/2026): la hoja real trae una columna con el
 * nombre exacto que debería figurar en un certificado (con título — "MsC.
 * FRIDA CAMARGO ARCE"), que el congreso emite POR SU CUENTA (columna
 * "Envió Certificado" del Sheet) — no generamos certificados nosotros para
 * este caso. En vez de inventar una columna nueva o intentar parsear el
 * título para reusar `participante.alias` (el campo que sí alimenta
 * `EventoController::certificadosPdf()`), se guarda tal cual, texto crudo,
 * como respuesta al sistema GENÉRICO de preguntas adicionales que ya existe
 * (`questions`/`answers`, `FormularioCampos`/`Answer`) — decisión del
 * usuario: cero riesgo de parsing, es solo un dato de referencia. Si el
 * FormType no tiene una `FormularioCampos` con `nombre_campo='nombre_certificado'`
 * configurada (setup manual, ver plan §1), el dato simplemente no se
 * guarda — no es un error, esta pregunta es opcional por evento.
 *
 * Extensión (17/09/2026) para un segundo caso de uso (evento con fuente
 * externa propia, sincronizada por PULL en vez de push — ver
 * App\Services\SyncExternoPullService): `numero_documento`/`tipo_documento`/
 * `genero`/`fecha_nacimiento` reales cuando la fuente los tiene (a
 * diferencia de COLABIOCLI, que nunca los tuvo — todos son opcionales,
 * aditivos, caen al mismo sentinel de siempre si no vienen), más souvenirs
 * y talleres opcionales por participante (kit de una carrera / talleres de
 * un congreso), matcheados por NOMBRE contra el catálogo real del evento
 * (`Souvenir`/`Taller`/`SesionCongreso`) — best-effort, un nombre que no
 * matchea se omite sin tumbar el resto del participante, mismo criterio que
 * `guardarRespuestaAdicional()`.
 *
 * Id externo estable, opcional (23/09/2026) — hallazgo real: `numero_documento`
 * es a la vez un dato normal Y la clave de matching de "¿ya existe?"; si la
 * fuente corrige un typo de documento de alguien ya sincronizado, la
 * búsqueda por el documento nuevo no encuentra la fila vieja y crea un
 * participante duplicado. `SyncExternoPullService` arma opcionalmente
 * `$fila['_origen_sync_externo']` (clave interna, scopeada por config, no
 * viaja en el contrato público de `$fila`) — cuando viene, es la clave de
 * matching PRIMARIA (antes que numero_documento), lo que permite actualizar
 * numero_documento/tipo_documento con seguridad. COLABIOCLI nunca la manda
 * (push, contrato fijo) — sin ella, el comportamiento es idéntico al de
 * siempre.
 */
class SincronizarParticipanteExternoAction
{
    /**
     * @param array{nombre?: string, apellido?: string, correo?: string, telefono?: string, categoria?: string, nombre_curso?: string, ubicacion?: string, nombre_certificado?: string, id_curso?: string, numero_documento?: string, tipo_documento?: string, genero?: string, fecha_nacimiento?: string, souvenirs?: array, talleres?: array, _origen_sync_externo?: string} $fila
     * @return array{resultado: 'creado'|'actualizado'|'omitido', motivo?: string, participanteId?: int}
     */
    public function run(Evento $evento, FormType $formType, array $fila): array
    {
        $nombre = trim((string) ($fila['nombre'] ?? ''));
        $apellido = trim((string) ($fila['apellido'] ?? ''));

        if ($nombre === '' || $apellido === '') {
            return ['resultado' => 'omitido', 'motivo' => 'Falta nombre o apellido.'];
        }

        // Documento real (17/09/2026) — prioridad sobre el fallback de
        // correo/nombre de abajo, que sigue intacto para fuentes que no lo
        // tengan (ej. COLABIOCLI).
        $documentoCrudo = trim((string) ($fila['numero_documento'] ?? ''));
        $correoCrudo = strtolower(trim((string) ($fila['correo'] ?? '')));
        $tieneCorreoValido = $correoCrudo !== '' && filter_var($correoCrudo, FILTER_VALIDATE_EMAIL);

        if ($documentoCrudo !== '') {
            $numeroDocumento = strtolower($documentoCrudo);
            $tipoDocumento = trim((string) ($fila['tipo_documento'] ?? '')) !== ''
                ? trim((string) $fila['tipo_documento'])
                : 'CI';
        } elseif ($tieneCorreoValido) {
            // Fallback (12/09/2026): `INSCRIPCIONES LIBERADAS` (invitados VIP)
            // a veces no trae correo — sin esto, esas filas se perdían enteras.
            // No es tan robusto contra duplicados como el correo (dos invitados
            // homónimos colisionarían), pero es una lista chica por edición y
            // de todos modos se busca por nombre en el mostrador.
            $numeroDocumento = $correoCrudo;
            $tipoDocumento = 'EMAIL';
        } else {
            $numeroDocumento = preg_replace('/\s+/', ' ', strtolower(trim("{$nombre} {$apellido}")));
            $tipoDocumento = 'NOMBRE';
        }

        // Género real (17/09/2026) — cualquier valor que no matchee el
        // enum real cae al sentinel 'Otro' de siempre (nunca un default
        // real como 'Masculino', ver project_bug_genero_masculino_y_hora_evento).
        $generoCrudo = mb_strtolower(trim((string) ($fila['genero'] ?? '')));
        $genero = match ($generoCrudo) {
            'masculino' => 'Masculino',
            'femenino' => 'Femenino',
            default => 'Otro',
        };

        // Fecha de nacimiento/edad reales (17/09/2026) — sin el campo, cae
        // al sentinel actual (1900-01-01/0), sin cambio para COLABIOCLI.
        // "Edad al sincronizar", mismo cálculo que ya usa
        // LegadoImportar.php — CalculoEdadResolver recalcula la edad "real"
        // por categoría a partir de fecha_nacimiento cuando hace falta,
        // sin tocar nada acá.
        $fechaNacimientoCruda = trim((string) ($fila['fecha_nacimiento'] ?? ''));
        $fechaNacimiento = '1900-01-01';
        $edad = 0;
        if ($fechaNacimientoCruda !== '') {
            try {
                $fechaNac = Carbon::parse($fechaNacimientoCruda);
                $fechaNacimiento = $fechaNac->toDateString();
                $edad = max(0, (int) $fechaNac->diffInYears(now()));
            } catch (\Exception) {
                // Fecha ilegible — no tumba la fila entera, solo se ignora
                // y cae al sentinel (mismo criterio tolerante del resto).
            }
        }

        // `nombre_curso` (hoja Cursos_Pre_Congreso) pisa a `categoria` si
        // viene — esa hoja manda "Curso Pre-Congreso" en su columna
        // Categoría para TODAS las filas (no distingue nada); el dato real
        // de qué curso es está en Nombre Curso.
        $categoria = trim((string) ($fila['nombre_curso'] ?? '')) !== ''
            ? trim((string) $fila['nombre_curso'])
            : trim((string) ($fila['categoria'] ?? ''));
        $telefono = trim((string) ($fila['telefono'] ?? ''));
        $ubicacion = trim((string) ($fila['ubicacion'] ?? ''));
        $correo = $tieneCorreoValido ? $correoCrudo : '';
        // Clave de matching estable, opcional — ver docblock de la clase.
        $origenSyncExterno = trim((string) ($fila['_origen_sync_externo'] ?? ''));
        $nombreCertificado = trim((string) ($fila['nombre_certificado'] ?? ''));
        // Fusión de inscripciones duplicadas por persona — curso
        // pre-congreso (16/09/2026) — a diferencia de nombre_curso (que ya
        // tiene un hogar natural en `categoria`), el id interno del curso
        // no tenía ninguna columna — mismo mecanismo que
        // nombre_certificado (pregunta adicional opcional, cero riesgo de
        // parsing), ver guardarRespuestaAdicional().
        $idCurso = trim((string) ($fila['id_curso'] ?? ''));
        $souvenirsCrudo = is_array($fila['souvenirs'] ?? null) ? $fila['souvenirs'] : [];
        $talleresCrudo = is_array($fila['talleres'] ?? null) ? $fila['talleres'] : [];

        return DB::transaction(function () use ($evento, $formType, $nombre, $apellido, $numeroDocumento, $tipoDocumento, $genero, $fechaNacimiento, $edad, $correo, $categoria, $telefono, $ubicacion, $origenSyncExterno, $nombreCertificado, $idCurso, $souvenirsCrudo, $talleresCrudo) {
            $participante = null;

            // Id externo estable (23/09/2026) — matching PRIMARIO cuando
            // viene, para que un cambio de numero_documento en la fuente no
            // cree un duplicado (ver docblock de la clase).
            if ($origenSyncExterno !== '') {
                $registrationPorClave = Registration::where('evento_id', $evento->id)
                    ->where('form_types_id', $formType->id)
                    ->where('origen_sync_externo', $origenSyncExterno)
                    ->first();

                if ($registrationPorClave) {
                    $participante = Participante::where('registration_id', $registrationPorClave->id)->first();
                }
            }

            // Fallback de siempre — también resuelve el auto-backfill: una
            // fila creada antes de que la fuente mandara external_id se
            // encuentra acá, y más abajo se le graba la clave por primera vez.
            if (! $participante) {
                $participante = Participante::whereHas(
                    'registration',
                    fn ($q) => $q->where('evento_id', $evento->id)->where('form_types_id', $formType->id)
                )->where('numero_documento', $numeroDocumento)->first();
            }

            if ($participante) {
                $participante->update([
                    'nombre' => $nombre,
                    'apellido' => $apellido,
                    // numero_documento/tipo_documento: solo se pisan cuando
                    // la identidad ya está confirmada por la clave externa
                    // estable — si no, numero_documento es también la
                    // clave de matching del fallback de arriba, pisarlo a
                    // ciegas rompería el próximo match.
                    'numero_documento' => $origenSyncExterno !== '' ? $numeroDocumento : $participante->numero_documento,
                    'tipo_documento' => $origenSyncExterno !== '' ? $tipoDocumento : $participante->tipo_documento,
                    'genero' => $genero !== 'Otro' ? $genero : $participante->genero,
                    'fecha_nacimiento' => $fechaNacimiento !== '1900-01-01' ? $fechaNacimiento : $participante->fecha_nacimiento,
                    'edad' => $fechaNacimiento !== '1900-01-01' ? $edad : $participante->edad,
                    'categoria' => $categoria !== '' ? $categoria : $participante->categoria,
                    'correo' => $correo !== '' ? $correo : $participante->correo,
                    'telefono' => $telefono !== '' ? $telefono : $participante->telefono,
                    'ciudad' => $ubicacion !== '' ? $ubicacion : $participante->ciudad,
                ]);

                // Auto-backfill (23/09/2026) — la fuente ya manda
                // external_id pero esta fila todavía no tiene la clave
                // grabada (se encontró por el fallback de numero_documento)
                // — grabarla ahora, sin necesitar backfill manual, para que
                // quede protegida contra un futuro cambio de documento.
                if ($origenSyncExterno !== '' && $participante->registration->origen_sync_externo !== $origenSyncExterno) {
                    $participante->registration->update(['origen_sync_externo' => $origenSyncExterno]);
                }

                if ($nombreCertificado !== '') {
                    $this->guardarRespuestaAdicional($formType, $participante, 'nombre_certificado', $nombreCertificado);
                }
                if ($idCurso !== '') {
                    $this->guardarRespuestaAdicional($formType, $participante, 'id_curso', $idCurso);
                }
                $this->sincronizarSouvenirs($formType, $participante, $souvenirsCrudo);
                $this->sincronizarTalleres($evento, $participante, $talleresCrudo);

                return ['resultado' => 'actualizado', 'participanteId' => $participante->id];
            }

            $referencia = 'EXT-' . strtoupper(substr(md5(uniqid((string) mt_rand(), true)), 0, 8));

            $registration = Registration::create([
                'referencia' => $referencia,
                'fecha' => now(),
                'evento_id' => $evento->id,
                'form_types_id' => $formType->id,
                'evento_nombre' => $evento->nombre,
                // 'externo': no es un cobro real nuestro — visible en
                // cualquier reporte/export para que no se confunda con un
                // pago procesado por nuestras pasarelas.
                'tipo_pago' => 'externo',
                'pago_status' => 'paid',
                'origen_sync_externo' => $origenSyncExterno !== '' ? $origenSyncExterno : null,
            ]);

            $participante = Participante::create([
                'registration_id' => $registration->id,
                'nombre' => $nombre,
                'apellido' => $apellido,
                // genero/fecha_nacimiento/edad: NOT NULL en el esquema —
                // reales cuando la fuente los tiene (17/09/2026), si no
                // sentinels explícitos, nunca un dato inventado que
                // parezca real.
                'genero' => $genero,
                'tipo_documento' => $tipoDocumento,
                'numero_documento' => $numeroDocumento,
                'fecha_nacimiento' => $fechaNacimiento,
                'edad' => $edad,
                // Puede quedar vacío (fallback sin correo, ver docblock) —
                // 'correo' no es NULLABLE pero sí acepta '' (string NOT
                // NULL sin default, no hay constraint de formato en BD).
                'correo' => $correo,
                'direccion' => '',
                'ciudad' => $ubicacion,
                'telefono' => $telefono,
                'categoria' => $categoria !== '' ? $categoria : 'Sin categoría',
                'subtotal' => 0,
            ]);

            // En cero, explícito — no es consolidación de balance (ver
            // docblock de la clase). `registration.totals` lo asumen no
            // nulo varios lugares del sistema (BalanceEventoData, edición
            // pagada), así que igual hace falta la fila.
            RegistrationTotal::create([
                'registration_id' => $registration->id,
                'inscripcion' => 0,
                'donacion' => 0,
                'souvenirs' => 0,
                'talleres' => 0,
                'fee' => 0,
                'descuento' => 0,
                'descuento_registrante' => 0,
                'grand_total' => 0,
                'costo_edicion_acumulado' => 0,
            ]);

            if ($nombreCertificado !== '') {
                $this->guardarRespuestaAdicional($formType, $participante, 'nombre_certificado', $nombreCertificado);
            }
            if ($idCurso !== '') {
                $this->guardarRespuestaAdicional($formType, $participante, 'id_curso', $idCurso);
            }
            $this->sincronizarSouvenirs($formType, $participante, $souvenirsCrudo);
            $this->sincronizarTalleres($evento, $participante, $talleresCrudo);

            return ['resultado' => 'creado', 'participanteId' => $participante->id];
        });
    }

    /**
     * Souvenirs (kit de una carrera, 17/09/2026) — cada entrada trae un
     * NOMBRE de texto libre (la fuente externa no conoce nuestros IDs),
     * matcheado case-insensitive contra el catálogo real del FormType. Un
     * nombre sin match se ignora (no tumba el participante) — mismo
     * criterio tolerante que guardarRespuestaAdicional(). Nunca borra
     * souvenirs existentes que ya no vengan en esta corrida (solo agrega/
     * actualiza) — evita perder datos por una fuente transitoriamente
     * incompleta.
     *
     * @param array<int, array{nombre?: string, talla?: string, sexo?: string}> $souvenirs
     */
    private function sincronizarSouvenirs(FormType $formType, Participante $participante, array $souvenirs): void
    {
        foreach ($souvenirs as $fila) {
            $nombre = trim((string) ($fila['nombre'] ?? ''));
            if ($nombre === '') {
                continue;
            }

            $souvenir = Souvenir::where('form_types_id', $formType->id)
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($nombre)])
                ->first();

            if (! $souvenir) {
                continue;
            }

            SouvenirParticipante::updateOrCreate(
                ['participante_id' => $participante->id, 'souvenir_id' => $souvenir->id],
                [
                    'nombre' => $souvenir->name,
                    'precio' => $souvenir->price,
                    'talla' => $souvenir->requiere_talla ? trim((string) ($fila['talla'] ?? '')) ?: null : null,
                    'sexo' => $souvenir->requiere_sexo ? trim((string) ($fila['sexo'] ?? '')) ?: null : null,
                ]
            );
        }
    }

    /**
     * Talleres (sesiones de congreso, 17/09/2026) — mismo criterio que
     * souvenirs: `taller`/`sesion` son texto libre, matcheados contra el
     * catálogo real del evento. Si el taller tiene una sola sesión y no
     * vino `sesion`, se toma esa; con 2+ sesiones sin desambiguar, se omite
     * (ambiguo, no se adivina). Nunca cobra nada (mismo criterio que el
     * resto de la Action — RegistrationTotal siempre en $0) — `discount=0`,
     * `pago_pendiente=false`.
     *
     * @param array<int, array{taller?: string, sesion?: string}> $talleres
     */
    private function sincronizarTalleres(Evento $evento, Participante $participante, array $talleres): void
    {
        foreach ($talleres as $fila) {
            $nombreTaller = trim((string) ($fila['taller'] ?? ''));
            if ($nombreTaller === '') {
                continue;
            }

            $taller = Taller::where('evento_id', $evento->id)
                ->whereRaw('LOWER(nombre) = ?', [mb_strtolower($nombreTaller)])
                ->first();

            if (! $taller) {
                continue;
            }

            $sesiones = $taller->sesiones;
            $nombreSesion = trim((string) ($fila['sesion'] ?? ''));

            if ($nombreSesion !== '') {
                $sesion = $sesiones->first(
                    fn ($s) => mb_strtolower(trim($s->titulo)) === mb_strtolower($nombreSesion)
                );
            } else {
                $sesion = $sesiones->count() === 1 ? $sesiones->first() : null;
            }

            if (! $sesion) {
                continue;
            }

            $unitPrice = ResolverPrecioTallerData::unitPrice($taller, $sesion, $evento);
            $total = ResolverPrecioTallerData::total($taller, $sesion, $evento);

            ParticipanteTallerSesion::updateOrCreate(
                ['participante_id' => $participante->id, 'sesion_congreso_id' => $sesion->id],
                [
                    'taller_id' => $taller->id,
                    'unit_price' => $unitPrice,
                    'discount' => 0,
                    'total' => $total,
                    'pago_pendiente' => false,
                ]
            );
        }
    }

    /**
     * Guarda un dato suelto (nombre_certificado, id_curso — 16/09/2026)
     * como respuesta a la pregunta adicional homónima, SI está configurada
     * para este FormType (setup manual, ver plan §1) — si no existe esa
     * `FormularioCampos`, no hace nada (estas preguntas son opcionales por
     * evento, no un requisito del sync). Idempotente: reenviar el mismo
     * valor actualiza la respuesta existente en vez de duplicarla, mismo
     * criterio que el resto del Action.
     */
    private function guardarRespuestaAdicional(FormType $formType, Participante $participante, string $nombreCampo, string $valor): void
    {
        $pregunta = FormularioCampos::where('form_types_id', $formType->id)
            ->where('nombre_campo', $nombreCampo)
            ->first();

        if (! $pregunta) {
            return;
        }

        Answer::updateOrCreate(
            [
                'form_types_id' => $formType->id,
                'question_id' => $pregunta->id,
                'participante_id' => $participante->id,
            ],
            ['value' => $valor]
        );
    }
}
