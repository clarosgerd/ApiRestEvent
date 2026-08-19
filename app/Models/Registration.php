<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Notifications\Notifiable;


class Registration extends Model
{
    /** @use HasFactory<\Database\Factories\RegistrationFactory> */
    use HasFactory,Notifiable;
     protected $fillable = [
        'referencia',
        'fecha',
        'evento_id',
        'form_types_id',
        'evento_nombre',
        'tipo_pago',
        'pago_status',
        'pay_order_number',
        // Inscripción en BOB y USD (18/08/2026) — ver
        // brain/PLAN-INSCRIPCION-BOB-USD-IMPLEMENTACION.md. Snapshot de la
        // moneda y la tasa al confirmar el pago (USD) o null/BOB en el
        // camino legacy. Default 'BOB' en la BD.
        'moneda_pago',
        'tipo_cambio_aplicado',
        'total_pagado',
        'origen_legado', // ETL de datos históricos 2014-hoy, ver elascenso/event/brain/
    ];

    protected $casts = [
        'fecha' => 'datetime',
        'moneda_pago' => 'string',
        'tipo_cambio_aplicado' => 'decimal:4',
        'total_pagado' => 'decimal:2',
    ];

    public function totals(): HasOne
    {
        return $this->hasOne(RegistrationTotal::class);
    }

    public function participants(): HasMany
    {
        return $this->hasMany(Participante::class);
    }

    public function formType(): BelongsTo
    {
        return $this->belongsTo(FormType::class, 'form_types_id');
    }

    public function evento(): BelongsTo
    {
        return $this->belongsTo(Evento::class, 'evento_id');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }
}
