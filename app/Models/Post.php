<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    use HasFactory;

    protected $fillable =['user_id','text'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

   /**
     * Get all media items for the post.
     */
    public function media(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable'); // one-to-many polymorphic :contentReference[oaicite:6]{index=6}
    }

    public function likes()
    {
        return $this->hasMany(Like::class);
    }

    public function comments()
    {
        return $this->hasMany(Comment::class)->whereNull('reply_comment_id');
    }

    public function isLikedBy($userId)
    {
        return $this->likes()->where('user_id', $userId)->exists();
    }
}
