<?php

namespace App\Services;

use App\DTOs\EventoDTO;
use App\Models\Evento;
use App\Models\Coordinate;
use App\Models\Route;
use App\Models\Category;
use App\Models\FormType;
use App\Models\Souvenir;
use App\Models\PromoCode;
use Illuminate\Support\Facades\DB;

class EventoService
{
    public function create(EventoDTO $dto): Evento
    {
        return DB::transaction(function () use ($dto) {

            $evento = Evento::create([
                'nombre'              => $dto->nombre,
                'descripcion'         => $dto->descripcion,
                'longDescription'     => $dto->longDescription,
                'fecha_inicio'        => $dto->fechaInicio,
                'direccion'           => $dto->direccion,
                'hasDonation'         => $dto->hasDonation,
                'video_url'           => $dto->videoUrl,
                'imagen_portada_url'  => $dto->imagenPortadaUrl,
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
                'color'                 => $formTypeDTO->color,
                'moneda'                => $formTypeDTO->moneda,
                'permite_lista_espera'  => $formTypeDTO->permiteListaEspera,
                'hasshirt'              => $formTypeDTO->hasshirt,
                'requiere_talla'        => $formTypeDTO->requiereTalla,
            ]);

            $this->createSouvenirs($formType, $formTypeDTO->souvenirs);
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

    private function createPromoCodes(Evento $evento, EventoDTO $dto): void
    {
        if (empty($dto->promoCodes)) return;

        $data = array_map(fn($p) => [
            'event_id'    => $evento->id,
            'promo_code'  => $p->promoCode,
            'price'       => $p->price,
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
            'promoCodes',
        ]);
    }
}
