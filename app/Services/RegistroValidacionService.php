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
     * Revalida cada participante contra el evento/tipo de formulario y
     * recalcula totales en el servidor.
     *
     * @return array{participantes: array, totales: array}|array{error: string}
     */
    public function validarYCalcularParticipantes(array $evento, ?array $formType, array $participantes): array
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
            $required = ['nombre', 'apellido', 'alias', 'genero', 'correo', 'direccion', 'ciudad', 'categoria', 'precioCategoria'];
            foreach ($required as $campo) {
                if (empty($p[$campo])) {
                    return ['error' => "Campo '{$campo}' requerido en participante ".($idx + 1).'.'];
                }
            }

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

            $conPolera = ! empty($p['polera']) && $p['polera'] !== 'No shirt';
            $precioPoleraValidado = $conPolera ? (float) ($formType['costo_polera'] ?? 0) : 0.0;

            $inscripcion = (float) ($p['precioCategoria'] ?? 0) + $precioPoleraValidado;
            $donacion = ! empty($formType['hasDonation']) ? max(0, (float) ($p['donacion'] ?? 0)) : 0.0;
            $souvenirsTotal = 0.0;
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
                if ($promo && ! empty($promo['usado'])) {
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

                $unitPrice = 0.0;
                if ($talleresConCosto) {
                    if (isset($sesion['precio']) && $sesion['precio'] !== null) {
                        $unitPrice = (float) $sesion['precio'];
                    } elseif (isset($taller['precio']) && $taller['precio'] !== null) {
                        $unitPrice = (float) $taller['precio'];
                    }
                }

                $unitPriceUsd = 0.0;
                if ($talleresConCosto && $usdPrecioFijo) {
                    if (isset($sesion['precioUsd']) && $sesion['precioUsd'] !== null) {
                        $unitPriceUsd = (float) $sesion['precioUsd'];
                    } elseif (isset($taller['precioUsd']) && $taller['precioUsd'] !== null) {
                        $unitPriceUsd = (float) $taller['precioUsd'];
                    } else {
                        return ['error' => "El taller '{$taller['nombre']}' no tiene precio USD configurado para este evento."];
                    }
                }

                $talleres[] = [
                    'taller_id' => (int) $taller['id'],
                    'sesion_congreso_id' => (int) $sesion['id'],
                    'unit_price' => $unitPrice,
                ];
                $talleresTotal += $unitPrice;
                $talleresTotalUsd += $unitPriceUsd;
            }

            $base = $inscripcion + $donacion + $souvenirsTotal;
            $subtotal = $base - $descuento;

            $totalInscripcion += $inscripcion;
            $totalDonacion += $donacion;
            $totalSouvenirs += $souvenirsTotal;
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
        $baseFee = $totalInscripcion + ($feeIncluyeTalleres ? $totalTalleres : 0);
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
