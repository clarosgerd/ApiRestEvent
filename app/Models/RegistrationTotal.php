<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RegistrationTotal extends Model
{
    /** @use HasFactory<\Database\Factories\RegistrationTotalFactory> */
    use HasFactory;
    protected $fillable = [
        'registration_id',
        'inscripcion',
        'donacion',
        'souvenirs',
        'fee',
        'descuento',
        'grand_total',
    ];

    protected $casts = [
        'inscripcion' => 'decimal:2',
        'donacion' => 'decimal:2',
        'souvenirs' => 'decimal:2',
        'fee' => 'decimal:2',
        'descuento' => 'decimal:2',
        'grand_total' => 'decimal:2',
    ];

    public function registration(): BelongsTo
    {
        return $this->belongsTo(Registration::class);
    }
}
