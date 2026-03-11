<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\CallCenterAssignment;
use App\Models\CallCenterInteraction;
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

    public function assignments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(CallCenterAssignment::class, 'call_center_report_id');
    }

    public function hiddenRows(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(CallCenterReportHiddenRow::class, 'call_center_report_id');
    }

    public function regionReviews(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(CallCenterReportRegionReview::class, 'call_center_report_id');
    }

    public function interactions(): \Illuminate\Database\Eloquent\Relations\HasManyThrough
    {
        return $this->hasManyThrough(
            CallCenterInteraction::class,
            CallCenterAssignment::class,
            'call_center_report_id',
            'assignment_id'
        );
    }
}
