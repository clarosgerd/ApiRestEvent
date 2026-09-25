<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

/**
 * SmartStand (25/09/2026) — cuenta de una empresa expositora en UN evento.
 * Actor con login propio (guard Sanctum `expositores`), mismo molde que
 * `Club`. Se crea sola al confirmarse el pago de una inscripción con
 * form_type `es_expositor` (ProvisionarCuentaExpositorAction) o a mano por el
 * organizador.
 */
class EmpresaExpositora extends Authenticatable
{
    use HasApiTokens;

    protected $table = 'empresas_expositoras';

    protected $fillable = [
        'evento_id',
        'registration_id',
        'nombre',
        'email',
        'password',
        'categoria_id',
        'stand',
        'activo',
        'credenciales_enviadas_at',
    ];

    protected $hidden = ['password'];

    protected $casts = [
        'activo'                   => 'boolean',
        'credenciales_enviadas_at' => 'datetime',
    ];

    public function evento(): BelongsTo
    {
        return $this->belongsTo(Evento::class, 'evento_id');
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(Registration::class);
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'categoria_id');
    }

    public function leads(): HasMany
    {
        return $this->hasMany(LeadCapturado::class, 'empresa_expositora_id');
    }
}
