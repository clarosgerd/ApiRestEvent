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

                // Columnas NOT NULL sin default en `eventos` que la API todavía no
                // expone (ver migración 2026_06_28_214848_create_eventos_table) —
                // se rellenan con valores neutros para que el INSERT no falle.
                'organizador_id'         => 1,
                'tipo_evento_id'         => 1,
                'subtipo_evento_id'      => 1,
                'pais_id'                => 1,
                'ciudad_id'              => 1,
                'nombre_corto'           => Str::limit($dto->nombre, 60, ''),
                'url_slug'               => Str::slug($dto->nombre) . '-' . Str::lower(Str::random(6)),
                'keyword'                => Str::slug($dto->nombre),
                'reglamento'             => '',
                'deslinde'               => '',
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

            return $this->loadRelations($evento);
        });
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
                'requiere_talla'        => $formTypeDTO->requiereTalla,
                'permite_inscripcion_grupal' => $formTypeDTO->permiteInscripcionGrupal,
                'max_integrantes_grupo'      => $formTypeDTO->maxIntegrantesGrupo,
                'descuento_registrante_pct'  => $formTypeDTO->descuentoRegistrantePct,
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
        ]);
    }
}
