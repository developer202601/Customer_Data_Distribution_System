<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DatasetExport extends Model
{
    use HasFactory;

    protected $fillable = [
        'token',
        'group',
        'bucket',
        'label',
        'filename',
        'file_path',
        'file_disk',
        'file_size',
        'file_hash',
        'user_id',
        'user_name',
        'generated_at',
        'status',
        'meta',
    ];

    protected $casts = [
        'generated_at' => 'datetime',
        'meta' => 'array',
    ];
}
