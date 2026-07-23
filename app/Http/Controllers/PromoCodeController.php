<?php

namespace App\Http\Controllers;

use App\Models\PromoCode;
use App\Http\Requests\StorePromoCodeRequest;
use App\Http\Requests\UpdatePromoCodeRequest;
use Illuminate\Support\Arr;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Resources\PromoCodeResource;
use App\Http\Resources\PromoCodeCollection;
use App\Filters\PromoCodeFilter;    
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

     public function promoCode(Request $request,string $id ,string $promocode):JsonResponse
    {
        //
//SendWhatsappMessageJob::dispatch('+59175925001@c.us', 'Hola, tu pedido está listo 📦');


    // dd($promocode);
        
        $promo = PromoCode::where([['event_id', '=', $id],['promo_code', '=', $promocode]])->get();
       // $promo = $eventos->paginate()->appends($request->query());
        //dd($eventos);
       // $eventos = Evento::paginate(15);
        $collection = PromoCodeResource::collection( $promo);
//dd($collection);

        if ($promo->isNotEmpty() && $promo->first()->usado) {
            return response()->json([
                'success' => false,
                'error' => 'Este código de promoción ya fue utilizado.',
            ]);
        }

        if ($collection->isNotEmpty())
            {
            return response()->json([
                'success' => true,
                'data' => $collection,

            ]);
            }else {
                 return response()->json([
                'success' =>false,
                'error' => 'no existe la promo para ese evento',
                
            ]);
            }
    }
}
