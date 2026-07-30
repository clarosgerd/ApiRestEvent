<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgendaItem extends Model
{
    protected $table = 'agenda_items';

    protected $fillable = [
        'event_id',
        'form_type_id',
        'fecha',
        'hora_inicio',
        'hora_fin',
        'titulo',
        'descripcion',
        'ponente',
        'ponente_cargo',
        'sala',
        'icono',
        'orden',
    ];

    public function evento()
    {
        return $this->belongsTo(Evento::class, 'event_id');
    }

    public function formType()
    {
        return $this->belongsTo(FormType::class, 'form_type_id');
    }
}
