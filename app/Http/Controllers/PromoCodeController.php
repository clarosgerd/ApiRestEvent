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
use App\Http\Controllers\Concerns\AuthorizesEventoScope;
use App\Services\AdminAuditLogger;
class PromoCodeController extends Controller
{
    use AuthorizesEventoScope;

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
    public function store(StorePromoCodeRequest $request): JsonResponse
    {
        $data = $request->validated();
        $this->assertCanWriteEvento((int) $data['event_id']);

        $promoCode = PromoCode::create($data);

        AdminAuditLogger::log('create', 'promo_code', $promoCode->id, (int) $promoCode->event_id, null, $promoCode->toArray());

        return response()->json([
            'success'   => true,
            'message'   => 'Código de promoción creado correctamente.',
            'promoCode' => new PromoCodeResource($promoCode),
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(PromoCode $promo_code)
    {
        //
        $includePromoCode = request()->query('includePromoCode');
        if ($includePromoCode) {
            return new PromoCodeResource($promo_code->loadMissing('promoCodes'));
        }

        return new PromoCodeResource($promo_code);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(PromoCode $promo_code)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdatePromoCodeRequest $request, PromoCode $promo_code): JsonResponse
    {
        $this->assertCanWriteEvento((int) $promo_code->event_id);

        $before = $promo_code->toArray();
        $promo_code->update($request->validated());

        AdminAuditLogger::log('update', 'promo_code', $promo_code->id, (int) $promo_code->event_id, $before, $promo_code->toArray());

        return response()->json([
            'success'   => true,
            'message'   => 'Código de promoción actualizado correctamente.',
            'promoCode' => new PromoCodeResource($promo_code),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * Bloquea (409) si el código ya fue usado — PromoCode trackea su
     * propio uso (`usado` + `registration_id`), no hace falta consultar
     * Participante.
     */
    public function destroy(PromoCode $promo_code): JsonResponse
    {
        $this->assertCanWriteEvento((int) $promo_code->event_id);

        if ($promo_code->usado || $promo_code->registration_id) {
            return response()->json([
                'success' => false,
                'error'   => 'No se puede eliminar este código de promoción: ya fue utilizado.',
            ], 409);
        }

        $before = $promo_code->toArray();
        $promo_code->delete();

        AdminAuditLogger::log('delete', 'promo_code', $promo_code->id, (int) $promo_code->event_id, $before, null);

        return response()->json([
            'success' => true,
            'message' => 'Código de promoción eliminado correctamente.',
        ]);
    }

     public function promoCode(Request $request,string $id ,string $promocode):JsonResponse
    {
        //
//SendWhatsappMessageJob::dispatch('+59175925001@c.us', 'Hola, tu pedido está listo 📦');


    // dd($promocode);
        
        // La columna promo_code tiene collation case-insensitive (utf8mb4_*_ci),
        // así que '=' normal matchea 'descuento20' con 'DESCUENTO20' — los
        // códigos de promoción son case-sensitive por diseño, se fuerza la
        // comparación exacta con BINARY.
        $promo = PromoCode::where('event_id', $id)
            ->whereRaw('BINARY promo_code = ?', [$promocode])
            ->get();
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
