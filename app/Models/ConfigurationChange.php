<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConfigurationChange extends Model
{
    use HasFactory;

    protected $table = 'configuration_changes';

    protected $fillable = [
        'configuration_id',
        'config_key',
        'old_value',
        'new_value',
        'user_id'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function configuration()
    {
        return $this->belongsTo(Configuration::class);
    }
}
