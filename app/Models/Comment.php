<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Comment extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'commentable_type', 'commentable_id', 'text'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function commentable()
    {
        return $this->morphTo();
    }

    public function parent()
    {
        return $this->belongsTo(Comment::class, 'reply_comment_id');
    }

    public function replies()
    {
        return $this->morphMany(Comment::class, 'commentable')
                    ->where('commentable_type', 'Comment');
    }

    public function likes(): MorphMany
    {
        return $this->morphMany(Like::class, 'likeable');
    }

    public function isLikedBy($userId)
    {
        return $this->likes()->where('user_id', $userId)->exists();
    }

    public function getRootPostId()
    {
        $root = $this->commentable;

        while ($root instanceof self) {
            $root = $root->commentable;
        }

        return $root instanceof \App\Models\Post ? $root->id : null;
    }
}
