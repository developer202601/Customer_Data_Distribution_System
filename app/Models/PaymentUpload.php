<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentUpload extends Model
{
    protected $fillable = [
        'token',
        'user_id',
        'original_name',
        'status',
        'progress',
        'message',
        'processed_rows',
        'total_rows',
        'matched',
        'updated',
        'not_found',
        'error',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'started_at' => 'integer',
        'finished_at' => 'integer',
        'progress' => 'integer',
        'processed_rows' => 'integer',
        'total_rows' => 'integer',
        'matched' => 'integer',
        'updated' => 'integer',
        'not_found' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
