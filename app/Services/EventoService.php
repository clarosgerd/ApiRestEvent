<?php

namespace App\Services;

use App\DTOs\EventoDTO;
use App\Models\Evento;
use App\Models\Coordinate;
use App\Models\Route;
use App\Models\Category;
use App\Models\FormType;
use App\Models\Souvenir;
use App\Models\FormularioCampos;
use App\Models\QuestionOptions;
use App\Models\PromoCode;
use App\Models\Auspiciador;
use App\Models\AgendaItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EventoService
{
    public function create(EventoDTO $dto): Evento
    {
        return DB::transaction(function () use ($dto) {

            $evento = Evento::create([
                // Campos expuestos por la API (StoreEventosRequest / EventoResource)
                'nombre'              => $dto->nombre,
                'descripcion'         => $dto->descripcion,
                'longDescription'     => $dto->longDescription ?? '',
                'fecha_inicio'        => $dto->fechaInicio,
                'localTime'           => $dto->localTime ?: null,
                'direccion'           => $dto->direccion,
                'estado_evento_id'    => $dto->status,
                'publicado'           => $dto->publicado,
                'hasDonation'         => $dto->hasDonation,
                'hasPromoCode'        => !empty($dto->promoCodes),
                'video_url'           => $dto->videoUrl ?? '',
                'imagen_portada_url'  => $dto->imagenPortadaUrl ?? '',
                // Default navy: mismo color que ya usaban gafetes/certificados antes de
                // que esto fuera configurable — un evento sin colorHex propio no cambia
                // de aspecto.
                'color_hex'           => $dto->colorHex ?: '#022858',
                'chronotrack_event_id' => $dto->chronotrackEventId,

                // organizador_id: usa el que venga en el request (validado contra
                // organizadores.id en StoreEventosRequest) — si no se manda,
                // mantiene el default histórico (1) para no romper callers
                // existentes que todavía no lo envían.
                'organizador_id'         => $dto->organizadorId ?? 1,

                // tipo_evento_id/subtipo_evento_id: ya expuestos de verdad
                // (StoreEventosRequest/EventoResource) — default 1 ("Carrera de
                // Ruta") si no viene, mismo criterio que organizador_id arriba,
                // para no romper callers viejos. Ver
                // brain/PLAN-ENDPOINT-CONSUMO-05082026.md.
                'tipo_evento_id'         => $dto->tipoEventoId ?? 1,
                'subtipo_evento_id'      => $dto->subtipoEventoId ?? 1,

                // Columnas NOT NULL sin default en `eventos` que la API todavía no
                // expone (ver migración 2026_06_28_214848_create_eventos_table) —
                // se rellenan con valores neutros para que el INSERT no falle.
                'pais_id'                => 1,
                'ciudad_id'              => 1,
                'nombre_corto'           => Str::limit($dto->nombre, 60, ''),
                'url_slug'               => Str::slug($dto->nombre) . '-' . Str::lower(Str::random(6)),
                'keyword'                => Str::slug($dto->nombre),
                'reglamento'             => '',
                'deslinde'               => $dto->deslinde ?? '',
                'deslinde_pdf_url'       => $dto->deslindePdfUrl ?? '',
                'fecha_fin'              => $dto->fechaInicio,
                'fecha_apertura_inscrip' => now(),
                'fecha_cierre_inscrip'   => $dto->fechaInicio,
                'mensaje_cierre'         => '',
                'lugar'                  => $dto->direccion,
                'url_virtual'            => '',
                'aforo_total'            => 0,
                'color_id'               => 1,
                'logo_url'               => '',
                'icono_url'              => '',
                'gpx_url'                => '',
                'link_strava'            => '',
            ]);

            $this->createCoordinates($evento, $dto);
            $this->createRoutes($evento, $dto);
            $this->createCategories($evento, $dto);
            $this->createFormTypes($evento, $dto);
            $this->createPromoCodes($evento, $dto);
            $this->createAuspiciadores($evento, $dto);
            $this->createAgendaItems($evento, $dto);

            return $this->loadRelations($evento);
        });
    }

    /**
     * Actualiza los campos escalares del evento — no toca categorías,
     * form_types, promo_codes, coordenadas, ruta ni agenda, que tienen sus
     * propios endpoints de edición (ver Category/FormType/PromoCode/
     * Coordinate/RouteController).
     */
    public function update(Evento $evento, array $data): Evento
    {
        $map = [
            'name'             => 'nombre',
            'description'      => 'descripcion',
            'longDescription'  => 'longDescription',
            'date'             => 'fecha_inicio',
            'localTime'        => 'localTime',
            'location'         => 'direccion',
            'status'           => 'estado_evento_id',
            'hasDonation'      => 'hasDonation',
            'video'            => 'video_url',
            'image'            => 'imagen_portada_url',
            'colorHex'         => 'color_hex',
            'chronotrackEventId' => 'chronotrack_event_id',
            'deslinde'         => 'deslinde',
            'deslinde_pdf_url' => 'deslinde_pdf_url',
            // Ver brain/PLAN-ENDPOINT-CONSUMO-05082026.md — permite corregir
            // desde el panel eventos ya creados que quedaron con el default
            // histórico (tipo_evento_id=1, "Carrera de Ruta") aunque en
            // realidad sean un congreso u otra disciplina.
            'tipo_evento_id'    => 'tipo_evento_id',
            'subtipo_evento_id' => 'subtipo_evento_id',
        ];

        // Columnas NOT NULL sin default en la migración original de `eventos`
        // (longDescription, deslinde, imagen_portada_url, video_url) —
        // UpdateEventosRequest las marca "nullable" porque el panel debe
        // poder vaciarlas, pero un `null` real revienta el UPDATE con un
        // "column cannot be null". `create()` ya coerce esto a '' — acá
        // faltaba el mismo tratamiento. Encontrado el 05/08/2026 al
        // verificar el campo tipo_evento_id nuevo (bug preexistente, sin
        // relación con ese cambio).
        $noNullables = ['longDescription', 'deslinde', 'imagen_portada_url', 'video_url'];

        $attributes = [];
        foreach ($map as $requestKey => $column) {
            if (array_key_exists($requestKey, $data)) {
                $value = $data[$requestKey];
                if ($value === null && in_array($column, $noNullables, true)) {
                    $value = '';
                }
                $attributes[$column] = $value;
            }
        }

        if (!empty($attributes)) {
            $evento->update($attributes);
        }

        return $this->loadRelations($evento);
    }

    private function createCoordinates(Evento $evento, EventoDTO $dto): void
    {
        if (empty($dto->coordinates)) return;

        $data = array_map(fn($c) => [
            'event_id' => $evento->id,
            'lat'      => $c->lat,
            'lng'      => $c->lng,
        ], $dto->coordinates);

        Coordinate::insert($data);
    }

    private function createRoutes(Evento $evento, EventoDTO $dto): void
    {
        if (empty($dto->routes)) return;

        $data = array_map(fn($r) => [
            'event_id' => $evento->id,
            'lat'      => $r->lat,
            'lng'      => $r->lng,
            'label'    => $r->label,
        ], $dto->routes);

        Route::insert($data);
    }

    private function createCategories(Evento $evento, EventoDTO $dto): void
    {
        if (empty($dto->categories)) return;

        $data = array_map(fn($c) => [
            'event_id'    => $evento->id,
            'name'        => $c->name,
            'price'       => $c->price,
            'description' => $c->description,
            'color'       => $c->color,
        ], $dto->categories);

        Category::insert($data);
    }

    private function createAuspiciadores(Evento $evento, EventoDTO $dto): void
    {
        if (empty($dto->auspiciadores)) return;

        $data = array_map(fn($a) => [
            'event_id' => $evento->id,
            'nombre'   => $a->nombre,
            'logo_url' => $a->logoUrl,
            'contacto' => $a->contacto,
            'orden'    => $a->orden,
        ], $dto->auspiciadores);

        Auspiciador::insert($data);
    }

    private function createAgendaItems(Evento $evento, EventoDTO $dto): void
    {
        if (empty($dto->agenda)) return;

        // Resuelve formTypeName -> id real ya creado por createFormTypes()
        // (corrió antes que este método) — el cliente arma el payload sin
        // conocer los IDs autogenerados de sus propios formTypes.
        $formTypesByName = $evento->formTypes()->pluck('id', 'name');

        $data = array_map(function ($a) use ($evento, $formTypesByName) {
            $formTypeId = $a->formTypeId ?? ($a->formTypeName ? $formTypesByName->get($a->formTypeName) : null);

            return [
                'event_id'      => $evento->id,
                'form_type_id'  => $formTypeId,
                'fecha'         => $a->fecha,
                'hora_inicio'   => $a->horaInicio,
                'hora_fin'      => $a->horaFin,
                'titulo'        => $a->titulo,
                'descripcion'   => $a->descripcion,
                'ponente'       => $a->ponente,
                'ponente_cargo' => $a->ponenteCargo,
                'sala'          => $a->sala,
                'icono'         => $a->icono,
                'orden'         => $a->orden,
                'created_at'    => now(),
                'updated_at'    => now(),
            ];
        }, $dto->agenda);

        AgendaItem::insert($data);
    }

    private function createFormTypes(Evento $evento, EventoDTO $dto): void
    {
        if (empty($dto->formTypes)) return;

        foreach ($dto->formTypes as $formTypeDTO) {
            $formType = FormType::create([
                'event_id'              => $evento->id,
                'name'                  => $formTypeDTO->name,
                'icon'                  => $formTypeDTO->icon,
                'description'           => $formTypeDTO->description,
                'tipo'                  => $formTypeDTO->tipo,
                'cupo_total'            => $formTypeDTO->cupoTotal,
                'precio_base'           => $formTypeDTO->precioBase,
                'costo_edicion'         => $formTypeDTO->costoEdicion,
                'tiempo_expiracion_min' => $formTypeDTO->tiempoExpiracionMin,
                'color'                 => $formTypeDTO->color,
                'moneda'                => $formTypeDTO->moneda,
                'permite_lista_espera'  => $formTypeDTO->permiteListaEspera,
                'hasshirt'              => $formTypeDTO->hasshirt,
                'costo_polera'          => $formTypeDTO->costoPolera,
                'requiere_talla'        => $formTypeDTO->requiereTalla,
                'permite_inscripcion_grupal' => $formTypeDTO->permiteInscripcionGrupal,
                'max_integrantes_grupo'      => $formTypeDTO->maxIntegrantesGrupo,
                'descuento_registrante_pct'  => $formTypeDTO->descuentoRegistrantePct,
                'has_team'                   => $formTypeDTO->hasTeam,
                'has_delivery'               => $formTypeDTO->hasDelivery,
                'requiere_categoria'         => $formTypeDTO->requiereCategoria,
            ]);

            $this->createSouvenirs($formType, $formTypeDTO->souvenirs);
            $this->createFormularioCampos($formType, $formTypeDTO->preguntas);
        }
    }

    private function createSouvenirs(FormType $formType, array $souvenirs): void
    {
        if (empty($souvenirs)) return;

        $data = array_map(fn($s) => [
            'form_types_id' => $formType->id,
            'name'          => $s->name,
            'icon'          => $s->icon,
            'price'         => $s->price,
        ], $souvenirs);

        Souvenir::insert($data);
    }

    private function createFormularioCampos(FormType $formType, array $preguntas): void
    {
        if (empty($preguntas)) return;

        foreach ($preguntas as $preguntaDTO) {
            $question = FormularioCampos::create([
                'form_types_id' => $formType->id,
                'nombre_campo'  => $preguntaDTO->nombreCampo,
                'seccion'       => $preguntaDTO->seccion,
                'etiqueta'      => $preguntaDTO->etiqueta,
                'tipo_input'    => $preguntaDTO->tipoInput,
                'placeholder'   => $preguntaDTO->placeholder,
                'obligatorio'   => $preguntaDTO->obligatorio,
                'orden'         => $preguntaDTO->orden,
            ]);

            $this->createQuestionOptions($question, $preguntaDTO->options);
        }
    }

    private function createQuestionOptions(FormularioCampos $question, array $options): void
    {
        if (empty($options)) return;

        $data = array_map(fn($o) => [
            'question_id' => $question->id,
            'option_text' => $o->optionText,
            'order'       => $o->order,
        ], $options);

        QuestionOptions::insert($data);
    }

    private function createPromoCodes(Evento $evento, EventoDTO $dto): void
    {
        if (empty($dto->promoCodes)) return;

        $data = array_map(fn($p) => [
            'event_id'         => $evento->id,
            'promo_code'       => $p->promoCode,
            'price'            => $p->price,
            'discount_type'    => $p->discountType,
            'discount_percent' => $p->discountPercent,
        ], $dto->promoCodes);

        PromoCode::insert($data);
    }

    private function loadRelations(Evento $evento): Evento
    {
        return $evento->load([
            'coordinates',
            'routes',
            'categories',
            'formTypes.souvenirs',
            'formTypes.formularioCampos.options',
            'promoCodes',
            'auspiciadores',
            'agendaItems',
            'organizador.formasPagoSeleccionadas',
        ]);
    }
}
