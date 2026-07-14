<?php

namespace App\Http\Controllers;

use App\Models\PromoCode;
use App\Http\Requests\StorePromoCodeRequest;
use App\Http\Requests\UpdatePromoCodeRequest;

class PromoCodeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        //
         $filter = new PromoCodeFilter();
        $filterItems = $filter->transform($request); // [['column','operator','value']]
        $promoCode = PromoCode::where($filterItems);
        return new PromoCodeCollection($promoCode->paginate()->appends($request->query()) );
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
    public function store(StorePromoCodeRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(PromoCode $promoCode)
    {
        //
        $includePromoCode = request()->query('includePromoCode');
        if ($includePromoCode) {
            return new PromoCodeResource($promoCode->loadMissing('promoCodes'));
        }

        return new PromoCodeResource($promoCode);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(PromoCode $promoCode)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdatePromoCodeRequest $request, PromoCode $promoCode)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(PromoCode $promoCode)
    {
        //
    }
}
