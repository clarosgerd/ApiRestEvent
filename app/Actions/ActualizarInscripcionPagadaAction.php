<?php

namespace App\Actions;

use App\DTOs\RegistrationDTO;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Evento;
use App\Models\Registration;
use App\Models\RegistrationTotal;
use App\Services\RegistrationService;
use App\Support\CurrencyResolverData;
use App\Support\PrecioVigenteData;
use App\Support\Taller\ValidarSeleccionesTallerAction;
use Illuminate\Support\Facades\DB;

class ActualizarInscripcionPagadaAction
{
    public function __construct(
        private readonly RegistrationService $registrationService,
    ) {
    }

    /**
     * Actualizar inscripción pagada con costo adicional.
     *
     * $permiteCambioCategoria distingue los 2 flujos que llaman a esta
     * Action (25/08/2026, ver PLAN-EDICION-PAGADA-TALLERES-CATEGORIA-25082026.md):
     * - Autoservicio (RegistrationController::updatePaid(), default false):
     *   el participante solo puede AGREGAR talleres nuevos, nunca cambiar
     *   de categoría — el sistema no reembolsa diferencias, se resuelve
     *   en caja el día del evento.
     * - Caja (CajaController::editarPagada(), true): el cajero además
     *   puede cambiar de categoría, porque puede cobrar/desembolsar la
     *   diferencia en efectivo ahí mismo. En ningún caso se permite QUITAR
     *   un taller que ya estaba pagado.
     *
     * $requierePagoEnSitio marca, en los talleres NUEVOS que agregue esta
     * llamada, si todavía falta cobrarlos en efectivo (autoservicio con
     * "pagar en el evento" — true) o si ya están cobrados (Caja cobra en el
     * momento; SIP ya cobró online — ambos false). Ver
     * PLAN-COBRO-SIP-ADICIONAL-26082026.md — el reporte de talleres
     * necesita esta señal por fila para no mezclar plata ya cobrada con
     * plata pendiente de cobrar bajo "recaudación".
     *
     * @return array{registration: Registration, costo_adicion: float}
     */
    public function handle(string $reference, array $data, bool $permiteCambioCategoria = false, bool $requierePagoEnSitio = false): array
    {
        return DB::transaction(function () use ($reference, $data, $permiteCambioCategoria, $requierePagoEnSitio) {

            $registration = Registration::with('formType')
                ->where('referencia', $reference)
                ->firstOrFail();

            if ($registration->pago_status !== 'paid') {
                throw new \DomainException(
                    'Esta operación solo aplica a inscripciones pagadas.'
                );
            }

            $costoEdicion = $registration->formType->costo_edicion ?? 0;

            $this->registrationService->validateDuplicateParticipantsFromData($data);

            $this->registrationService->releasePromoCodes($registration->id);

            // Congresos con talleres (18/08/2026) — ver
            // brain/PLAN-CONGRESOS-TALLERES-HORARIOS-IMPLEMENTACION.md.
            // Mismo orden que ActualizarInscripcionAction: validación
            // ANTES del delete de participantes, para que la exclusión
            // del propio cupo funcione correctamente.
            $registrationDto = RegistrationDTO::fromArray(array_merge($data, [
                'evento_id'      => $registration->evento_id,
                'evento_nombre'  => $registration->evento_nombre,
                'pago_status'    => $registration->pago_status,
                'tipo_pago'      => $registration->tipo_pago,
                'pay_order_number' => $registration->pay_order_number,
                'referencia'     => $registration->referencia,
                'fecha'          => $registration->fecha?->toDateTimeString() ?? now()->toDateTimeString(),
                'form_types_id'  => $registration->form_types_id,
            ]));

            // Agregar talleres a una inscripción pagada (25/08/2026) — ver
            // PLAN-EDICION-PAGADA-TALLERES-CATEGORIA-25082026.md. Snapshot
            // del estado ANTERIOR (categoría/talleres), tomado antes del
            // delete de abajo — se correlaciona con $data['participantes']
            // por POSICIÓN (mismo criterio que ya asume el resto de esta
            // Action: no se agregan ni quitan personas en esta operación,
            // solo se modifican las que ya existen).
            //
            // Movido ANTES de ValidarSeleccionesTallerAction (02/09/2026) —
            // bug real en UAT: SIP cobró un pago adicional real (agregar un
            // taller nuevo) y ConfirmarPagoAdicionalAction rechazó igual la
            // aplicación con "El taller 'Bombas Elastoméricas' no está
            // disponible para inscripción en este momento" — ESE taller no
            // era el nuevo, era uno que el participante ya tenía pagado de
            // antes y que el organizador deshabilitó (permite_inscripcion)
            // después. Como un taller ya pagado NUNCA se puede quitar (ver
            // el chequeo un poco más abajo), revalidar su disponibilidad
            // actual en cada edición posterior era una contradicción sin
            // salida — el dinero ya cobrado por SIP quedaba en 'error' sin
            // poder aplicarse nunca. Ahora se arma acá arriba para pasarle a
            // ValidarSeleccionesTallerAction::run()/runCapacidad() qué
            // sesiones son "previas" (no sujetas a los chequeos de
            // disponibilidad, ya que no se les puede quitar).
            $participantesAnteriores = $registration->participants()
                ->with('talleresSesiones')
                ->orderBy('id')
                ->get();

            if ($participantesAnteriores->count() !== count($data['participantes'])) {
                throw new \DomainException(
                    'Esta operación no permite agregar ni quitar participantes, solo modificar los existentes.'
                );
            }

            $sesionIdsPreviasPorIndice = $participantesAnteriores
                ->map(fn ($anterior) => $anterior->talleresSesiones
                    ->pluck('sesion_congreso_id')
                    ->map(fn ($id) => (int) $id)
                    ->all())
                ->all();

            ValidarSeleccionesTallerAction::run($registrationDto, $sesionIdsPreviasPorIndice);
            ValidarSeleccionesTallerAction::runCapacidad($registrationDto, $registration->id, $sesionIdsPreviasPorIndice);

            $tallerIdsNuevosPorIndice = [];
            $pagoPendientePorIndiceYSesion = [];
            $deltaCategoria = 0.0;

            foreach ($data['participantes'] as $i => $participantData) {
                $anterior = $participantesAnteriores[$i];

                // Snapshot de pago_pendiente ANTES del delete de más abajo —
                // se restaura tal cual para los talleres que NO son nuevos
                // en esta llamada (createParticipantFromData() los recrea
                // sin el flag; sin este snapshot, un taller que ya estaba
                // marcado "pendiente de cobro" perdería esa marca en la
                // siguiente edición aunque nadie lo haya cobrado todavía).
                $pagoPendientePorIndiceYSesion[$i] = $anterior->talleresSesiones
                    ->pluck('pago_pendiente', 'sesion_congreso_id')
                    ->all();

                $categoriaNueva = (string) ($participantData['categoria'] ?? '');
                if ($categoriaNueva !== (string) $anterior->categoria) {
                    if (! $permiteCambioCategoria) {
                        throw new \DomainException(
                            'No se puede cambiar de categoría desde tu cuenta — esa diferencia se resuelve en caja el día del evento.'
                        );
                    }

                    // No se confía en el precio que manda el cliente para
                    // este cálculo (a diferencia del alta nueva, acá el
                    // resultado puede ser un desembolso real de dinero) —
                    // se resuelve el precio vigente real de la categoría
                    // nueva server-side, mismo helper que ya usa el resto
                    // del sistema para "Precios por período".
                    //
                    // Categorías por form_type (27/08/2026) — ver
                    // PLAN-CATEGORIAS-POR-FORM-TYPE-27082026.md. Antes acá
                    // se hacía `Category::findOrFail()` sin filtrar NI
                    // SIQUIERA por evento — aceptaba la categoría de
                    // cualquier evento del sistema. Ahora exige mismo
                    // evento y, si la categoría tiene `formulario_id`,
                    // mismo form_type que esta inscripción (null =
                    // compartida, sin cambios).
                    $categoriaModel = Category::where('id', (int) $categoriaNueva)
                        ->where('event_id', $registration->evento_id)
                        ->where(fn ($q) => $q->whereNull('formulario_id')->orWhere('formulario_id', $registration->form_types_id))
                        ->first();

                    if (!$categoriaModel) {
                        throw new \DomainException(
                            "La categoría '{$categoriaNueva}' no es válida para este evento/tipo de formulario."
                        );
                    }

                    $precioNuevo = PrecioVigenteData::paraCategoria($categoriaModel)['precio'];
                    $deltaCategoria += $precioNuevo - (float) $anterior->precio_categoria;
                }

                $idsAnteriores = $anterior->talleresSesiones->pluck('sesion_congreso_id')->map(fn ($id) => (int) $id)->all();
                $idsNuevos = collect($participantData['talleres'] ?? [])->pluck('sesion_congreso_id')->map(fn ($id) => (int) $id)->all();

                if (! empty(array_diff($idsAnteriores, $idsNuevos))) {
                    throw new \DomainException('No se pueden quitar talleres que ya fueron pagados.');
                }

                $tallerIdsNuevosPorIndice[$i] = array_diff($idsNuevos, $idsAnteriores);
            }

            $registration->participants()->delete();
            $registration->totals()->delete();

            // Revalidación de stock (13/08/2026) — ver
            // PLAN-STOCK-SOUVENIRS-SIMPLES-13082026.md punto 2. Después del
            // delete de arriba (el propio consumo anterior de esta
            // inscripción ya no cuenta) y antes de recrear los
            // participantes.
            $this->registrationService->validateStockForParticipants($data['participantes']);

            foreach ($data['participantes'] as $participantData) {
                $this->registrationService->createParticipantFromData($registration, $participantData);
            }

            // Costo real de los talleres agregados (25/08/2026) — a
            // diferencia de la categoría, acá sí se confía en el precio ya
            // persistido (`total` de ParticipanteTallerSesion), porque
            // createParticipantFromData() ya lo resolvió server-side contra
            // el taller/sesión real, no contra un valor mandado por el
            // cliente. Se correlaciona por posición contra
            // $tallerIdsNuevosPorIndice armado antes del delete.
            $deltaTalleres = 0.0;
            $participantesNuevos = $registration->participants()
                ->with('talleresSesiones')
                ->orderBy('id')
                ->get();
            foreach ($participantesNuevos as $i => $participanteNuevo) {
                $idsAgregados = $tallerIdsNuevosPorIndice[$i] ?? [];
                if (! empty($idsAgregados)) {
                    $deltaTalleres += (float) $participanteNuevo->talleresSesiones
                        ->whereIn('sesion_congreso_id', $idsAgregados)
                        ->sum('total');
                }

                // Reporte de talleres confiable (27/08/2026) — restaura el
                // pago_pendiente que tenía cada taller ya existente (el
                // delete de arriba lo perdió) y marca los recién agregados
                // según $requierePagoEnSitio (ver doc del método). Update
                // puntual fila por fila — la cantidad de talleres por
                // participante es siempre chica, no vale la pena un query
                // masivo.
                $pagoPendienteAnterior = $pagoPendientePorIndiceYSesion[$i] ?? [];
                foreach ($participanteNuevo->talleresSesiones as $pts) {
                    $esNuevo = in_array((int) $pts->sesion_congreso_id, $idsAgregados, true);
                    $pendiente = $esNuevo
                        ? $requierePagoEnSitio
                        : (bool) ($pagoPendienteAnterior[$pts->sesion_congreso_id] ?? false);
                    if ($pendiente !== (bool) $pts->pago_pendiente) {
                        $pts->update(['pago_pendiente' => $pendiente]);
                    }
                }
            }

            ValidarSeleccionesTallerAction::runRequeridos($registrationDto);

            $this->registrationService->deactivateFormTypeIfCupoLleno($registration->form_types_id);

            // Inscripción en BOB y USD (18/08/2026) — ver
            // brain/PLAN-INSCRIPCION-BOB-USD-IMPLEMENTACION.md. Mismo
            // criterio que ActualizarInscripcionAction: validar y
            // normalizar el snapshot de moneda/tasa. En este path la
            // inscripción ya está pagada, así que la moneda probablemente
            // ya está persistida — si el cliente la cambia (algo que
            // solo debería permitir el admin) se revalida y se acepta
            // o se rechaza.
            $eventoRef = Evento::find($registration->evento_id);
            $monedaRef = strtoupper((string) ($data['moneda_pago'] ?? ($registration->moneda_pago ?? 'BOB')));
            $tasaRef   = isset($data['tipo_cambio_aplicado']) ? (float) $data['tipo_cambio_aplicado'] : null;
            $totalRef  = isset($data['total_pagado']) ? (float) $data['total_pagado'] : null;
            if ($monedaRef === 'USD' && (! $eventoRef || ! $eventoRef->acepta_usd)) {
                throw new \DomainException('Este evento solo acepta pago en BOB.');
            }
            // Precio USD fijo (19/08/2026) — ver
            // brain/PLAN-PRECIO-USD-FIJO-19082026.md.
            $resuelto  = ($monedaRef === 'USD' && $eventoRef && $eventoRef->usd_precio_fijo)
                ? CurrencyResolverData::resolverPrecioFijo($registrationDto, $eventoRef)
                : CurrencyResolverData::resolver(
                    (float) $data['totales']['grand_total'],
                    $monedaRef,
                    $tasaRef,
                    $totalRef,
                );
            $registration->moneda_pago        = $monedaRef;
            $registration->tipo_cambio_aplicado = $resuelto['tipo_cambio_aplicado'];
            $registration->total_pagado       = $resuelto['total_pagado'];
            $registration->save();

            RegistrationTotal::create([
                'registration_id' => $registration->id,
                'inscripcion'     => $data['totales']['inscripcion'],
                'donacion'        => $data['totales']['donacion'],
                'souvenirs'       => $data['totales']['souvenirs'],
                // Congresos con talleres (18/08/2026).
                'talleres'        => $data['totales']['talleres'] ?? 0,
                'fee'             => $data['totales']['fee'],
                'descuento'       => $data['totales']['descuento'],
                'descuento_registrante' => $data['totales']['descuento_registrante'] ?? 0,
                'grand_total'     => $data['totales']['grand_total'],
            ]);

            $this->registrationService->syncPersonas($registration);

            // Monto real a cobrar/desembolsar (25/08/2026) — el cargo fijo
            // de siempre (costo_edicion) más la diferencia real de
            // categoría (0 si no cambió, o si el autoservicio la bloqueó
            // arriba) y el precio real de los talleres agregados. Puede
            // quedar negativo si el cambio de categoría es una devolución
            // mayor que el resto — ver CajaController::editarPagada(), que
            // ahora también registra un CajaMovimiento cuando es negativo.
            $costoAdicion = (float) $costoEdicion + $deltaTalleres + $deltaCategoria;

            AuditLog::create([
                'registration_id' => $registration->id,
                'usuario'         => $data['_usuario'] ?? null,
                'costo_adicion'   => $costoAdicion,
            ]);

            return [
                'registration'  => $this->registrationService->loadRelations($registration),
                'costo_adicion' => $costoAdicion,
            ];
        });
    }
}
