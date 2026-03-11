<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CallCenterReportHiddenRow extends Model
{
    use HasFactory;

    protected $fillable = [
        'call_center_report_id',
        'master_dataset_row_id',
        'hidden_by_user_id',
        'hidden_at',
    ];

    protected $casts = [
        'hidden_at' => 'datetime',
    ];

    public function report()
    {
        return $this->belongsTo(CallCenterReport::class, 'call_center_report_id');
    }

    public function row()
    {
        return $this->belongsTo(MasterDatasetRow::class, 'master_dataset_row_id');
    }

    public function hiddenBy()
    {
        return $this->belongsTo(User::class, 'hidden_by_user_id');
    }
}
