<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CallCenterInteraction extends Model
{
    use HasFactory;

    protected $table = 'call_center_interactions';

    protected $fillable = [
        'assignment_id',
        'agent_id',
        'account_number',
        'outcome',
        'note',
        'payment_expected_at',
        'paid',
        'payment_date',
        'paid_amount',
    ];

    protected $casts = [
        'paid' => 'boolean',
        'payment_expected_at' => 'date',
        'payment_date' => 'date',
        'paid_amount' => 'decimal:2',
    ];

    public function assignment()
    {
        return $this->belongsTo(CallCenterAssignment::class, 'assignment_id');
    }

    public function agent()
    {
        return $this->belongsTo(User::class, 'agent_id');
    }
}
