<?php

namespace App\Services;

use App\Http\Controllers\EventoController;
use App\Models\Persona;
use App\Models\Registration;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Consolidación monolito (22/08/2026), Fase 2b — port de
 * `elascenso-blade\App\Services\RegistroValidacionService`. Se mantiene
 * A PROPÓSITO como capa de prevalidación/UX temprana (mensajes de error
 * claros antes de llegar al create real) — decisión explícita del usuario
 * al confirmar el enfoque de esta sub-fase, ver
 * [[project_plan_consolidacion_monolito]]. `CrearInscripcionAction` sigue
 * siendo la fuente de verdad y revalida todo de nuevo (defensa en
 * profundidad, documentada abajo en cada chequeo "temprano").
 *
 * Los 3 métodos que antes hacían una llamada HTTP real a ApiRestEvent
 * (`fetchExternalRegistro`, `fetchExternalEvento`,
 * `resolverPersonaRegistrante`) pasan a resolver directo contra el modelo/
 * controller in-process — esa es la única lógica de negocio que cambia acá;
 * `resolverFormType()`, `validarYCalcularParticipantes()` y
 * `calcularTotalUsdFijo()` son puro cálculo sobre arrays ya en memoria, sin
 * ningún `forward()` que eliminar, así que se portan verbatim.
 */
class RegistroValidacionService
{
    public function fetchExternalRegistro(string $referencia): ?array
    {
        return Registration::where('referencia', $referencia)->first()?->toArray();
    }

    /**
     * Categoría/precio/código promo que cada participante YA tenía antes de
     * esta edición (16/10/2026) — port de
     * `elascenso/event\api\_registro_validacion.php::categoriasAnterioresDesdeRegistro()`,
     * nunca portado a este servicio pese a que el fix original (categoría,
     * 31/08/2026) es anterior a esta Fase 2b (22/08/2026)... en rigor el fix
     * es POSTERIOR al port, por eso faltaba — ver
     * [[project_promocode_grandfather_edicion_propia]]. Array indexado por
     * POSICIÓN (mismo criterio de correlación que ya usa
     * `ActualizarInscripcionPagadaAction`), o `null` si la inscripción no
     * existe o no tiene participantes. Devuelve las columnas crudas del
     * modelo (`categoria`, `precio_categoria`, `promo_codigo`), no el JSON
     * camelCase de `ParticipanteResource` — `validarYCalcularParticipantes()`
     * abajo las usa con esos mismos nombres.
     */
    public function categoriasAnterioresDesdeRegistro(string $referencia): ?array
    {
        $registration = Registration::with('participants')->where('referencia', $referencia)->first();
        if (! $registration || $registration->participants->isEmpty()) {
            return null;
        }

        $anteriores = [];
        foreach ($registration->participants as $idx => $p) {
            $anteriores[$idx] = [
                'categoria' => $p->categoria,
                'precioCategoria' => $p->precio_categoria !== null ? (float) $p->precio_categoria : null,
                'promoCodigo' => $p->promo_codigo,
            ];
        }

        return $anteriores;
    }

    public function fetchExternalEvento(string $eventoId): ?array
    {
        $evento = \App\Models\Evento::find($eventoId);
        if (! $evento) {
            return null;
        }

        $decoded = app(EventoController::class)->show($evento)->getData(true);

        return empty($decoded['success']) || ! isset($decoded['eventos']) ? null : $decoded['eventos'];
    }

    /**
     * Resuelve un token Bearer de Persona directo contra Sanctum (mismo
     * mecanismo que `GET /persona/me`, sin el salto HTTP) — el
     * `auth_token` viaja como campo del BODY (no header), igual que el
     * original: es el mismo contrato que ya usaba `elascenso/event`.
     */
    public function resolverPersonaRegistrante(string $authToken): ?array
    {
        $authToken = trim($authToken);
        if ($authToken === '') {
            return null;
        }

        $token = PersonalAccessToken::findToken($authToken);
        if (! $token || ! $token->tokenable instanceof Persona) {
            return null;
        }

        return $token->tokenable->toArray();
    }

    /**
     * Resuelve el tipo de formulario dentro de un evento ya cargado.
     *
     * @return array{formType: array|null}|array{error: string}
     */
    public function resolverFormType(array $evento, mixed $formTypeId): array
    {
        $formType = null;
        if ($formTypeId !== null) {
            foreach (($evento['formTypes'] ?? []) as $ft) {
                if ((string) $ft['id'] === (string) $formTypeId) {
                    $formType = $ft;
                    break;
                }
            }
        }
        if ($formType === null && ! empty($evento['formTypes'])) {
            return ['error' => 'Tipo de formulario no válido para este evento.'];
        }

        return ['formType' => $formType];
    }

    /**
     * Precio por categoría de un combo (13/10/2026, pedido del usuario) — el
     * mismo combo (mismos talleres) puede costar distinto según la categoría
     * del participante. Gana una fila específica de esa categoría; si no hay,
     * la fila default (categoriaId null); si no hay ninguna de las dos,
     * `null` (el combo no aplica a esa categoría — no hay "precio base" al
     * que caer). Mismo criterio que TallerCombo::precioParaCategoria()
     * (ApiRestEvent).
     *
     * USD fijo (13/10/2026, pedido del usuario) — `$usarPriceUsd=true` lee
     * `priceUsd` en vez de `precio`; `null` también si la fila encontrada no
     * tiene `priceUsd` cargado (mismo criterio que un taller sin precio USD:
     * nunca se cobra de menos en silencio).
     */
    private function precioComboParaCategoria(array $combo, mixed $categoriaId, bool $usarPriceUsd = false): ?float
    {
        $precios = $combo['precios'] ?? [];
        $campo = $usarPriceUsd ? 'priceUsd' : 'precio';
        $default = null;

        foreach ($precios as $p) {
            if ($p['categoriaId'] === null) {
                $default = isset($p[$campo]) && $p[$campo] !== null ? (float) $p[$campo] : null;
                continue;
            }
            if ($categoriaId !== null && ctype_digit((string) $categoriaId) && (int) $p['categoriaId'] === (int) $categoriaId) {
                return isset($p[$campo]) && $p[$campo] !== null ? (float) $p[$campo] : null;
            }
        }

        return $default;
    }

    /**
     * Precio vigente de un taller por categoría (13/10/2026, pedido del
     * usuario) — mismo criterio que precioComboParaCategoria(), pero para
     * períodos de precio por fecha de un taller. ApiRestEvent ya resolvió
     * "hoy" para cada track (general + cada categoría con período propio) en
     * TallerResource::precioVigentePorCategoria — este servicio solo hace la
     * misma búsqueda plana específica→default, nunca el algoritmo de fechas
     * (eso vive solo en PrecioVigenteTallerData, ApiRestEvent).
     *
     * A diferencia de los combos, la fila general (categoriaId null) siempre
     * está presente — si por algún motivo no lo está (payload viejo/caché),
     * cae a `precioVigente`/`precioUsdVigente` planos como red de seguridad.
     */
    private function precioVigenteParaCategoria(array $taller, mixed $categoriaId, bool $usarPriceUsd = false): ?float
    {
        $filas = $taller['precioVigentePorCategoria'] ?? null;
        $campo = $usarPriceUsd ? 'priceUsd' : 'precio';

        if ($filas === null) {
            $campoPlano = $usarPriceUsd ? 'precioUsdVigente' : 'precioVigente';

            return isset($taller[$campoPlano]) && $taller[$campoPlano] !== null ? (float) $taller[$campoPlano] : null;
        }

        $default = null;
        foreach ($filas as $fila) {
            if ($fila['categoriaId'] === null) {
                $default = isset($fila[$campo]) && $fila[$campo] !== null ? (float) $fila[$campo] : null;
                continue;
            }
            if ($categoriaId !== null && ctype_digit((string) $categoriaId) && (int) $fila['categoriaId'] === (int) $categoriaId) {
                return isset($fila[$campo]) && $fila[$campo] !== null ? (float) $fila[$campo] : null;
            }
        }

        return $default;
    }

    /**
     * Combos de talleres (12/10/2026, pedido del usuario) — "Taller A + Taller
     * B por el precio de 1" o "por $300 fijo". Réplica en PHP del mismo
     * algoritmo que ResolverCombosTallerAction en ApiRestEvent: un combo se
     * aplica si TODOS sus talleres miembro están entre las selecciones
     * (subconjunto), eligiendo entre combos superpuestos el de mayor ahorro
     * total (greedy, sin reusar un taller ya consumido), repartiendo el
     * descuento proporcional al unit_price de cada ítem con el resto de
     * redondeo en el último para que la suma cierre exacta.
     *
     * Es una capa de preview/defensa en profundidad — quien decide y persiste
     * de verdad qué se cobra es ApiRestEvent (RegistrationService::
     * createParticipantFromData(), que NUNCA confía en el unit_price que
     * mande este servicio, y que además excluye del matching cualquier
     * sesión ya persistida de una edición anterior, para que un combo nunca
     * se aplique retroactivo sobre algo ya cobrado — ver el snapshot-lock
     * ahí).
     *
     * USD fijo (13/10/2026, pedido del usuario) — `$usarPriceUsd=true` corre
     * exactamente el mismo matching/reparto, pero sobre `unit_price` en USD y
     * el precio del combo en USD — independiente del resultado en Bs (mismo
     * criterio ya existente para talleres individuales). Un combo cuya fila
     * de precio no tiene `priceUsd` cargado tampoco es candidato en ese modo.
     */
    private function resolverCombosTaller(array $selecciones, array $tallerCombos, mixed $categoriaId = null, bool $usarPriceUsd = false): array
    {
        foreach ($selecciones as &$s) {
            $s['discount'] = 0.0;
            $s['total'] = round($s['unit_price'], 2);
            $s['taller_combo_id'] = null;
        }
        unset($s);

        $combosVigentes = [];
        foreach ($tallerCombos as $combo) {
            if (empty($combo['vigente'])) {
                continue;
            }
            $precio = $this->precioComboParaCategoria($combo, $categoriaId, $usarPriceUsd);
            if ($precio === null) {
                continue;
            }
            $combo['_precioResuelto'] = $precio;
            $combosVigentes[] = $combo;
        }

        if (empty($combosVigentes)) {
            return $selecciones;
        }

        $indicePorTaller = [];
        foreach ($selecciones as $i => $s) {
            $tid = $s['taller_id'];
            if (! isset($indicePorTaller[$tid]) || $s['unit_price'] < $selecciones[$indicePorTaller[$tid]]['unit_price']) {
                $indicePorTaller[$tid] = $i;
            }
        }

        $candidatos = [];
        foreach ($combosVigentes as $combo) {
            $tallerIdsCombo = $combo['tallerIds'] ?? [];
            sort($tallerIdsCombo);
            if (empty($tallerIdsCombo)) {
                continue;
            }

            $todosPresentes = true;
            foreach ($tallerIdsCombo as $tid) {
                if (! isset($indicePorTaller[$tid])) {
                    $todosPresentes = false;
                    break;
                }
            }
            if (! $todosPresentes) {
                continue;
            }

            $suma = 0.0;
            foreach ($tallerIdsCombo as $tid) {
                $suma += $selecciones[$indicePorTaller[$tid]]['unit_price'];
            }

            $candidatos[] = [
                'combo'     => $combo,
                'tallerIds' => $tallerIdsCombo,
                'suma'      => $suma,
                'ahorro'    => $suma - (float) $combo['_precioResuelto'],
            ];
        }

        usort($candidatos, function ($a, $b) {
            if ($a['ahorro'] == $b['ahorro']) {
                return $a['combo']['id'] <=> $b['combo']['id'];
            }

            return $b['ahorro'] <=> $a['ahorro'];
        });

        $consumidos = [];
        foreach ($candidatos as $c) {
            if (array_intersect($c['tallerIds'], $consumidos)) {
                continue;
            }

            $descuentoTotal = round(max(0.0, $c['ahorro']), 2);
            $indices = array_map(fn ($tid) => $indicePorTaller[$tid], $c['tallerIds']);
            $ultimo = count($indices) - 1;
            $acumulado = 0.0;

            foreach ($indices as $pos => $idx) {
                $unit = $selecciones[$idx]['unit_price'];

                if ($pos === $ultimo) {
                    $discount = round($descuentoTotal - $acumulado, 2);
                } elseif ($c['suma'] > 0) {
                    $discount = round($descuentoTotal * $unit / $c['suma'], 2);
                } else {
                    $discount = 0.0;
                }
                $acumulado += $discount;

                $selecciones[$idx]['discount'] = $discount;
                $selecciones[$idx]['total'] = round($unit - $discount, 2);
                $selecciones[$idx]['taller_combo_id'] = $c['combo']['id'];
            }

            $consumidos = array_merge($consumidos, $c['tallerIds']);
        }

        return $selecciones;
    }

    /**
     * Revalida cada participante contra el evento/tipo de formulario y
     * recalcula totales en el servidor.
     *
     * @return array{participantes: array, totales: array}|array{error: string}
     */
    public function validarYCalcularParticipantes(array $evento, ?array $formType, array $participantes, ?array $categoriasAnteriores = null, bool $exigirPrecioAnteriorSiSinCambios = true): array
    {
        $permiteGrupal = ! empty($formType['permite_inscripcion_grupal'] ?? null);
        $maxGrupo = (int) ($formType['max_integrantes_grupo'] ?? 0);
        $descuentoPct = (float) ($formType['descuento_registrante_pct'] ?? 0);

        if ($permiteGrupal && $maxGrupo > 0 && count($participantes) > $maxGrupo) {
            return ['error' => "Máximo {$maxGrupo} participantes por inscripción."];
        }

        $totalInscripcion = 0.0;
        $totalDonacion = 0.0;
        $totalSouvenirs = 0.0;
        // Cargo de servicio por souvenir individual (01/09/2026,
        // sincronizado 14/09/2026) — suma aparte solo de los souvenirs con
        // `aplica_cargo_servicio` true (ej. una polera con costo real), se
        // usa para la base del fee más abajo, separado de $totalSouvenirs
        // (que sigue siendo el total mostrado, afuera del fee por default).
        // Ver ApiRestEvent::CrearInscripcionAction::validateFeePct(),
        // fuente de verdad de este mismo cálculo.
        $totalSouvenirsConCargo = 0.0;
        $totalDescuento = 0.0;
        $totalTalleres = 0.0;
        $talleresConCosto = ! empty($evento['talleresConCosto']);
        $usdPrecioFijo = ! empty($evento['usdPrecioFijo']);
        $talleresById = [];
        foreach (($evento['talleres'] ?? []) as $t) {
            $talleresById[(string) $t['id']] = $t;
        }
        $participantesValidos = [];

        foreach ($participantes as $idx => $p) {
            // Alias/Dirección/Ciudad pasaron a opcionales (31/08/2026,
            // sincronizado 14/09/2026) — no hacen a la identidad de la
            // persona. Ver PLAN-GENERO-CATALOGO-CAMPOS-OPCIONALES-31082026.md.
            $required = ['nombre', 'apellido', 'genero', 'correo', 'categoria', 'precioCategoria'];
            foreach ($required as $campo) {
                if (empty($p[$campo])) {
                    return ['error' => "Campo '{$campo}' requerido en participante ".($idx + 1).'.'];
                }
            }

            // Categoría sin cambios respecto a la edición anterior
            // (16/10/2026) — port del fix del 31/08/2026 de
            // elascenso/event: no revalidar contra el precio de hoy, exigir
            // que siga siendo el mismo que ya tenía. Ver docblock de
            // categoriasAnterioresDesdeRegistro().
            $anterior = is_array($categoriasAnteriores) ? ($categoriasAnteriores[$idx] ?? null) : null;
            $categoriaSinCambios = $anterior !== null
                && (string) $anterior['categoria'] === (string) $p['categoria'];

            if ($categoriaSinCambios && $exigirPrecioAnteriorSiSinCambios) {
                if ((float) $anterior['precioCategoria'] !== (float) $p['precioCategoria']) {
                    return ['error' => 'El precio de la categoría no coincide con el que ya tenías. Recargá la página e intentá de nuevo.'];
                }
            } else {
                $catValida = false;
                foreach (($evento['categories'] ?? []) as $cat) {
                    if ((string) $cat['id'] === (string) $p['categoria'] && (float) $cat['price'] === (float) $p['precioCategoria']) {
                        $catValida = true;
                        break;
                    }
                }
                if (! $catValida) {
                    return ['error' => "Categoría '{$p['categoria']}' no válida para este evento."];
                }
            }

            $conPolera = ! empty($p['polera']) && $p['polera'] !== 'No shirt';
            $precioPoleraValidado = $conPolera ? (float) ($formType['costo_polera'] ?? 0) : 0.0;

            $inscripcion = (float) ($p['precioCategoria'] ?? 0) + $precioPoleraValidado;
            $donacion = ! empty($formType['hasDonation']) ? max(0, (float) ($p['donacion'] ?? 0)) : 0.0;
            $souvenirsTotal = 0.0;
            $souvenirsConCargo = 0.0;
            $souvenirsDesc = [];

            $ftSouvenirs = $formType['souvenirs'] ?? [];
            foreach (($p['souvenirs'] ?? []) as $sv) {
                foreach ($ftSouvenirs as $ftSv) {
                    $svNombre = $sv['nombre'] ?? $sv['name'] ?? '';
                    $precioEsperado = ! empty($ftSv['incluido']) ? 0.0 : (float) $ftSv['price'];
                    if ($ftSv['name'] === $svNombre && $precioEsperado === (float) $sv['precio']) {
                        $tallasCtrl = $ftSv['tallas'] ?? [];
                        if (! empty($tallasCtrl)) {
                            $svTalla = $sv['talla'] ?? null;
                            $svSexo = $sv['sexo'] ?? null;
                            $match = null;
                            foreach ($tallasCtrl as $t) {
                                if (($t['talla'] ?? null) == $svTalla && ($t['sexo'] ?? null) == $svSexo) {
                                    $match = $t;
                                    break;
                                }
                            }
                            if (! $match || (int) $match['disponible'] <= 0) {
                                return ['error' => "No hay stock disponible de \"{$ftSv['name']}\" en la talla/combinación seleccionada."];
                            }
                        }

                        $souvenirsTotal += (float) $sv['precio'];
                        // Cargo de servicio por souvenir individual
                        // (01/09/2026, sincronizado 14/09/2026) — se usa el
                        // precio de CATÁLOGO ($precioEsperado), no el que
                        // mande el cliente, mismo criterio "nunca confiar en
                        // el proxy" del resto de esta función. Si el
                        // souvenir es `incluido` el precio esperado ya es 0
                        // (ver arriba), así que sumar $precioEsperado acá es
                        // equivalente y más simple que repetir la lógica de
                        // incluido.
                        if (! empty($ftSv['aplica_cargo_servicio'])) {
                            $souvenirsConCargo += $precioEsperado;
                        }
                        $souvenirsDesc[] = $sv;
                        break;
                    }
                }
            }

            $ftQuestions = [];
            foreach (($formType['preguntas'] ?? []) as $q) {
                $ftQuestions[(string) $q['id']] = true;
            }

            $answersValidas = [];
            foreach (($p['answers'] ?? []) as $ans) {
                $qid = (string) ($ans['question_id'] ?? '');
                if ($qid === '' || ! isset($ftQuestions[$qid])) {
                    continue;
                }
                $answersValidas[] = [
                    'form_types_id' => (int) ($formType['id'] ?? 0),
                    'question_id' => (int) $qid,
                    'value' => (string) ($ans['value'] ?? ''),
                ];
            }

            $descuento = 0.0;
            $promoCodigo = trim($p['promoCodigo'] ?? '');
            if ($promoCodigo !== '') {
                if (empty($formType['hasPromoCode'])) {
                    return ['error' => 'Este tipo de formulario no admite códigos promocionales.'];
                }

                $promo = null;
                foreach (($evento['promoCodes'] ?? []) as $pc) {
                    if (strcasecmp(trim($pc['promo_code'] ?? ''), $promoCodigo) === 0) {
                        $promo = $pc;
                        break;
                    }
                }
                // Código sin cambios respecto a la edición anterior
                // (16/10/2026, bug real reportado por el usuario) — port
                // del mismo criterio aplicado hoy a elascenso/event, ver
                // [[project_promocode_grandfather_edicion_propia]]: el
                // código propio de esta inscripción no se trata como "ya
                // utilizado" aunque el flag global lo esté; uno distinto
                // (ajeno, agotado por otra inscripción) sigue rechazándose.
                $anteriorPromo = is_array($categoriasAnteriores) ? ($categoriasAnteriores[$idx] ?? null) : null;
                $esMismoCodigoQueYaTenia = $anteriorPromo !== null
                    && ! empty($anteriorPromo['promoCodigo'])
                    && strcasecmp(trim((string) $anteriorPromo['promoCodigo']), $promoCodigo) === 0;

                if ($promo && ! empty($promo['usado']) && ! $esMismoCodigoQueYaTenia) {
                    return ['error' => 'Este código de promoción ya fue utilizado.'];
                }
                if ($promo) {
                    if (($promo['discount_type'] ?? 'fixed_price') === 'percentage') {
                        $descuento = round($inscripcion * (float) ($promo['discount_percent'] ?? 0), 2);
                    } else {
                        $promoPrecio = (float) ($promo['price'] ?? 0);
                        $descuento = max(0, round($inscripcion - $promoPrecio, 2));
                    }
                }
            }

            $talleres = [];
            $talleresUsd = [];
            $talleresTotal = 0.0;
            $talleresTotalUsd = 0.0;
            foreach (($p['talleres'] ?? []) as $ts) {
                $tallerId = (string) ($ts['taller_id'] ?? '');
                $sesionId = (string) ($ts['sesion_congreso_id'] ?? '');
                if ($tallerId === '' || $sesionId === '') {
                    return ['error' => 'Selección de taller incompleta en participante '.($idx + 1).'.'];
                }

                $taller = $talleresById[$tallerId] ?? null;
                if (! $taller) {
                    return ['error' => 'El taller seleccionado no pertenece a este evento.'];
                }

                $sesion = null;
                foreach (($taller['sesiones'] ?? []) as $s) {
                    if ((string) $s['id'] === $sesionId) {
                        $sesion = $s;
                        break;
                    }
                }
                if (! $sesion) {
                    return ['error' => "La sesión seleccionada no pertenece al taller '{$taller['nombre']}'."];
                }

                // Precio efectivo: si el evento NO cobra por talleres,
                // siempre 0 (los talleres siguen siendo seleccionables,
                // pero no suman). Override de la sesión gana; si no hay,
                // precio vigente del taller PARA LA CATEGORÍA de este
                // participante (13/10/2026, ver precioVigenteParaCategoria()
                // — ya resuelto "hoy" por ApiRestEvent,
                // TallerResource::precioVigentePorCategoria, que tiene en
                // cuenta tanto el período de precio por fecha como la
                // categoría si el taller tiene alguno cargado). Este
                // servicio no reimplementa esa lógica, solo consume el
                // campo.
                $unitPrice = 0.0;
                if ($talleresConCosto) {
                    if (isset($sesion['precio']) && $sesion['precio'] !== null) {
                        $unitPrice = (float) $sesion['precio'];
                    } else {
                        $vigente = $this->precioVigenteParaCategoria($taller, $p['categoria'] ?? null);
                        $unitPrice = $vigente ?? 0.0;
                    }
                }

                $unitPriceUsd = 0.0;
                if ($talleresConCosto && $usdPrecioFijo) {
                    if (isset($sesion['precioUsd']) && $sesion['precioUsd'] !== null) {
                        $unitPriceUsd = (float) $sesion['precioUsd'];
                    } else {
                        $vigenteUsd = $this->precioVigenteParaCategoria($taller, $p['categoria'] ?? null, true);
                        if ($vigenteUsd !== null) {
                            $unitPriceUsd = $vigenteUsd;
                        } else {
                            return ['error' => "El taller '{$taller['nombre']}' no tiene precio USD configurado para este evento."];
                        }
                    }
                }

                $talleres[] = [
                    'taller_id' => (int) $taller['id'],
                    'sesion_congreso_id' => (int) $sesion['id'],
                    'unit_price' => $unitPrice,
                ];
                if ($usdPrecioFijo) {
                    $talleresUsd[] = [
                        'taller_id' => (int) $taller['id'],
                        'sesion_congreso_id' => (int) $sesion['id'],
                        'unit_price' => $unitPriceUsd,
                    ];
                }
            }

            // Combos de talleres (12/10/2026, pedido del usuario) —
            // preview: quien decide y persiste de verdad qué se cobra es
            // ApiRestEvent (nunca confía en el unit_price que mande este
            // servicio), así que una discrepancia acá nunca compromete la
            // integridad de los datos, solo el monto que se le muestra al
            // participante antes de confirmar.
            $talleres = $this->resolverCombosTaller($talleres, $evento['tallerCombos'] ?? [], $p['categoria'] ?? null);
            $talleresTotal = 0.0;
            foreach ($talleres as $t) {
                $talleresTotal += $t['total'];
            }

            // USD fijo (13/10/2026, pedido del usuario) — mismo matching,
            // independiente, en USD (ver resolverCombosTaller($usarPriceUsd=true)).
            $talleresTotalUsd = 0.0;
            if ($usdPrecioFijo) {
                $talleresUsdResueltos = $this->resolverCombosTaller($talleresUsd, $evento['tallerCombos'] ?? [], $p['categoria'] ?? null, true);
                foreach ($talleresUsdResueltos as $t) {
                    $talleresTotalUsd += $t['total'];
                }
            }

            $base = $inscripcion + $donacion + $souvenirsTotal;
            $subtotal = $base - $descuento;

            $totalInscripcion += $inscripcion;
            $totalDonacion += $donacion;
            $totalSouvenirs += $souvenirsTotal;
            $totalSouvenirsConCargo += $souvenirsConCargo;
            $totalTalleres += $talleresTotal;
            $totalDescuento += $descuento;

            $participantesValidos[] = array_merge($p, [
                'precioPolera' => $precioPoleraValidado,
                'donacion' => $donacion,
                'souvenirs' => $souvenirsDesc,
                'talleres' => $talleres,
                'talleresTotalUsd' => $talleresTotalUsd,
                'promoDescuento' => $descuento,
                'subtotal' => round($subtotal, 2),
                'answers' => $answersValidas,
            ]);
        }

        $feePct = isset($evento['fee_pct']) ? (float) $evento['fee_pct'] : 0.05;
        $feeIncluyeTalleres = $evento['feeIncluyeTalleres'] ?? true;
        // Cargo de servicio por souvenir individual (01/09/2026,
        // sincronizado 14/09/2026) — souvenirs ya no quedan siempre afuera:
        // cada ítem del catálogo puede marcarse `aplica_cargo_servicio`
        // (default false, opt-in) para sumar su precio a la base.
        // $totalSouvenirsConCargo ya viene filtrado por ese flag.
        $baseFee = $totalInscripcion + ($feeIncluyeTalleres ? $totalTalleres : 0) + $totalSouvenirsConCargo;
        $fee = round($baseFee * $feePct, 2);

        $descuentoRegistrante = ($permiteGrupal && $maxGrupo > 0 && count($participantesValidos) >= $maxGrupo)
            ? round($totalInscripcion * $descuentoPct, 2)
            : 0.0;

        $grandTotal = round($totalInscripcion + $totalDonacion + $totalSouvenirs + $totalTalleres + $fee - $totalDescuento - $descuentoRegistrante, 2);

        return [
            'participantes' => $participantesValidos,
            'totales' => [
                'inscripcion' => $totalInscripcion,
                'donacion' => $totalDonacion,
                'souvenirs' => $totalSouvenirs,
                'talleres' => $totalTalleres,
                'fee' => $fee,
                'descuento' => $totalDescuento,
                'descuento_registrante' => $descuentoRegistrante,
                'grand_total' => $grandTotal,
            ],
        ];
    }

    /**
     * @return array{total: float, totalTalleres: float}|array{error: string}
     */
    public function calcularTotalUsdFijo(array $evento, array $participantesValidos): array
    {
        $categoriasPorId = [];
        foreach (($evento['categories'] ?? []) as $cat) {
            $categoriasPorId[(string) $cat['id']] = $cat;
        }

        $totalUsd = 0.0;
        $totalTalleresUsd = 0.0;
        foreach ($participantesValidos as $p) {
            if (
                (float) ($p['donacion'] ?? 0) > 0
                || ! empty($p['souvenirs'])
                || (float) ($p['precioPolera'] ?? 0) > 0
            ) {
                return ['error' => 'Este evento cobra en USD solo la inscripción y los talleres — sacá souvenirs, camiseta o donación del carrito, o pagá en BOB.'];
            }

            $cat = $categoriasPorId[(string) ($p['categoria'] ?? '')] ?? null;
            if (! $cat || ! isset($cat['priceUsd']) || $cat['priceUsd'] === null) {
                return ['error' => 'La categoría elegida no tiene precio en USD configurado para este evento.'];
            }

            $totalTalleresUsd += (float) ($p['talleresTotalUsd'] ?? 0);
            $totalUsd += (float) $cat['priceUsd'] + (float) ($p['talleresTotalUsd'] ?? 0);
        }

        return ['total' => round($totalUsd, 2), 'totalTalleres' => round($totalTalleresUsd, 2)];
    }
}
