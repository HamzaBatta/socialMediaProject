<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Post extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'text', 'privacy', 'group_id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function group()
    {
        return $this->belongsTo(Group::class);
    }

   /**
     * Get all media items for the post.
     */
    public function media(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable'); // one-to-many polymorphic :contentReference[oaicite:6]{index=6}
    }

    public function likes(): MorphMany
    {
        return $this->morphMany(Like::class, 'likeable');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class)->whereNull('reply_comment_id');
    }

    public function isLikedBy($userId)
    {
        return $this->likes()->where('user_id', $userId)->exists();
    }

    public function savedPosts(){
        return $this->belongsToMany(SavedPost::class,'post_saved_posts');
    }


}
