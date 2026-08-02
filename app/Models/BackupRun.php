<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BackupRun extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'type',
        'status',
        'disk',
        'filename',
        'size_bytes',
        'error_message',
        'triggered_by_user_id',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function triggeredBy()
    {
        return $this->belongsTo(User::class, 'triggered_by_user_id');
    }
}
