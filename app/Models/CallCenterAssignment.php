<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CallCenterAssignment extends Model
{
    use HasFactory;

    protected $table = 'call_center_row_assignments';

    protected $fillable = [
        'call_center_report_id',
        'master_dataset_row_id',
        'assigned_user_id',
        'status',
        'locked_at',
        'locked_by',
        'accepted',
        'accepted_at',
        'rejected',
        'rejected_at',
        'rejected_by',
        'rejection_note',
    ];

    protected $casts = [
        'locked_at' => 'datetime',
        'accepted' => 'boolean',
        'accepted_at' => 'datetime',
        'rejected' => 'boolean',
        'rejected_at' => 'datetime',
    ];

    public function report()
    {
        return $this->belongsTo(CallCenterReport::class, 'call_center_report_id');
    }

    public function row()
    {
        return $this->belongsTo(MasterDatasetRow::class, 'master_dataset_row_id');
    }

    public function agent()
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function interactions()
    {
        return $this->hasMany(CallCenterInteraction::class, 'assignment_id');
    }

    public function rejectedBy()
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function scopeRejected($query)
    {
        return $query->where('rejected', true);
    }

    public function scopePendingApproval($query)
    {
        return $query->where('accepted', false)->where('rejected', false);
    }
}
