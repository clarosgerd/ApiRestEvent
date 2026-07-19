<?php

namespace App\Http\Controllers;

use App\Models\FormType;
use App\Http\Requests\StoreFormTypeRequest;
use App\Http\Requests\UpdateFormTypeRequest;
use App\Http\Resources\FormTypeCollection;
use App\Http\Resources\FormTypeResource;
use Illuminate\Http\Request;
use App\Filters\FormTypeFilter;


class FormTypeController extends Controller
{
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
    public function store(StoreFormTypeRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(FormType $formType)
    {
        //

          $includeFormType = request()->query('includeFormType');
        if ($includeFormType) {
            return new FormTypeResource($formType->loadMissing('souvenirs'));
        }

        return new FormTypeResource($formType);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(FormType $formType)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateFormTypeRequest $request, FormType $formType)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(FormType $formType)
    {
        //
    }
}
