<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Organizador extends Model
{
    /** @use HasFactory<\Database\Factories\OrganizadorFactory> */
    use HasFactory;
    protected $table = 'organizadores';
    protected $fillable = [
        'razon_social',
        'nombre_comercial',
        'rut_nit',
        'email',
        'telefono',
        'pais_id',
        'ciudad_id',
        'direccion',
        'logo_url',
        'plan_id',
        'comision_especial',
        'convenio_notas',
        'activo',
        'dias_recordatorio_pendiente_1',
        'dias_recordatorio_pendiente_2',
        'dias_gracia_reversion',
        'dias_recordatorio_kit',
        'dia_envio_marketing',
        'whatsapp_canal',
    ];

    protected $casts = [
        'comision_especial' => 'decimal:2',
        'activo' => 'boolean',
        'dias_recordatorio_pendiente_1' => 'integer',
        'dias_recordatorio_pendiente_2' => 'integer',
        'dias_gracia_reversion' => 'integer',
        'dias_recordatorio_kit' => 'integer',
        'dia_envio_marketing' => 'integer',
    ];

    public function pais()
    {
        return $this->belongsTo(Pais::class, 'pais_id');
    }

    public function ciudad()
    {
        return $this->belongsTo(Ciudad::class, 'ciudad_id');
    }

    public function eventos()
    {
        return $this->hasMany(Evento::class, 'organizador_id');
    }

    /**
     * Métodos de pago propios de este organizador (su convenio/gateway o
     * instrucciones manuales) — no los del sistema.
     */
    public function formasPagoPropias()
    {
        return $this->hasMany(FormasPago::class, 'organizador_id');
    }

    /**
     * Combinación que este organizador eligió vía el pivote
     * organizador_formas_pago (puede incluir métodos del sistema, los
     * suyos propios, o ambos — es la fuente de verdad que edita el panel de
     * administración).
     */
    public function formasPagoSeleccionadas()
    {
        return $this->belongsToMany(FormasPago::class, 'organizador_formas_pago', 'organizador_id', 'forma_pago_id')
            ->wherePivot('activo', true)
            ->withPivot('activo', 'link_pago')
            ->withTimestamps();
    }

    /**
     * Link configurado para el método "Pago pendiente (USD)" — null si el
     * organizador no lo activó o no cargó un link todavía (ver
     * EventoResource::formasPago(), que usa esto para decidir si el
     * método se ofrece en un evento usdPrecioFijo, y
     * InscripcionPendienteMail, que lo usa para el correo).
     */
    public function linkPagoPendienteUsd(): ?string
    {
        return $this->formasPagoSeleccionadas()
            ->where('slug', 'pendiente_usd')
            ->first()?->pivot?->link_pago;
    }

    /**
     * Lista efectiva de métodos de pago para eventos de este organizador:
     * lo que eligió vía el pivote, o si todavía no fue configurado (pivote
     * vacío — organizador nuevo, pendiente de pasar por el panel de
     * administración), los métodos del sistema por defecto.
     */
    public function formasPagoEfectivas()
    {
        $seleccionadas = $this->relationLoaded('formasPagoSeleccionadas')
            ? $this->formasPagoSeleccionadas
            : $this->formasPagoSeleccionadas()->get();

        if ($seleccionadas->isNotEmpty()) {
            return $seleccionadas;
        }

        return FormasPago::whereNull('organizador_id')->where('activo', true)->get();
    }
}
