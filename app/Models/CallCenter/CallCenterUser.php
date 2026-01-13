<?php

namespace App\Models\CallCenter;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CallCenterUser extends User
{
    /**
     * Default attribute values for call center users.
     *
     * @var array<string, int|string|bool>
     */
    protected $attributes = [
        'system' => 'cc',
        'status' => 1,
        'fixed' => 0,
    ];

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'users';

    /**
     * Apply a global scope to constrain to call center users only.
     */
    protected static function booted(): void
    {
        static::addGlobalScope('call-center', function (Builder $builder): void {
            $builder->where('system', 'cc');
        });
    }

    /**
     * Scope for only active call center users.
     */
    public function scopeActive(Builder $builder): Builder
    {
        return $builder->where('status', true);
    }

    public function isAdmin(): bool
    {
        return (bool) $this->admin_prev;
    }

    /**
     * Supervisor relationship (the user who created / owns this caller).
     */
    public function supervisorUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor');
    }
}
