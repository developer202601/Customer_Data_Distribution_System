<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MasterUpload extends Model
{
    use HasFactory;

    protected $fillable = [
        'token',
        'user_id',
        'original_name',
        'status',
        'progress',
        'message',
        'processed_rows',
        'total_rows',
        'file_size',
        'mime_type',
        'error',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'started_at' => 'integer',
        'finished_at' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
