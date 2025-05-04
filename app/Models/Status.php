<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class Status extends Model
{
    use HasFactory;
    protected $fillable = ['user_id', 'text', 'expires_at', 'is_active'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function media(): MorphOne
    {
        return $this->morphOne(Media::class, 'mediable');
    }
}
