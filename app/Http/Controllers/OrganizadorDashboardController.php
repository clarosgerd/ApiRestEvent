<?php

namespace App\Http\Controllers;

use App\Models\Answer;
use App\Models\Evento;
use App\Models\Genero;
use App\Models\NumeracionRango;
use App\Models\Participante;
use App\Services\RegistrationService;
use App\Support\BalanceEventoData;
use App\Support\CalculoEdadResolver;
use App\Support\DashboardInscripcionesData;
use App\Support\NumeracionRangoChecker;
use App\Support\RecategorizacionResolver;
use App\Support\ReporteInscritosData;
use App\Support\TallaPoleraData;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\URL;

class OrganizadorDashboardController extends Controller
{
    /**
     * Dashboard de solo lectura para el organizador — accedido vía link
     * firmado (sin login, ver routes/web.php), generado con
     * `php artisan organizador:generar-link {evento}`. El cálculo de
     * conteos vive en DashboardInscripcionesData, reusado también por el
     * endpoint autenticado del panel de administración
     * (EventoController::dashboardInscripciones).
     */
    public function show(Evento $evento)
    {
        return view('organizador.dashboard', array_merge(
            ['evento' => $evento],
            DashboardInscripcionesData::paraEvento($evento),
            [
                'balance' => BalanceEventoData::paraEvento($evento),
                // Inscritos por categoría/distancia con recaudación
                // (03/09/2026, pedido del usuario) — distinto de
                // $porCategoria de arriba (DashboardInscripcionesData: cuenta
                // por estado de pago, sin dinero). Reusa ReporteInscritosData
                // tal cual, mismo cálculo que ya usa el panel autenticado
                // (EventoController::dashboardInscripciones) — solo cuenta
                // `paid`, "Recaudación" es dinero efectivamente cobrado.
                'reporteInscritos' => ReporteInscritosData::paraEvento($evento),
                // Firma cubre solo `evento` (los filtros se ignoran al validar
                // en exportCsv) — la vista arma los links filtrados agregando
                // &categoria=/&form_type_id=/&pago_status= a esta misma URL base.
                'exportBaseUrl' => URL::signedRoute('organizador.dashboard.export', ['evento' => $evento->id]),
                // Detalle de inscritos con buscador (07/09/2026, pedido del
                // usuario: poder buscar al hacer clic en "Pagados") — mismo
                // patrón que exportBaseUrl, la firma solo cubre `evento`.
                'detalleBaseUrl' => URL::signedRoute('organizador.dashboard.detalle', ['evento' => $evento->id]),
            ]
        ));
    }

    /**
     * Detalle de inscritos fila-por-fila, con buscador (07/09/2026, pedido
     * del usuario: al hacer clic en la tarjeta "Pagados" — u otro estado —
     * del dashboard público, ver un listado filtrable por documento,
     * nombre/apellido o correo). Mismo patrón que exportCsv(): la firma
     * cubre solo `evento`, ignorando categoria/pago_status/search/page para
     * que un único link sirva con cualquier combinación de filtros.
     *
     * A propósito una pantalla nueva (no reutiliza participantes-detalle de
     * admin-eventos, que requiere login) — este dashboard es sin login,
     * accedido solo por link firmado.
     */
    public function detalle(Evento $evento, Request $request)
    {
        abort_unless(
            $request->hasValidSignatureWhileIgnoring(['categoria', 'pago_status', 'search', 'page']),
            403
        );

        $categoria = $request->query('categoria', '');
        $pagoStatus = $request->query('pago_status', '');
        $search = trim((string) $request->query('search', ''));
        $page = max((int) $request->query('page', 1), 1);
        $perPage = 50;

        $query = DashboardInscripcionesData::participantesDelEvento($evento);

        if ($categoria !== '') {
            $query->where('categoria', $categoria);
        }
        if ($pagoStatus !== '') {
            $query->whereHas('registration', fn (Builder $q) => $q->where('pago_status', $pagoStatus));
        }
        // Buscador — mismo criterio que ParticipanteController::porEvento()
        // (LIKE OR sobre nombre/apellido/numero_documento/correo).
        if ($search !== '') {
            $like = '%' . $search . '%';
            $query->where(function (Builder $q) use ($like) {
                $q->where('nombre', 'like', $like)
                    ->orWhere('apellido', 'like', $like)
                    ->orWhere('numero_documento', 'like', $like)
                    ->orWhere('correo', 'like', $like);
            });
        }

        $paginador = $query->orderBy('apellido')->paginate($perPage, ['*'], 'page', $page);

        return view('organizador.dashboard-detalle', [
            'evento' => $evento,
            'participantes' => $paginador,
            'nombresCategorias' => $evento->categories()->pluck('name', 'id'),
            'categoriaSeleccionada' => $categoria,
            'pagoStatusSeleccionado' => $pagoStatus,
            'searchSeleccionado' => $search,
            'baseUrl' => URL::signedRoute('organizador.dashboard.detalle', ['evento' => $evento->id]),
            'dashboardUrl' => URL::signedRoute('organizador.dashboard', ['evento' => $evento->id]),
            // Para el <form method="GET">: el navegador descarta la query
            // string de `action` al armar el submit, así que la firma viaja
            // como campo oculto (ver dashboard-detalle.blade.php).
            'signature' => $request->query('signature'),
        ]);
    }

    /**
     * Descarga CSV del listado de participantes, con filtros opcionales por
     * categoría / tipo de formulario / estado de pago (query string, sin
     * firmar) — la firma solo cubre `evento`, así el mismo link sirve para
     * cualquier combinación de filtros sin generar un link por cada uno.
     */
    public function exportCsv(Evento $evento, Request $request)
    {
        abort_unless(
            $request->hasValidSignatureWhileIgnoring(['categoria', 'form_type_id', 'pago_status']),
            403
        );

        $query = DashboardInscripcionesData::participantesDelEvento($evento);

        if ($request->filled('categoria')) {
            $query->where('categoria', $request->query('categoria'));
        }
        if ($request->filled('form_type_id')) {
            $query->whereHas('registration', fn (Builder $q) => $q->where('form_types_id', $request->query('form_type_id')));
        }
        if ($request->filled('pago_status')) {
            $query->whereHas('registration', fn (Builder $q) => $q->where('pago_status', $request->query('pago_status')));
        }

        $participantes = $query->get();

        // Fusión de inscripciones duplicadas por persona — curso
        // pre-congreso (16/09/2026) — un participante externo (ver
        // SincronizarParticipanteExternoAction, tipo_pago='externo') puede
        // tener 2 inscripciones en el mismo evento con el mismo documento
        // (ej. COLABIOCLI: Congresista + Curso Pre-Congreso) — antes cada
        // una salía como su propia fila del CSV, y elascenso/delivery
        // (que solo indexa por documento) terminaba con UNA sola tarjeta
        // donde la fila que se sincronizaba después pisaba en silencio a
        // la anterior (perdiendo la categoría de Congresista sin avisar).
        // Se fusiona acá: la fila "Curso Pre-Congreso" no sale por su
        // cuenta si la misma persona tiene otra inscripción — su info
        // viaja en las columnas nuevas NombreCurso/IdCurso de la fila
        // principal. Si alguien SOLO tomó un curso (sin ser Congresista),
        // conserva su propia fila normalmente (no tiene con qué fusionar).
        $participantesCurso = $participantes->filter(
            fn ($p) => mb_strtolower(trim(optional($p->registration->formType)->name ?? '')) === 'curso pre-congreso'
        );

        // Answer no tiene relación `question()` definida (solo
        // `participante()`) — join directo contra `questions` (tabla real
        // detrás de FormularioCampos) en vez de whereHas.
        $idCursoPorParticipante = $participantesCurso->isEmpty()
            ? collect()
            : Answer::query()
                ->join('questions', 'questions.id', '=', 'answers.question_id')
                ->whereIn('answers.participante_id', $participantesCurso->pluck('id'))
                ->where('questions.nombre_campo', 'id_curso')
                ->pluck('answers.value', 'answers.participante_id');

        $cursoInfoPorDocumento = [];
        foreach ($participantesCurso as $p) {
            // Si una persona toma 2+ cursos, el último procesado pisa al
            // anterior acá — misma limitación aceptada que ya existía para
            // `categoria` antes de esta fusión (ver plan, sección
            // "Cursos_Pre_Congreso es un producto aparte"), no es una
            // regresión nueva.
            $cursoInfoPorDocumento[$p->numero_documento] = [
                'nombreCurso' => $p->categoria,
                'idCurso' => $idCursoPorParticipante[$p->id] ?? '',
            ];
        }

        // Campos de carrera/congreso en reportes al cliente (18/09/2026) —
        // ver $incluyeColumnasNumeracion más abajo, mismo criterio: solo se
        // agregan las columnas NombreCurso/IdCurso si ESTE evento tiene
        // datos reales de curso pre-congreso (no todo congreso lo tiene).
        $incluyeColumnasCurso = ! empty($cursoInfoPorDocumento);

        $documentosConOtraInscripcion = $participantes
            ->filter(fn ($p) => mb_strtolower(trim(optional($p->registration->formType)->name ?? '')) !== 'curso pre-congreso')
            ->pluck('numero_documento')
            ->flip();

        $participantes = $participantes->reject(function ($p) use ($documentosConOtraInscripcion) {
            $esCurso = mb_strtolower(trim(optional($p->registration->formType)->name ?? '')) === 'curso pre-congreso';

            return $esCurso && $documentosConOtraInscripcion->has($p->numero_documento);
        });

        // Nombre de categoría en el CSV (02/09/2026) — participantes.categoria
        // guarda el ID de la categoría (ver backfill_participante_categoria_
        // legacy_names_to_id), no el nombre; el CSV lo mostraba crudo ("6",
        // "90028") en vez de "15K"/"35K". Mismo mapeo que ya arma
        // DashboardInscripcionesData::paraEvento() (nombresCategorias) para
        // la tabla "Por categoría" en pantalla. Fallback al valor crudo: un
        // form_type sin categoría real (requiere_categoria=false) guarda su
        // propio nombre en este campo, no un ID — ya es legible tal cual.
        $nombresCategorias = $evento->categories()->pluck('name', 'id');

        // Edad usada para el aviso de numeración (16/09/2026) — la tarjeta
        // de entrega en elascenso/delivery mostraba antes la edad "de hoy"
        // (fecha actual menos fecha de nacimiento), que puede no coincidir
        // con el método que eligió la categoría (`calculo_edad_id`, ver
        // CalculoEdadResolver) — confundía al staff con casos como "edad
        // que cumple en el año del evento" (participante que todavía no
        // cumplió años este año). Se expone la edad REAL usada por el
        // aviso para que la tarjeta muestre siempre la misma que
        // NumeracionRangoChecker usó para decidir si avisar o no.
        $categoriasPorId = $evento->categories()->get()->keyBy('id');

        // Numeración/chip solo aplica a carreras, no a congresos
        // (16/09/2026) — el POS mostraba igual la sección de N° corredor/
        // chip (y su aviso) para cualquier evento, incluidos congresos
        // (ej. COLABIOCLI) donde no reparten numeración ni chip de
        // cronometraje. `tipos_evento` tiene un único tipo que no es una
        // actividad de carrera: "Congreso / No aplica" — el resto (Carrera
        // de Ruta, Trail Running, Ciclismo, Caminata, Triatlón, Natación)
        // sí son carreras reales.
        $usaNumeracion = mb_strtolower(trim(optional($evento->tipoEvento)->nombre ?? '')) !== 'congreso / no aplica'
            ? '1' : '';

        // Campos de carrera/congreso en reportes al cliente (18/09/2026) —
        // ver análisis en la memoria del proyecto
        // (project_reportes_csv_campos_carrera_congreso). Criterio por
        // PRESENCIA DE DATOS, no por tipo de evento (evita romper eventos
        // híbridos y no depende de un catálogo con un único valor "no
        // carrera"): las columnas de numeración/chip solo se agregan si
        // $usaNumeracion (ya calculado arriba) es real, y las de curso
        // pre-congreso solo si este evento realmente tiene algún dato de
        // curso — mismo criterio que ya usa el CSV de talleres del panel
        // (dashboard-inscripciones.blade.php) para ocultar su propio botón.
        $incluyeColumnasNumeracion = $usaNumeracion === '1';

        // Recategorización visual por edad/género (23/09/2026) — a pedido
        // del usuario, solo se agregan las 2 columnas si el evento tiene
        // AL MENOS un NumeracionRango cargado en alguna de sus categorías
        // (mismo criterio de presencia de datos que el resto de este CSV).
        // Precargado una sola vez (no por participante) para no hacer N+1
        // — ver RecategorizacionResolver, que acepta estas colecciones.
        $numeracionRangosDelEvento = NumeracionRango::whereHas(
            'category',
            fn ($q) => $q->where('event_id', $evento->id)
        )->with('category')->get();
        $incluyeColumnaRecategorizacion = $numeracionRangosDelEvento->isNotEmpty();
        $generosPorNombre = $incluyeColumnaRecategorizacion ? Genero::all()->keyBy('nombre') : collect();

        // Talla real de la polera (03/09/2026) — ver TallaPoleraData: esta
        // columna leía directo `participantes.polera` (legacy), que queda
        // siempre en el sentinel 'No shirt' para eventos que ya modelan la
        // polera como un souvenir normal.
        $souvenirIdsPolera = TallaPoleraData::souvenirIdsPolera($evento->formTypes()->pluck('id')->all());

        return response()->streamDownload(function () use ($participantes, $evento, $nombresCategorias, $categoriasPorId, $souvenirIdsPolera, $cursoInfoPorDocumento, $usaNumeracion, $incluyeColumnasNumeracion, $incluyeColumnasCurso, $incluyeColumnaRecategorizacion, $numeracionRangosDelEvento, $generosPorNombre) {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                // 'Monto categoría'/'Monto souvenir' (02/09/2026) — pedido
                // del usuario revisando este mismo CSV: antes solo se veía
                // el nombre de la categoría/souvenirs, sin poder saber
                // cuánto pagó cada participante por cada uno.
                'Nombre', 'Apellido', 'Documento', 'Categoría', 'Monto categoría', 'Tipo de formulario',
                'Talla/Polera', 'Souvenirs', 'Monto souvenir', 'Teléfono', 'Correo', 'Estado de pago', 'Referencia',
                // Campos de carrera/congreso (18/09/2026) — estas 3 solo se
                // agregan si el evento realmente usa numeración (ver
                // $incluyeColumnasNumeracion).
                ...($incluyeColumnasNumeracion ? ['NumeroCorredor', 'Chip', 'ActualizarNumeracionUrl'] : []),
                'MontoPendiente', 'ConfirmarPagoSitioUrl',
                // Aviso de numeración vs. género/edad real en entrega de kit
                // (16/09/2026) — Género/FechaNacimiento no viajaban antes,
                // elascenso/delivery los necesita para mostrarlos en la
                // tarjeta de entrega.
                'Género', 'FechaNacimiento', 'EdadCalculada',
                // AlertaNumeracion (16/09/2026) — igual que arriba, solo
                // tiene sentido si el evento usa numeración — ver
                // NumeracionRangoChecker.
                ...($incluyeColumnasNumeracion ? ['AlertaNumeracion'] : []),
                // Fusión de inscripciones duplicadas por persona — curso
                // pre-congreso (16/09/2026) — ahora solo se agregan si ESTE
                // evento tiene datos reales de curso (18/09/2026, no todo
                // congreso los tiene).
                ...($incluyeColumnasCurso ? ['NombreCurso', 'IdCurso'] : []),
                // Numeración/chip solo aplica a carreras (16/09/2026) —
                // '1' si el tipo de evento no es "Congreso / No aplica",
                // vacío si lo es. elascenso/delivery usa esto para ocultar
                // toda la sección de N° corredor/chip y su aviso en el POS
                // cuando no corresponde. Esta columna se mantiene SIEMPRE
                // presente (a diferencia de las condicionales de arriba)
                // porque delivery ya depende de que siempre esté ahí.
                'UsaNumeracion',
                // Recategorización visual por edad/género (23/09/2026) —
                // solo si el evento tiene algún NumeracionRango cargado
                // (ver $incluyeColumnaRecategorizacion). Nunca toca
                // participantes.categoria ni precio_categoria — es
                // puramente informativo para ChronoTrack/delivery.
                ...($incluyeColumnaRecategorizacion ? ['CategoriaRecalculada', 'CategoriaRecalculadaColor'] : []),
            ]);
            foreach ($participantes as $p) {
                // Cobro en sitio (12/08/2026) — ver
                // ApiRestEvent/brain/api_rest_event/PRD-precios-periodos-fechas.md,
                // sección 0: los form_types con requiere_categoria=false
                // pasaron a cobrar precio_base de verdad (antes $0), así que
                // una inscripción pendiente de ese tipo puede llegar al
                // mostrador de retiro en sitio sin haber pagado. Acotado a
                // propósito a ese caso — no es "cobro en efectivo genérico
                // para cualquier pendiente", solo el que este cambio de
                // precio originó. `ConfirmarPagoSitioUrl` viaja vacío para
                // cualquier otro caso (ya pagado, o requiere_categoria=true)
                // — elascenso/delivery decide si mostrar el botón de cobro
                // únicamente en base a si esta columna trae algo.
                $formType = $p->registration->formType;
                $elegibleCobroSitio = $p->registration->pago_status === 'pending'
                    && $formType
                    && ! $formType->requiere_categoria;

                $tallaPolera = TallaPoleraData::resolver($p, $souvenirIdsPolera);

                $categoriaParticipante = $categoriasPorId->get($p->categoria);
                $edadCalculada = $categoriaParticipante
                    ? CalculoEdadResolver::para($p, $categoriaParticipante, $evento)
                    : '';

                $cursoInfo = $cursoInfoPorDocumento[$p->numero_documento] ?? null;

                if ($incluyeColumnaRecategorizacion) {
                    $recategorizacion = RecategorizacionResolver::paraParticipante(
                        $p, $evento, $numeracionRangosDelEvento, $generosPorNombre, $categoriasPorId
                    );
                    $categoriaRecalculada = $recategorizacion['category']->name ?? ($nombresCategorias[$p->categoria] ?? $p->categoria);
                    $categoriaRecalculadaColor = $recategorizacion['color'] ?? '';
                }

                fputcsv($out, [
                    $p->nombre,
                    $p->apellido,
                    trim($p->tipo_documento . ' ' . $p->numero_documento),
                    $nombresCategorias[$p->categoria] ?? $p->categoria,
                    $p->precio_categoria,
                    optional($formType)->name,
                    $tallaPolera,
                    $p->souvenirParticipante->pluck('nombre')->implode(', '),
                    // number_format explícito: sum() no pasa por el cast
                    // decimal:2 del modelo (a diferencia de precio_categoria
                    // arriba), sale "40" en vez de "40.00" si no se fuerza.
                    number_format((float) $p->souvenirParticipante->sum('precio'), 2, '.', ''),
                    $p->telefono,
                    $p->correo,
                    $p->registration->pago_status,
                    $p->registration->referencia,
                    // Campos de carrera/congreso (18/09/2026) — ver el
                    // header, mismo criterio $incluyeColumnasNumeracion. El
                    // link firmado (por-documento, para que elascenso/delivery
                    // pueda cargar numeración/chip al momento de la entrega
                    // física cuando el proveedor externo no llegó a tiempo)
                    // ni se genera si no aplica.
                    ...($incluyeColumnasNumeracion ? [
                        $p->numero_corredor,
                        $p->chip,
                        URL::signedRoute('organizador.dashboard.actualizar-numeracion', [
                            'evento' => $evento->id,
                            'documento' => $p->numero_documento,
                        ]),
                    ] : []),
                    $elegibleCobroSitio ? optional($p->registration->totals)->grand_total : null,
                    $elegibleCobroSitio ? URL::signedRoute('organizador.dashboard.confirmar-pago-sitio', [
                        'evento' => $evento->id,
                        'documento' => $p->numero_documento,
                    ]) : null,
                    $p->genero,
                    optional($p->fecha_nacimiento)->format('Y-m-d'),
                    $edadCalculada,
                    ...($incluyeColumnasNumeracion ? [NumeracionRangoChecker::alertaPara($p, $evento) ?? ''] : []),
                    ...($incluyeColumnasCurso ? [$cursoInfo['nombreCurso'] ?? '', $cursoInfo['idCurso'] ?? ''] : []),
                    $usaNumeracion,
                    ...($incluyeColumnaRecategorizacion ? [$categoriaRecalculada, $categoriaRecalculadaColor] : []),
                ]);
            }
            fclose($out);
        }, 'participantes-evento-' . $evento->id . '.csv', ['Content-Type' => 'text/csv']);
    }

    /**
     * Push-back de numeración de corredor/chip desde el POS de retiro en
     * sitio (elascenso/delivery) — machine-to-machine, la firma cubre
     * evento+documento e ignora numero_corredor/chip (mismo patrón que
     * DeliveryController::updateEstado()). No requiere el id numérico del
     * participante porque delivery, en el flujo de retiro en sitio, solo
     * tiene el numero_documento (ver brain, RetiroSitio no guarda
     * participante_id).
     */
    public function actualizarNumeracionSitio(Request $request, Evento $evento, string $documento): JsonResponse
    {
        abort_unless($request->hasValidSignatureWhileIgnoring(['numero_corredor', 'chip']), 403);

        $participante = Participante::whereHas('registration', fn (Builder $q) => $q->where('evento_id', $evento->id))
            ->where('numero_documento', $documento)
            ->first();
        abort_unless($participante, 404);

        $updates = array_filter([
            'numero_corredor' => $request->query('numero_corredor'),
            'chip'            => $request->query('chip'),
        ], fn ($v) => $v !== null);

        if ($updates) {
            $participante->update($updates);
        }

        // Aviso de numeración vs. género/edad real en entrega de kit
        // (16/09/2026) — antes el aviso solo llegaba a delivery en el
        // próximo sync del CSV completo; ahora se recalcula acá mismo, al
        // momento de la entrega (con o sin cambio de numeración), para que
        // el staff vea de inmediato si el número que está confirmando (el
        // que ya tenía, o el que acaba de corregir) corresponde a su
        // género/edad real.
        return response()->json([
            'success'          => true,
            'numeroCorredor'   => $participante->numero_corredor,
            'chip'             => $participante->chip,
            'alertaNumeracion' => NumeracionRangoChecker::alertaPara($participante, $evento) ?? '',
        ]);
    }

    /**
     * Cobro en sitio (12/08/2026) — ver
     * PRD-precios-periodos-fechas.md, sección 0. Push-back desde el POS de
     * retiro en sitio (elascenso/delivery) para inscripciones pendientes de
     * un form_type sin categoría (`requiere_categoria=false`), que ahora
     * cobra `precio_base` de verdad en vez de $0 — mismo patrón sin
     * sesión/CSRF que `actualizarNumeracionSitio()`, firma validada a mano.
     *
     * Reusa `RegistrationService::updatePaymentStatus()` a propósito (no un
     * update directo del modelo): es el mismo camino que usa la pasarela
     * real, así que dispara el correo de confirmación
     * (`notificarPagoConfirmado`) igual que cualquier otro pago — el
     * participante no debería notar la diferencia de haber pagado en el
     * mostrador en vez de por QR.
     *
     * Revalida la elegibilidad servidor-side (no confía en que la URL
     * firmada implique que sigue siendo válida — los datos pudieron
     * cambiar desde que se generó el CSV): si ya está `paid` (alguien pagó
     * por QR mientras tanto, o dos clicks en el mostrador), responde éxito
     * igual — es un no-op idempotente, no un error. Si el form_type
     * requiere categoría, rechaza — este camino es solo para el caso que
     * lo originó.
     */
    public function confirmarPagoSitio(Evento $evento, string $documento, RegistrationService $registrationService): JsonResponse
    {
        // Firma validada por el middleware `signed` de la ruta (sin query
        // string que ignorar acá, a diferencia de actualizarNumeracionSitio).
        $participante = Participante::with('registration.formType')
            ->whereHas('registration', fn (Builder $q) => $q->where('evento_id', $evento->id))
            ->where('numero_documento', $documento)
            ->first();
        abort_unless($participante, 404);

        $registration = $participante->registration;

        if ($registration->pago_status === 'paid') {
            return response()->json(['success' => true, 'pagoStatus' => 'paid']);
        }

        if ($registration->pago_status !== 'pending') {
            return response()->json([
                'success' => false,
                'error'   => "Esta inscripción está en estado '{$registration->pago_status}', no se puede confirmar el pago.",
            ], 422);
        }

        if (!$registration->formType || $registration->formType->requiere_categoria) {
            return response()->json([
                'success' => false,
                'error'   => 'Este tipo de inscripción requiere categoría — el cobro en sitio no aplica acá.',
            ], 422);
        }

        $registration = $registrationService->updatePaymentStatus($registration->referencia, 'paid');

        return response()->json(['success' => true, 'pagoStatus' => $registration->pago_status]);
    }
}
