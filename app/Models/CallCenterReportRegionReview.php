<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CallCenterReportRegionReview extends Model
{
    use HasFactory;

    protected $fillable = [
        'call_center_report_id',
        'report_type',
        'region_name',
        'reviewed_by_user_id',
        'reviewed_at',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    public function report()
    {
        return $this->belongsTo(CallCenterReport::class, 'call_center_report_id');
    }

    public function reviewedBy()
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }
}
