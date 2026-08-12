<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Lista de espera de un form_type con cupo lleno, o de un ítem/talla
 * puntual agotado — ver
 * ApiRestEvent/brain/api_rest_event/PRD-kit-tallas-stock-lista-espera.md.
 * `souvenir_id`/`talla`/`sexo` nulos = la espera es por cupo general.
 * Promoción automática y notificación por email — ver
 * App\Actions\PromoverListaEsperaAction.
 */
class ListaEspera extends Model
{
    protected $table = 'lista_espera';

    protected $fillable = [
        'evento_id',
        'form_types_id',
        'souvenir_id',
        'talla',
        'sexo',
        'nombre',
        'correo',
        'telefono',
        'estado',
        'promovido_at',
    ];

    protected $casts = [
        'promovido_at' => 'datetime',
    ];

    public function evento(): BelongsTo
    {
        return $this->belongsTo(Evento::class, 'evento_id');
    }

    public function formType(): BelongsTo
    {
        return $this->belongsTo(FormType::class, 'form_types_id');
    }

    public function souvenir(): BelongsTo
    {
        return $this->belongsTo(Souvenir::class, 'souvenir_id');
    }
}
