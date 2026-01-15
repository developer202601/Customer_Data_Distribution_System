<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    use HasFactory;

    /**
     * Indicates if the model should be timestamped.
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'username',
        'admin_prev',
        'system',
        'created_at',
        'fixed',
        'status',
        'name',
        'assignment',
        'supervisor',
    ];

    /**
     * The attributes that should be casted to native types.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'admin_prev' => 'boolean',
        'fixed' => 'boolean',
        'status' => 'boolean',
        'created_at' => 'datetime',
        'supervisor' => 'integer',
    ];

    public function supervisedUsers()
    {
        return $this->hasMany(User::class, 'supervisor');
    }
}
