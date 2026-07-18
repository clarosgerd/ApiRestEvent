<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\FormularioCampos;

class QuestionOptions extends Model
{
    /** @use HasFactory<\Database\Factories\QuestionOptionsFactory> */
    use HasFactory;

    
    protected $fillable = [ 
        'question_id', 
        'option_text', 
        'order'
     ];

    public function question() {
        return $this->belongsTo( FormularioCampos::class );
    }
  
}
