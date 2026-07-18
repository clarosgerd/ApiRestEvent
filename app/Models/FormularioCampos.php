<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\QuestionOptions;

class FormularioCampos extends Model
{
    /** @use HasFactory<\Database\Factories\FormularioCamposFactory> */
    use HasFactory;
 protected $table ='questions';
     protected $fillable = [
        'form_types_id',
        'seccion',
        'nombre_campo',
        'etiqueta',
        'tipo_input',
        'opciones',
        'placeholder',
        'obligatorio',
        'visible_en_reporte',
        'orden',
    ];
protected $casts = [
        'obligatorio' => 'boolean',
    ];
     public function formType()  {
        return $this->belongsTo('App\Models\FormType','form_types_id','id');
     }
     public function options() {
        return $this->hasMany( QuestionOptions::class, 'question_id' )->orderBy( 'order' );
    }
}
