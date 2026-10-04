<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class Persona extends Authenticatable
{
    use HasApiTokens, HasFactory;

    protected $table = 'personas';

    protected $fillable = [
        'email',
        'password',
        'nombre',
        'apellido',
        'alias',
        'sexo',
        'tipo_documento',
        'numero_documento',
        'fecha_nacimiento',
        'correo',
        'direccion',
        'ciudad',
        'telefono',
        'celular',
        'token',
        'acepta_marketing',
        'ultimo_envio_marketing_at',
    ];

    protected $hidden = [
        'password',
        'token',
    ];

    protected $casts = [
        'acepta_marketing'          => 'boolean',
        'ultimo_envio_marketing_at' => 'datetime',
    ];

    public function contactoEmergencia()
    {
        return $this->hasOne(ContactoEmergencia::class);
    }

    /**
     * App de staff offline (02/10/2026) — ¿esta Persona está inscripta como
     * staff (form_type.es_staff=true) de este evento, con el pago
     * confirmado? No hay FK directa Persona→Participante (Persona es una
     * cuenta derivada, ver RegistrationService::syncPersonas()) — se
     * resuelve por `email` (único) primero, `numero_documento` como
     * fallback, mismo criterio anti-colisión que syncPersonas() (nunca
     * `orWhere`, que podría matchear el participante equivocado si dos
     * personas comparten documento).
     */
    public function participanteStaffParaEvento(Evento $evento): ?Participante
    {
        $query = fn () => Participante::whereHas('registration', fn ($q) => $q->where('evento_id', $evento->id)
            ->where('pago_status', 'paid')
            ->whereHas('formType', fn ($q2) => $q2->where('es_staff', true)));

        return $query()->where('correo', $this->email)->first()
            ?? $query()->where('numero_documento', $this->numero_documento)->first();
    }
}
