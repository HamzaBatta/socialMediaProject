<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Status extends Model
{
    use HasFactory;
    protected $casts = [
        'expiration_date' => 'datetime',
    ];
    protected $fillable = ['user_id', 'text', 'expiration_date', 'privacy'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function media(): MorphOne
    {
        return $this->morphOne(Media::class, 'mediable');
    }

    public function likes(): MorphMany
    {
        return $this->morphMany(Like::class, 'likeable');
    }

    public function isLikedBy($userId)
    {
        return $this->likes()->where('user_id', $userId)->exists();
    }

    public function highlights()
    {
        return $this->belongsToMany(Highlight::class, 'status_highlight')
                    ->withPivot('added_at')
                    ->withTimestamps();
    }
}
