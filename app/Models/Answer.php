<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Answer extends Model
{
    /** @use HasFactory<\Database\Factories\AnswerFactory> */
     use HasFactory;
    protected $fillable = [ 'form_types_id', 'question_id','participante_id', 'value' ];


 public function participante(): BelongsTo
    {
        return $this->belongsTo(Participante::class);
    }

}
