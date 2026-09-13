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


     /**
      * Fix real (13/09/2026) — antes usaba `belongsTo('App\Models\Evento',
      * 'id')`, donde 'id' se interpreta como $foreignKey (columna en
      * promo_codes), no como owner key: la query armaba
      * `eventos.id = promo_codes.id`, ignorando la FK real (`event_id`).
      * Confirmado sin call sites en ningún repo antes de este fix — era
      * código muerto, nunca causó un bug activo, pero devolvía datos
      * incorrectos/null para quien lo llamara.
      */
     public function evento()  {
        return $this->belongsTo(Evento::class, 'event_id');
     }

    public function usages(): HasMany
    {
        return $this->hasMany(PromoCodeUsage::class);
    }
}
