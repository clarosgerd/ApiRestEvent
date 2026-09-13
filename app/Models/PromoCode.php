<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PromoCode extends Model
{
    /** @use HasFactory<\Database\Factories\PromoCodeFactory> */
    use HasFactory;
      public $timestamps = false;
     protected $fillable = [
        'event_id',
        'promo_code',
        'price',
        'discount_type',
        'discount_percent',
        'status',
        'usado',
        'registration_id',
        // Multi-uso (13/09/2026) — `usado` ahora significa "agotado"
        // (times_used >= max_uses), mantenido físicamente/en sync para que
        // los lectores existentes (admin-eventos, elascenso/event,
        // PromoCodeController::promoCode()) no necesiten cambios. Ver
        // RegistrationService::consumePromoCode()/releasePromoCodes().
        'max_uses',
        'times_used',
    ];


     public function evento()  {
        return $this->belongsTo('App\Models\Evento','id');
     }

    public function usages(): HasMany
    {
        return $this->hasMany(PromoCodeUsage::class);
    }
}
