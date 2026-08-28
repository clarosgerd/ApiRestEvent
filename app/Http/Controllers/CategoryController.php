<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\FormType;
use App\Models\Participante;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Filters\CategoryFilter;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\CategoryCollection;
use App\Http\Controllers\Concerns\AuthorizesEventoScope;
use App\Services\AdminAuditLogger;
class CategoryController extends Controller
{
    use AuthorizesEventoScope;

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        //
         $filter = new CategoryFilter();
        $filterItems = $filter->transform($request); // [['column','operator','value']]
        // .pricePeriods eager-cargado para que CategoryResource calcule
        // precio_vigente sin N+1 — ver PRD-precios-periodos-fechas.md.
        $category = Category::where($filterItems)->with('pricePeriods');
        return new CategoryCollection($category->paginate()->appends($request->query()) );
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $data = $request->validated();
        $this->assertCanWriteEvento((int) $data['event_id']);

        try {
            $this->assertFormularioPerteneceAlEvento($data['formulario_id'] ?? null, (int) $data['event_id']);
        } catch (\DomainException $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 422);
        }

        $category = Category::create($data);

        AdminAuditLogger::log('create', 'categoria', $category->id, (int) $category->event_id, null, $category->toArray());

        return response()->json([
            'success'  => true,
            'message'  => 'Categoría creada correctamente.',
            'category' => new CategoryResource($category),
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Category $category)
    {
        //

         return new CategoryResource($category);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Category $category)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateCategoryRequest $request, Category $category): JsonResponse
    {
        $this->assertCanWriteEvento((int) $category->event_id);

        $data = $request->validated();
        if (array_key_exists('formulario_id', $data)) {
            try {
                $this->assertFormularioPerteneceAlEvento($data['formulario_id'], (int) $category->event_id);
            } catch (\DomainException $e) {
                return response()->json(['success' => false, 'error' => $e->getMessage()], 422);
            }
        }

        $before = $category->toArray();
        $category->update($data);

        AdminAuditLogger::log('update', 'categoria', $category->id, (int) $category->event_id, $before, $category->toArray());

        return response()->json([
            'success'  => true,
            'message'  => 'Categoría actualizada correctamente.',
            'category' => new CategoryResource($category),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * Bloquea (409) si algún participante del evento ya se inscribió con
     * esta categoría — `Participante.categoria` guarda el id de la
     * categoría (ver certificadosPdf/gafetesPdf en EventoController).
     * Regla confirmada por el usuario: aplica siempre, sin importar si el
     * evento está publicado.
     */
    public function destroy(Category $category): JsonResponse
    {
        $this->assertCanWriteEvento((int) $category->event_id);

        $enUso = Participante::where('categoria', (string) $category->id)
            ->whereHas('registration', fn ($q) => $q->where('evento_id', $category->event_id))
            ->exists();

        if ($enUso) {
            return response()->json([
                'success' => false,
                'error'   => 'No se puede eliminar esta categoría: ya tiene participantes inscritos.',
            ], 409);
        }

        $before = $category->toArray();
        $category->delete();

        AdminAuditLogger::log('delete', 'categoria', $category->id, (int) $category->event_id, $before, null);

        return response()->json([
            'success' => true,
            'message' => 'Categoría eliminada correctamente.',
        ]);
    }

    /**
     * Categorías por form_type (27/08/2026) — ver
     * PLAN-CATEGORIAS-POR-FORM-TYPE-27082026.md. `formulario_id = null`
     * sigue significando "categoría compartida por todos los form_types
     * del evento" (comportamiento actual, no rompe nada). Cuando SÍ se
     * manda un valor, nada garantizaba antes que ese form_type fuera del
     * mismo evento que la categoría — se cierra acá.
     */
    private function assertFormularioPerteneceAlEvento(?int $formularioId, int $eventId): void
    {
        if ($formularioId === null) {
            return;
        }

        $perteneceAlEvento = FormType::where('id', $formularioId)
            ->where('event_id', $eventId)
            ->exists();

        if (!$perteneceAlEvento) {
            throw new \DomainException('El tipo de formulario indicado no pertenece a este evento.');
        }
    }
}
