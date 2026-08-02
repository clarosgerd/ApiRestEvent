<?php

namespace App\Http\Controllers;

use App\Models\FormType;
use App\Models\Registration;
use App\Http\Requests\StoreFormTypeRequest;
use App\Http\Requests\UpdateFormTypeRequest;
use App\Http\Resources\FormTypeCollection;
use App\Http\Resources\FormTypeResource;
use App\Services\FormTypeService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Filters\FormTypeFilter;
use App\Http\Controllers\Concerns\AuthorizesEventoScope;
use App\Services\AdminAuditLogger;


class FormTypeController extends Controller
{
    use AuthorizesEventoScope;

    public function __construct(
        private readonly FormTypeService $service
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        //
         $filter = new FormTypeFilter();
        $filterItems = $filter->transform($request); // [['column','operator','value']]
        $formType = FormType::where($filterItems);
        return new FormTypeCollection($formType->paginate()->appends($request->query()) );
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
    public function store(StoreFormTypeRequest $request): JsonResponse
    {
        $data = $request->validated();
        $this->assertCanWriteEvento((int) $data['event_id']);

        $formType = $this->service->create($data);

        AdminAuditLogger::log('create', 'form_type', $formType->id, (int) $formType->event_id, null, $formType->toArray());

        return response()->json([
            'success'  => true,
            'message'  => 'Tipo de formulario creado correctamente.',
            'formType' => new FormTypeResource($formType),
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(FormType $form_type)
    {
        //

          $includeFormType = request()->query('includeFormType');
        if ($includeFormType) {
            return new FormTypeResource($form_type->loadMissing('souvenirs'));
        }

        return new FormTypeResource($form_type);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(FormType $form_type)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateFormTypeRequest $request, FormType $form_type): JsonResponse
    {
        $this->assertCanWriteEvento((int) $form_type->event_id);

        $before = $form_type->toArray();
        $formType = $this->service->update($form_type, $request->validated());

        AdminAuditLogger::log('update', 'form_type', $formType->id, (int) $formType->event_id, $before, $formType->toArray());

        return response()->json([
            'success'  => true,
            'message'  => 'Tipo de formulario actualizado correctamente.',
            'formType' => new FormTypeResource($formType),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * Bloquea (409) si existe alguna inscripción hecha con este form_type.
     */
    public function destroy(FormType $form_type): JsonResponse
    {
        $this->assertCanWriteEvento((int) $form_type->event_id);

        $enUso = Registration::where('form_types_id', $form_type->id)->exists();

        if ($enUso) {
            return response()->json([
                'success' => false,
                'error'   => 'No se puede eliminar este tipo de formulario: ya tiene inscripciones.',
            ], 409);
        }

        $before = $form_type->toArray();
        $form_type->delete();

        AdminAuditLogger::log('delete', 'form_type', $form_type->id, (int) $form_type->event_id, $before, null);

        return response()->json([
            'success' => true,
            'message' => 'Tipo de formulario eliminado correctamente.',
        ]);
    }
}
