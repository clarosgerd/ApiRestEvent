<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\AuthorizesEventoScope;
use App\Http\Controllers\Admin\Concerns\DelegatesToApiJson;
use App\Http\Controllers\Controller;
use App\Http\Controllers\EventoController as ApiEventoController;
use App\Http\Controllers\ParticipanteController as ApiParticipanteController;
use App\Http\Controllers\RegistrationController as ApiRegistrationController;
use App\Models\Evento;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Reporte detallado de inscritos (15/08/2026) — pantalla de solo lectura a
 * la que se llega desde las tarjetas de totales del Dashboard de
 * inscripciones, filtrable por estado de pago.
 *
 * Consume ParticipanteController::porEvento() en proceso (sin HTTP), igual
 * que el resto del monolito. El formato del CSV y las columnas por tipo de
 * evento son los mismos que en admin-eventos (ParticipantesDetalleController):
 * ver el commit de origen de cada bloque en el historial del repo admin-eventos.
 */
class ParticipantesDetalleController extends Controller
{
    use AuthorizesEventoScope;

    use DelegatesToApiJson;

    private const PER_PAGE_DEFAULT = 50;
    private const PER_PAGE_MAX = 200;

    public function index(Request $request, Evento $event, ApiEventoController $apiEvento, ApiParticipanteController $apiParticipante): View
    {
        $this->assertCanViewEvento($event->id);

        $eventoData = $apiEvento->show($event)->getData(true)['eventos'] ?? null;
        abort_if(!$eventoData, 404);

        [$categoria, $pagoStatus, $perPage, $page, $search] = $this->filtrosDesde($request);

        $request->merge(array_filter([
            'categoria' => $categoria !== '' ? $categoria : null,
            'pago_status' => $pagoStatus !== '' ? $pagoStatus : null,
            'per_page' => $perPage,
            'page' => $page,
            'search' => $search !== '' ? $search : null,
        ]));

        $payload = $apiParticipante->porEvento($request, $event)->getData(true);
        abort_if(!($payload['success'] ?? false), 502, 'No se pudo cargar el detalle de inscritos.');

        $participantes = collect($payload['participantes'] ?? [])
            ->map(fn (array $p) => $p + ['poleraTalla' => $this->tallaPolera($p['polera'] ?? null)])
            ->all();

        return view('admin.eventos.participantes-detalle', [
            'evento' => $eventoData,
            // Carrera vs congreso: numeración y distancia solo aplican a carreras.
            'usaNumeracion' => $this->esCarrera($eventoData),
            // Equipo: solo carreras, y solo si algún formulario del evento tiene has_team.
            'mostrarEquipo' => $this->esCarrera($eventoData)
                && collect($participantes)->contains(fn ($p) => ($p['eventoConEquipo'] ?? false) === true),
            // Talla de polera: solo carreras con souvenir de polera en el evento.
            'mostrarPolera' => $this->esCarrera($eventoData)
                && collect($participantes)->contains(fn ($p) => ($p['eventoConPolera'] ?? false) === true),
            // Descuento grupal: depende del flag del formulario, no del tipo de evento.
            'mostrarGrupal' => collect($participantes)->contains(fn ($p) => ($p['eventoConGrupal'] ?? false) === true),
            'categoriaSeleccionada' => $categoria,
            'pagoStatusSeleccionado' => $pagoStatus,
            'searchSeleccionado' => $search,
            'participantes' => $participantes,
            'meta' => $payload['meta'] ?? null,
        ]);
    }

    /**
     * Conciliación manual de "Pago pendiente (USD)" — delega en
     * RegistrationController::confirmarPagoManual(), que revalida
     * tipo_pago/pago_status y assertCanWriteEvento().
     */
    public function confirmarPagoManual(Request $request, Evento $event, string $referencia, ApiRegistrationController $apiRegistration): RedirectResponse
    {
        $this->assertCanViewEvento($event->id);

        $payload = $apiRegistration->confirmarPagoManual($referencia)->getData(true);

        if (!($payload['success'] ?? false)) {
            return back()->withErrors($this->extractErrors($payload));
        }

        return back()->with('status', "Pago confirmado — referencia {$referencia}.");
    }

    public function csvDownload(Request $request, Evento $event, ApiEventoController $apiEvento, ApiParticipanteController $apiParticipante): Response
    {
        $this->assertCanViewEvento($event->id);

        [$categoria, $pagoStatus, , , $search] = $this->filtrosDesde($request);

        // `categoria` viaja como ID; se resuelve el nombre para que la columna sea legible.
        $eventoData = $apiEvento->show($event)->getData(true)['eventos'] ?? [];
        $categoriasPorId = collect($eventoData['categories'] ?? [])->keyBy(fn ($c) => (string) $c['id']);

        // 3 edades para recategorización + carga a ChronoTrack: a la fecha del
        // evento, a fin de año, y la de hoy. El staff decide cuál aplica.
        $fechaEvento = $eventoData['date'] ?? null;
        $finDeAnioEvento = $fechaEvento ? Carbon::parse($fechaEvento)->endOfYear() : null;

        $usaNumeracion = $this->esCarrera($eventoData);

        // Sin per_page a propósito: la descarga CSV es una acción explícita.
        $request->merge(array_filter([
            'categoria' => $categoria !== '' ? $categoria : null,
            'pago_status' => $pagoStatus !== '' ? $pagoStatus : null,
            'search' => $search !== '' ? $search : null,
        ]));
        $payload = $apiParticipante->porEvento($request, $event)->getData(true);
        abort_if(!($payload['success'] ?? false), 502, 'No se pudo generar el archivo.');

        $participantes = $payload['participantes'] ?? [];

        $handle = fopen('php://temp', 'w+');
        fwrite($handle, "\xEF\xBB\xBF");

        // Mismo formato que los reportes legacy: MAYÚSCULAS, N° correlativo,
        // FORMA DE PAGO, OBSERVACIONES y una columna por pregunta "En reporte".
        // Carrera: IMPORTE sin polera + IMPORTE_POLERA; DISTANCIA y CATEGORIA (grupo de edad).
        // Congreso: IMPORTE_TALLER y DEN. (título = alias) antes de NOMBRE.
        // IMPORTE_TOTAL no cambia (sigue = importe + polera + taller).
        $preguntas = collect($participantes[0]['respuestas'] ?? [])->values();

        $mostrarEquipo = $usaNumeracion && collect($participantes)->contains(fn ($p) => ($p['eventoConEquipo'] ?? false) === true);
        $mostrarPolera = $usaNumeracion && collect($participantes)->contains(fn ($p) => ($p['eventoConPolera'] ?? false) === true);
        $mostrarGrupal = collect($participantes)->contains(fn ($p) => ($p['eventoConGrupal'] ?? false) === true);

        fputcsv($handle, [
            'N°',
            ...($usaNumeracion ? ['NUMERO_CORREDOR'] : []),
            'ESTADO',
            'IMPORTE',
            ...($usaNumeracion ? ['IMPORTE_POLERA'] : ['IMPORTE_TALLER']),
            'IMPORTE_TOTAL',
            'PROMO_CODIGO', 'PROMO_DESCUENTO',
            ...($mostrarGrupal ? ['DESCUENTO_GRUPAL'] : []),
            'NUMERO_DOCUMENTO',
            ...($usaNumeracion ? [] : ['DEN.']),
            'NOMBRE', 'APELLIDO',
            ...($usaNumeracion ? ['ALIAS'] : []),
            ...($mostrarEquipo ? ['EQUIPO'] : []),
            ...($mostrarPolera ? ['POLERA'] : []),
            'SEXO', 'CELULAR', 'FECHA_INSCRIPCION', 'REFERENCIA', 'NACIMIENTO',
            ...($usaNumeracion ? ['DISTANCIA', 'CATEGORIA'] : ['CATEGORIA']),
            'EDAD_FECHA', 'EDAD_FIN_DE_ANIO', 'EDAD_HOY',
            'FORMA DE PAGO', 'OBSERVACIONES',
            ...$preguntas->map(fn ($r) => mb_strtoupper($r['etiqueta']))->all(),
        ]);
        foreach ($participantes as $i => $p) {
            [$edadEvento, $edadFinDeAnio, $edadHoy] = $this->edades($p['fechaNacimiento'] ?? null, $fechaEvento, $finDeAnioEvento);
            $importePolera = (float) ($p['importePolera'] ?? 0);
            $importeCarrera = $usaNumeracion ? round((float) $p['importe'] - $importePolera, 2) : $p['importe'];
            $distancia = $categoriasPorId[$p['categoria']]['name'] ?? $p['categoria'];

            fputcsv($handle, [
                $i + 1,
                ...($usaNumeracion ? [$p['numeroCorredor']] : []),
                $this->estadoLabel($p['pagoStatus']),
                $importeCarrera,
                ...($usaNumeracion ? [$importePolera] : [$p['importeTaller'] ?? 0]),
                $p['importeTotal'] ?? $p['importe'],
                $p['promoCodigo'] ?? '', $p['promoDescuento'] ?? 0,
                ...($mostrarGrupal ? [$p['descuentoGrupal'] ?? 0] : []),
                $p['numeroDocumento'],
                ...($usaNumeracion ? [] : [$p['alias'] ?? '']),
                $p['nombre'], $p['apellido'],
                ...($usaNumeracion ? [$p['alias'] ?? ''] : []),
                ...($mostrarEquipo ? [$p['equipo'] ?? ''] : []),
                ...($mostrarPolera ? [$this->tallaPolera($p['polera'] ?? null)] : []),
                $p['genero'], $p['telefono'],
                $p['fechaInscripcion'], $p['referencia'], $p['fechaNacimiento'],
                ...($usaNumeracion ? [$distancia, $p['categoriaRecalculada'] ?? ''] : [$distancia]),
                $edadEvento, $edadFinDeAnio, $edadHoy,
                $this->formaPagoLabel($p['tipoPago'] ?? null),
                '',
                ...collect($p['respuestas'] ?? [])->pluck('valor')->all(),
            ]);
        }
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        $filename = 'detalle-inscritos-evento-'.$event->id.($pagoStatus !== '' ? '-'.$pagoStatus : '').'.csv';

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    private function filtrosDesde(Request $request): array
    {
        $categoria = $request->query('categoria', '');
        $pagoStatus = $request->query('pago_status', '');
        $perPage = min((int) $request->query('per_page', self::PER_PAGE_DEFAULT), self::PER_PAGE_MAX);
        $page = max((int) $request->query('page', 1), 1);
        $search = trim((string) $request->query('search', ''));

        return [$categoria, $pagoStatus, $perPage, $page, $search];
    }

    /**
     * Talla de polera. El API devuelve el centinela legacy 'No shirt' cuando
     * el participante no eligió polera: se muestra vacío.
     */
    private function tallaPolera(?string $talla): string
    {
        $limpia = trim((string) $talla);

        return strcasecmp($limpia, 'No shirt') === 0 ? '' : $limpia;
    }

    /**
     * Carrera = cualquier tipo de evento distinto de "Congreso / No aplica".
     */
    private function esCarrera(array $evento): bool
    {
        return mb_strtolower(trim((string) ($evento['tipoEvento'] ?? ''))) !== 'congreso / no aplica';
    }

    /**
     * FORMA DE PAGO con las etiquetas del reporte legacy. Valores desconocidos
     * salen tal cual en mayúsculas, sin perder el dato.
     */
    private function formaPagoLabel(?string $tipoPago): string
    {
        $clave = mb_strtolower(trim((string) $tipoPago));

        return match ($clave) {
            '' => '',
            'sip' => 'QR SIP',
            'qr' => 'QR',
            'multipago' => 'QR MULTIPAGO',
            'efectivo' => 'EFECTIVO',
            'organizador' => 'DIRECTO ORG.',
            'cortesía', 'cortesia' => 'CORTESIA',
            'depósito', 'deposito' => 'DEPOSITO',
            'pendiente' => 'PENDIENTE',
            'pendiente_usd' => 'PENDIENTE USD',
            'gratis' => 'GRATIS',
            'externo' => 'EXTERNO',
            'legado' => 'LEGADO',
            'excel' => 'EXCEL',
            default => mb_strtoupper($clave),
        };
    }

    private function estadoLabel(string $pagoStatus): string
    {
        return match ($pagoStatus) {
            'paid' => 'Pagado',
            'pending' => 'Pendiente',
            'cancelled' => 'Cancelado',
            'failed' => 'Fallido',
            default => $pagoStatus,
        };
    }

    /**
     * Las 3 edades del reporte. Devuelve '' cuando falta el dato de origen
     * en vez de un 0 engañoso.
     *
     * @return array{0: int|string, 1: int|string, 2: int|string}
     */
    private function edades(?string $fechaNacimiento, ?string $fechaEvento, ?Carbon $finDeAnioEvento): array
    {
        if (!$fechaNacimiento) {
            return ['', '', ''];
        }

        $nacimiento = Carbon::parse($fechaNacimiento);

        $edadEvento = $fechaEvento ? (int) $nacimiento->diffInYears(Carbon::parse($fechaEvento)) : '';
        $edadFinDeAnio = $finDeAnioEvento ? (int) $nacimiento->diffInYears($finDeAnioEvento) : '';
        $edadHoy = (int) $nacimiento->diffInYears(Carbon::today());

        return [$edadEvento, $edadFinDeAnio, $edadHoy];
    }
}
