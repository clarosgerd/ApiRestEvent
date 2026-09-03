<?php

namespace App\Actions;

use App\Models\Registration;
use App\Support\EdicionPagadaCategoriaData;
use App\Support\EdicionPagadaSouvenirsData;
use App\Support\Taller\ResolverPrecioTallerData;

/**
 * Cobro real por SIP del monto adicional (26/08/2026) — ver
 * PLAN-COBRO-SIP-ADICIONAL-26082026.md. Calcula cuánto habría que cobrar
 * SIN aplicar ningún cambio — necesario porque el monto del QR SIP se
 * necesita ANTES de que ActualizarInscripcionPagadaAction corra de
 * verdad (esa Action solo se llama al confirmar el pago, ver
 * ConfirmarPagoAdicionalAction). Nunca se confía en un monto que mande
 * el cliente para esto — se recalcula acá con las mismas reglas/fuentes
 * de precio que usaría la aplicación real (ResolverPrecioTallerData),
 * sin duplicar esa lógica.
 *
 * Mismas validaciones que ActualizarInscripcionPagadaAction en modo
 * autoservicio ('solo_subida', 02/09/2026 — ver EdicionPagadaCategoriaData
 * / EdicionPagadaSouvenirsData, extraídas de acá y de esa Action para no
 * mantener la misma regla escrita dos veces): permite categoría hacia
 * arriba, rechaza remoción de talleres/souvenirs ya pagados — no tiene
 * sentido generar un QR de pago para un cambio que de todos modos se va a
 * rechazar al confirmar. Esta Action SIEMPRE corre en modo autoservicio —
 * Caja cobra en efectivo en el momento, nunca pasa por acá.
 */
class CalcularCostoAdicionalAction
{
    public function handle(Registration $registration, array $participantes): float
    {
        if ($registration->pago_status !== 'paid') {
            throw new \DomainException('Esta operación solo aplica a inscripciones pagadas.');
        }

        $costoEdicion = (float) ($registration->formType->costo_edicion ?? 0);
        $evento = $registration->evento;

        // Cobro real por SIP del monto adicional NO soporta todavía eventos
        // con precio fijo en USD (27/08/2026) — ni costo_edicion ni el
        // cálculo de talleres de acá tienen variante en USD, y
        // pago_adicional.php generaría el QR en Bs para un evento que cobra
        // EXCLUSIVAMENTE en USD (ver project_precio_usd_fijo_frontend_
        // exclusivo). Decisión explícita del usuario: por ahora solo se
        // permite modificar y pagar en efectivo en el evento — arreglar
        // esto de verdad requiere threadear la moneda por todo el flujo
        // (costo_edicion_usd, ResolverPrecioTallerData::unitPriceUsd() acá,
        // moneda dinámica en pago_adicional.php), fuera de alcance por
        // ahora.
        if ($evento?->usd_precio_fijo) {
            throw new \DomainException(
                'Este evento cobra en USD — el pago del monto adicional por QR todavía no está disponible; se cobra en efectivo el día del evento.'
            );
        }

        $participantesAnteriores = $registration->participants()
            ->with('talleresSesiones', 'souvenirParticipante')
            ->orderBy('id')
            ->get();

        if ($participantesAnteriores->count() !== count($participantes)) {
            throw new \DomainException(
                'Esta operación no permite agregar ni quitar participantes, solo modificar los existentes.'
            );
        }

        $deltaTalleres = 0.0;
        $deltaCategoria = 0.0;
        $deltaSouvenirs = 0.0;

        foreach ($participantes as $i => $participantData) {
            $anterior = $participantesAnteriores[$i];

            $cambioCategoria = EdicionPagadaCategoriaData::resolver(
                categoriaAnteriorId: (string) $anterior->categoria,
                precioAnterior: (float) $anterior->precio_categoria,
                categoriaNuevaId: (string) ($participantData['categoria'] ?? ''),
                eventoId: $registration->evento_id,
                formTypesId: $registration->form_types_id,
                modoCategoria: 'solo_subida',
            );
            $deltaCategoria += $cambioCategoria['delta'];

            $idsAnteriores = $anterior->talleresSesiones->pluck('sesion_congreso_id')->map(fn ($id) => (int) $id)->all();
            $idsNuevos = collect($participantData['talleres'] ?? [])->pluck('sesion_congreso_id')->map(fn ($id) => (int) $id)->all();

            if (! empty(array_diff($idsAnteriores, $idsNuevos))) {
                throw new \DomainException('No se pueden quitar talleres que ya fueron pagados.');
            }

            $idsAgregados = array_diff($idsNuevos, $idsAnteriores);
            if (! empty($idsAgregados)) {
                $sesiones = \App\Models\SesionCongreso::with('taller')
                    ->whereIn('id', $idsAgregados)
                    ->where('evento_id', $registration->evento_id)
                    ->get();

                foreach ($sesiones as $sesion) {
                    if (! $sesion->taller) {
                        continue;
                    }
                    $deltaTalleres += ResolverPrecioTallerData::total($sesion->taller, $sesion, $evento);
                }
            }

            $souvenirIdsAnteriores = $anterior->souvenirParticipante->pluck('souvenir_id')->map(fn ($id) => (int) $id)->all();
            $souvenirIdsNuevos = collect($participantData['souvenirs'] ?? [])->pluck('id')->map(fn ($id) => (int) $id)->all();
            $cambioSouvenirs = EdicionPagadaSouvenirsData::resolver($registration->form_types_id, $souvenirIdsAnteriores, $souvenirIdsNuevos);
            $deltaSouvenirs += $cambioSouvenirs['deltaSouvenirs'];
        }

        return round($costoEdicion + $deltaTalleres + $deltaCategoria + $deltaSouvenirs, 2);
    }
}
