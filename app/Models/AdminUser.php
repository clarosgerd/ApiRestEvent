<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class AdminUser extends Authenticatable
{
    use HasApiTokens;

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
    ];

    public function evento(): BelongsTo
    {
        return $this->belongsTo(Evento::class, 'evento_id');
    }
}
