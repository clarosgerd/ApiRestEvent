<?php

namespace App\Actions;

use App\Models\Answer;
use App\Models\Evento;
use App\Models\FormType;
use App\Models\FormularioCampos;
use App\Models\Participante;
use App\Models\Registration;
use App\Models\RegistrationTotal;
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
 */
class SincronizarParticipanteExternoAction
{
    /**
     * @param array{nombre?: string, apellido?: string, correo?: string, telefono?: string, categoria?: string, nombre_curso?: string, ubicacion?: string, nombre_certificado?: string} $fila
     * @return array{resultado: 'creado'|'actualizado'|'omitido', motivo?: string, participanteId?: int}
     */
    public function run(Evento $evento, FormType $formType, array $fila): array
    {
        $nombre = trim((string) ($fila['nombre'] ?? ''));
        $apellido = trim((string) ($fila['apellido'] ?? ''));

        if ($nombre === '' || $apellido === '') {
            return ['resultado' => 'omitido', 'motivo' => 'Falta nombre o apellido.'];
        }

        $correoCrudo = strtolower(trim((string) ($fila['correo'] ?? '')));
        $tieneCorreoValido = $correoCrudo !== '' && filter_var($correoCrudo, FILTER_VALIDATE_EMAIL);

        // Fallback (12/09/2026): `INSCRIPCIONES LIBERADAS` (invitados VIP)
        // a veces no trae correo — sin esto, esas filas se perdían enteras.
        // No es tan robusto contra duplicados como el correo (dos invitados
        // homónimos colisionarían), pero es una lista chica por edición y
        // de todos modos se busca por nombre en el mostrador.
        if ($tieneCorreoValido) {
            $numeroDocumento = $correoCrudo;
            $tipoDocumento = 'EMAIL';
        } else {
            $numeroDocumento = preg_replace('/\s+/', ' ', strtolower(trim("{$nombre} {$apellido}")));
            $tipoDocumento = 'NOMBRE';
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
        $nombreCertificado = trim((string) ($fila['nombre_certificado'] ?? ''));

        return DB::transaction(function () use ($evento, $formType, $nombre, $apellido, $numeroDocumento, $tipoDocumento, $correo, $categoria, $telefono, $ubicacion, $nombreCertificado) {
            $participante = Participante::whereHas(
                'registration',
                fn ($q) => $q->where('evento_id', $evento->id)->where('form_types_id', $formType->id)
            )->where('numero_documento', $numeroDocumento)->first();

            if ($participante) {
                $participante->update([
                    'nombre' => $nombre,
                    'apellido' => $apellido,
                    'categoria' => $categoria !== '' ? $categoria : $participante->categoria,
                    'telefono' => $telefono !== '' ? $telefono : $participante->telefono,
                    'ciudad' => $ubicacion !== '' ? $ubicacion : $participante->ciudad,
                ]);

                if ($nombreCertificado !== '') {
                    $this->guardarNombreCertificado($formType, $participante, $nombreCertificado);
                }

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
            ]);

            $participante = Participante::create([
                'registration_id' => $registration->id,
                'nombre' => $nombre,
                'apellido' => $apellido,
                // genero/fecha_nacimiento/edad: NOT NULL en el esquema pero
                // no los pide el formulario del congreso — sentinels
                // explícitos, no un dato inventado que parezca real.
                'genero' => 'Otro',
                'tipo_documento' => $tipoDocumento,
                'numero_documento' => $numeroDocumento,
                'fecha_nacimiento' => '1900-01-01',
                'edad' => 0,
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
                $this->guardarNombreCertificado($formType, $participante, $nombreCertificado);
            }

            return ['resultado' => 'creado', 'participanteId' => $participante->id];
        });
    }

    /**
     * Guarda `nombre_certificado` como respuesta a la pregunta adicional
     * homónima, SI está configurada para este FormType (setup manual, ver
     * plan §1) — si no existe esa `FormularioCampos`, no hace nada (esta
     * pregunta es opcional por evento, no un requisito del sync).
     * Idempotente: reenviar el mismo valor actualiza la respuesta existente
     * en vez de duplicarla, mismo criterio que el resto del Action.
     */
    private function guardarNombreCertificado(FormType $formType, Participante $participante, string $valor): void
    {
        $pregunta = FormularioCampos::where('form_types_id', $formType->id)
            ->where('nombre_campo', 'nombre_certificado')
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
