<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class Club extends Authenticatable
{
    use HasApiTokens;

    protected $table = 'clubes';

    protected $fillable = [
        'nombre',
        'email',
        'password',
        'activo',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function equipos(): HasMany
    {
        return $this->hasMany(Equipo::class, 'club_id');
    }
}
