<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\MasterDatasetProcess;

class CallCenterReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'master_dataset_process_id',
        'token',
        'dataset_month',
        'row_count',
        'row_ids',
    ];

    protected $casts = [
        'row_count' => 'integer',
        'row_ids' => 'array',
    ];

    public function process(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(MasterDatasetProcess::class, 'master_dataset_process_id');
    }
}
