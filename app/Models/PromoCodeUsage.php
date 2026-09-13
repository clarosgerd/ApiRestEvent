<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Multi-uso de promo codes (13/09/2026) — una fila por cada participante
 * que aplicó un código, independiente de si comparte inscripción con otros
 * (decisión confirmada: "cada participante que lo aplica" es lo que cuenta
 * como un uso). Ver RegistrationService::consumePromoCode()/
 * releasePromoCodes() y PromoCodeReporteData::paraEvento().
 *
 * Sin timestamps propios de Eloquent — `used_at` lo pone el servicio
 * explícitamente, mismo criterio que PromoCode ($timestamps = false).
 */
class PromoCodeUsage extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'promo_code_id',
        'registration_id',
        'participante_id',
        'monto_descontado',
        'used_at',
    ];

    protected $casts = [
        'used_at' => 'datetime',
    ];

    public function promoCode(): BelongsTo
    {
        return $this->belongsTo(PromoCode::class);
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(Registration::class);
    }

    public function participante(): BelongsTo
    {
        return $this->belongsTo(Participante::class);
    }
}
