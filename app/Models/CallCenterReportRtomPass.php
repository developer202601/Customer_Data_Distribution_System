<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CallCenterReportRtomPass extends Model
{
    protected $table = 'call_center_report_rtom_passes';

    protected $fillable = [
        'call_center_report_id',
        'region_name',
        'rtom',
        'passed_by_user_id',
        'passed_at',
    ];

    protected $casts = [
        'passed_at' => 'datetime',
    ];

    public function report()
    {
        return $this->belongsTo(CallCenterReport::class, 'call_center_report_id');
    }

    public function passedBy()
    {
        return $this->belongsTo(User::class, 'passed_by_user_id');
    }
}
