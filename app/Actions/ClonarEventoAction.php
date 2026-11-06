<?php

namespace App\Actions;

use App\Models\Category;
use App\Models\Evento;
use App\Models\PromoCode;
use App\Models\Taller;
use App\Services\EventoService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Clonar evento (23/10/2026, pedido real del usuario) — duplica toda la
 * CONFIGURACIÓN de un evento (categorías, tipos de formulario, souvenirs,
 * talleres/sesiones/combos, agenda, auspiciadores, promo codes) sin tocar
 * nada transaccional (inscripciones, participantes, pagos, caja,
 * numeración ya asignada, stock consumido, asistencia real). Mismo
 * espíritu que CrearEventoAction (todo en una transacción), pero acá la
 * fuente es un Evento YA EXISTENTE, no un DTO armado a mano — por eso usa
 * Model::replicate() en vez de listar cada columna: copia todo lo que haya
 * en el modelo, incluido cualquier campo que se agregue a futuro, y solo
 * hace falta pisar explícitamente lo que cambia (FKs al evento nuevo, y
 * los pocos campos que se resetean).
 *
 * Ver DEPLOY-CHECKLIST-CLONAR-EVENTO-*.md para la lista completa de qué se
 * clona y qué no.
 */
class ClonarEventoAction
{
    public function __construct(
        private readonly EventoService $eventoService,
    ) {
    }

    public function handle(Evento $original, string $nuevoNombre): Evento
    {
        return DB::transaction(function () use ($original, $nuevoNombre) {
            $nuevo = $this->clonarEventoBase($original, $nuevoNombre);

            $this->clonarCoordinates($original, $nuevo);
            $this->clonarRoutes($original, $nuevo);
            $categoriaIdMap = $this->clonarCategorias($original, $nuevo);
            $formTypeIdMap = $this->clonarFormTypes($original, $nuevo);
            // Categories.formulario_id puede apuntar a un FormType (categorías
            // por form_type, 27/08/2026) — recién acá, con formTypeIdMap ya
            // armado, se puede remapear.
            $this->remapCategoriasFormularioId($original, $nuevo, $categoriaIdMap, $formTypeIdMap);
            $agendaItemIdMap = $this->clonarAgendaItems($original, $nuevo, $formTypeIdMap);
            $tallerIdMap = $this->clonarTalleres($original, $nuevo, $categoriaIdMap);
            $this->clonarSesionesCongreso($original, $nuevo, $tallerIdMap, $agendaItemIdMap);
            $this->clonarTallerCombos($original, $nuevo, $tallerIdMap, $categoriaIdMap);
            $this->clonarPromoCodes($original, $nuevo);
            $this->clonarAuspiciadores($original, $nuevo);

            return $this->eventoService->loadRelations($nuevo);
        });
    }

    private function clonarEventoBase(Evento $original, string $nuevoNombre): Evento
    {
        $nuevo = $original->replicate([
            'id',
            'nombre',
            'nombre_corto',
            'url_slug',
            'keyword',
            'publicado',
            'destacado',
            'estado_evento_id',
            'contador_visitas',
            'chronotrack_event_id',
            'created_at',
            'updated_at',
            'deleted_at',
        ]);

        $nuevo->nombre = $nuevoNombre;
        $nuevo->nombre_corto = Str::limit($nuevoNombre, 60, '');
        // Mismo criterio de generación de slug que CrearEventoAction — un
        // nombre repetido (ej. "Evento X (copia)" clonado dos veces) nunca
        // choca, el slug no depende de la unicidad del nombre.
        $nuevo->url_slug = Str::slug($nuevoNombre) . '-' . Str::lower(Str::random(6));
        $nuevo->keyword = Str::slug($nuevoNombre);
        // El clon nace borrador siempre — el organizador revisa fechas
        // (que SÍ se copian tal cual, ver docblock de la clase) antes de
        // publicar.
        $nuevo->publicado = false;
        $nuevo->destacado = false;
        $nuevo->estado_evento_id = $original->estado_evento_id;
        $nuevo->contador_visitas = 0;
        $nuevo->chronotrack_event_id = null;
        $nuevo->save();

        return $nuevo;
    }

    private function clonarCoordinates(Evento $original, Evento $nuevo): void
    {
        foreach ($original->coordinates as $coordinate) {
            $clon = $coordinate->replicate(['id', 'event_id']);
            $clon->event_id = $nuevo->id;
            $clon->save();
        }
    }

    private function clonarRoutes(Evento $original, Evento $nuevo): void
    {
        foreach ($original->routes as $route) {
            $clon = $route->replicate(['id', 'event_id']);
            $clon->event_id = $nuevo->id;
            $clon->save();
        }
    }

    /** @return array<int,int> id de categoría original => id de categoría clonada */
    private function clonarCategorias(Evento $original, Evento $nuevo): array
    {
        $map = [];

        foreach ($original->categories as $categoria) {
            $clon = $categoria->replicate(['id', 'event_id']);
            $clon->event_id = $nuevo->id;
            // formulario_id se remapea después (remapCategoriasFormularioId),
            // una vez que exista formTypeIdMap — acá se guarda tal cual
            // (apunta al FormType ORIGINAL, temporalmente inconsistente
            // dentro de la misma transacción) para no duplicar queries.
            $clon->save();
            $map[$categoria->id] = $clon->id;

            foreach ($categoria->pricePeriods as $periodo) {
                $clonPeriodo = $periodo->replicate(['id', 'category_id']);
                $clonPeriodo->category_id = $clon->id;
                $clonPeriodo->save();
            }
            // NumeracionRango (categoria->numeracionRangos) NO se clona a
            // propósito — son números ya asignados del evento original.
        }

        return $map;
    }

    private function remapCategoriasFormularioId(Evento $original, Evento $nuevo, array $categoriaIdMap, array $formTypeIdMap): void
    {
        foreach ($original->categories as $categoriaOriginal) {
            if (empty($categoriaOriginal->formulario_id)) {
                continue;
            }

            $nuevoFormularioId = $formTypeIdMap[$categoriaOriginal->formulario_id] ?? null;
            if ($nuevoFormularioId === null) {
                continue;
            }

            Category::whereKey($categoriaIdMap[$categoriaOriginal->id])
                ->update(['formulario_id' => $nuevoFormularioId]);
        }
    }

    /** @return array<int,int> id de form_type original => id de form_type clonado */
    private function clonarFormTypes(Evento $original, Evento $nuevo): array
    {
        $map = [];

        foreach ($original->formTypes as $formType) {
            $clon = $formType->replicate(['id', 'event_id']);
            $clon->event_id = $nuevo->id;
            $clon->save();
            $map[$formType->id] = $clon->id;

            foreach ($formType->souvenirs as $souvenir) {
                $clonSouvenir = $souvenir->replicate(['id', 'form_types_id']);
                $clonSouvenir->form_types_id = $clon->id;
                $clonSouvenir->save();
                // Souvenir->stock (ItemStock) NO se clona — stock real
                // consumido del evento original.
            }

            foreach ($formType->formularioCampos as $pregunta) {
                $clonPregunta = $pregunta->replicate(['id', 'form_types_id']);
                $clonPregunta->form_types_id = $clon->id;
                $clonPregunta->save();

                foreach ($pregunta->options as $opcion) {
                    $clonOpcion = $opcion->replicate(['id', 'question_id']);
                    $clonOpcion->question_id = $clonPregunta->id;
                    $clonOpcion->save();
                }
            }
        }

        return $map;
    }

    /** @return array<int,int> id de agenda item original => id de agenda item clonado */
    private function clonarAgendaItems(Evento $original, Evento $nuevo, array $formTypeIdMap): array
    {
        $map = [];

        foreach ($original->agendaItems as $agendaItem) {
            $clon = $agendaItem->replicate(['id', 'event_id', 'form_type_id']);
            $clon->event_id = $nuevo->id;
            $clon->form_type_id = $agendaItem->form_type_id
                ? ($formTypeIdMap[$agendaItem->form_type_id] ?? null)
                : null;
            $clon->save();
            $map[$agendaItem->id] = $clon->id;
        }

        return $map;
    }

    /** @return array<int,int> id de taller original => id de taller clonado */
    private function clonarTalleres(Evento $original, Evento $nuevo, array $categoriaIdMap): array
    {
        $map = [];

        foreach ($original->talleres as $taller) {
            $clon = $taller->replicate(['id', 'evento_id']);
            $clon->evento_id = $nuevo->id;
            $clon->save();
            $map[$taller->id] = $clon->id;

            foreach ($taller->pricePeriods as $periodo) {
                // TallerPricePeriod también tiene category_id (precio por
                // categoría dentro del período) — se remapea igual que en
                // TallerComboPrecio; un período sin categoría (null) queda
                // null tal cual.
                $nuevaCategoriaId = $periodo->category_id ? ($categoriaIdMap[$periodo->category_id] ?? null) : null;

                $clonPeriodo = $periodo->replicate(['id', 'taller_id', 'category_id']);
                $clonPeriodo->taller_id = $clon->id;
                $clonPeriodo->category_id = $nuevaCategoriaId;
                $clonPeriodo->save();
            }
        }

        return $map;
    }

    /**
     * Sesiones de congreso (23/10/2026) — no cuelgan solo de un Taller:
     * tienen su propio `evento_id` y pueden referenciar `taller_id`/
     * `agenda_item_id` de forma independiente (ver SesionCongreso
     * fillable), así que se recorren TODAS las del evento original, no
     * solo las de cada Taller. No se clona `asistencias()` (check-in real)
     * ni el staff/ponente asignado (pivot `sesion_congreso_staff`, son
     * Participantes reales del evento original).
     */
    private function clonarSesionesCongreso(Evento $original, Evento $nuevo, array $tallerIdMap, array $agendaItemIdMap): void
    {
        foreach ($original->sesionesCongreso as $sesion) {
            $clon = $sesion->replicate(['id', 'evento_id', 'taller_id', 'agenda_item_id']);
            $clon->evento_id = $nuevo->id;
            $clon->taller_id = $sesion->taller_id ? ($tallerIdMap[$sesion->taller_id] ?? null) : null;
            $clon->agenda_item_id = $sesion->agenda_item_id ? ($agendaItemIdMap[$sesion->agenda_item_id] ?? null) : null;
            $clon->save();
        }
    }

    private function clonarTallerCombos(Evento $original, Evento $nuevo, array $tallerIdMap, array $categoriaIdMap): void
    {
        foreach ($original->tallerCombos as $combo) {
            $clon = $combo->replicate(['id', 'evento_id']);
            $clon->evento_id = $nuevo->id;
            $clon->save();

            $nuevosTallerIds = $combo->talleres->map(fn (Taller $t) => $tallerIdMap[$t->id] ?? null)->filter()->values();
            if ($nuevosTallerIds->isNotEmpty()) {
                $clon->talleres()->sync($nuevosTallerIds);
            }

            foreach ($combo->precios as $precio) {
                $nuevaCategoriaId = $categoriaIdMap[$precio->category_id] ?? null;
                if ($nuevaCategoriaId === null) {
                    continue;
                }

                $clonPrecio = $precio->replicate(['id', 'taller_combo_id', 'category_id']);
                $clonPrecio->taller_combo_id = $clon->id;
                $clonPrecio->category_id = $nuevaCategoriaId;
                $clonPrecio->save();
            }
        }
    }

    /**
     * El código promocional es único a nivel GLOBAL, no por evento (pedido
     * real del usuario 23/10/2026: clonar la definición pero con un código
     * nuevo) — se arma con un sufijo aleatorio y se reintenta si choca, en
     * vez de confiar en que el sufijo alcance a la primera. Uso/contador se
     * resetea siempre: `usado=false`, `times_used=0`, `registration_id=null`.
     */
    private function clonarPromoCodes(Evento $original, Evento $nuevo): void
    {
        foreach ($original->promoCodes as $promoCode) {
            $clon = $promoCode->replicate(['id', 'event_id', 'promo_code', 'usado', 'registration_id', 'times_used']);
            $clon->event_id = $nuevo->id;
            $clon->promo_code = $this->generarCodigoUnico($promoCode->promo_code);
            $clon->usado = false;
            $clon->registration_id = null;
            $clon->times_used = 0;
            $clon->save();
        }
    }

    private function generarCodigoUnico(string $codigoOriginal): string
    {
        do {
            $candidato = $codigoOriginal . '-' . Str::upper(Str::random(4));
        } while (PromoCode::where('promo_code', $candidato)->exists());

        return $candidato;
    }

    private function clonarAuspiciadores(Evento $original, Evento $nuevo): void
    {
        foreach ($original->auspiciadores as $auspiciador) {
            $clon = $auspiciador->replicate(['id', 'event_id']);
            $clon->event_id = $nuevo->id;
            $clon->save();
        }
    }
}
