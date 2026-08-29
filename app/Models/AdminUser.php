<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class AdminUser extends Authenticatable
{
    use HasApiTokens, HasFactory;

    protected $table = 'admin_users';

    protected $fillable = [
        'nombre',
        'email',
        'password',
        'rol',
        'evento_id',
        'activo',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'evento_id' => 'integer',
    ];

    public function evento(): BelongsTo
    {
        return $this->belongsTo(Evento::class, 'evento_id');
    }

    /**
     * Admin de evento asignado a varios eventos (28/08/2026) — ver
     * PLAN-ADMIN-MULTI-EVENTO-28082026.md. `evento_id` sigue siendo el
     * "evento principal" (sin cambios); esta relación son los eventos
     * ADICIONALES, 100% opt-in — solo tiene sentido para rol `admin`.
     */
    public function eventosAdicionales(): BelongsToMany
    {
        return $this->belongsToMany(Evento::class, 'admin_user_evento', 'admin_user_id', 'evento_id');
    }

    /**
     * Unión de evento_id (principal) + eventosAdicionales, deduplicada.
     * Método de conveniencia para no repetir esta unión en cada
     * consumidor (login, dashboard, filtros de auditoría, etc.).
     *
     * @return array<int, int>
     */
    public function eventoIds(): array
    {
        $ids = $this->eventosAdicionales->pluck('id')->all();

        if ($this->evento_id !== null) {
            $ids[] = $this->evento_id;
        }

        return array_values(array_unique($ids));
    }

    /**
     * Centraliza la regla de acceso por evento que antes estaba repetida
     * a mano en varios controllers (comparación `!==` inline). `cajero`
     * sigue comparando solo `evento_id` — no participa de
     * `eventosAdicionales`, decisión explícita del usuario (28/08/2026).
     */
    public function tieneAccesoAEvento(int $eventoId): bool
    {
        if ($this->rol === 'super_admin') {
            return true;
        }

        if ($this->rol === 'cajero') {
            return (int) $this->evento_id === $eventoId;
        }

        if ($this->rol === 'admin') {
            return (int) $this->evento_id === $eventoId
                || $this->eventosAdicionales()->where('eventos.id', $eventoId)->exists();
        }

        return false;
    }
}
