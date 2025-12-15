<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;


class Configurations extends Model
{
    use HasFactory;

    protected $table = 'configurations';

    protected $fillable = [
        'config_name',
        'value',
        'changedby_id'
    ];    

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changedby_id');
    }
}
