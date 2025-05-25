<?php

namespace App\Models;

use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class User extends Authenticatable implements JWTSubject,MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable,CanResetPassword;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'google_id',
        'bio',
        'is_private'
    ];
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [
            'name' => $this->name,
            'email' => $this->email,
            'avatar' => $this->media ? url("storage/{$this->media->path}") : null,
        ];
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function posts()
    {
        return $this->hasMany(Post::class);
    }

    public function likes()
    {
        return $this->hasMany(Like::class);
    }

    public function comments()
    {
        return $this->hasMany(Comment::class);
    }

    public function media(): MorphOne
    {
        return $this->morphOne(Media::class, 'mediable'); // one-to-one polymorphic :contentReference[oaicite:8]{index=8}
    }

    public function savedPost(){
        return $this->hasOne(SavedPost::class);
    }

    public function groups()
    {
        return $this->belongsToMany(Group::class, 'members')
                    ->withPivot('role')
                    ->withTimestamps();
    }
    /**
     * createing the follow relationship between users
     * and there are many functions :
     * 1- the following to know what users we are following
     * 2- the followers to know what users are following us
     * 3- is following (USER) to check if we follow this user (will help us in the privacy policies)
     * 4- is followed by (USER) to ckeck if this user follow us (will help us in the privacy policies)
     */

    // Users that this user is following
    public function following(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'user_follows',
            'follower_id',
            'followee_id'
            )->withTimestamps();
    }

        // Users that are following this user
    public function followers(): BelongsToMany
        {
            return $this->belongsToMany(
                User::class,
                'user_follows',
                'followee_id',
                'follower_id'
                )->withTimestamps();
        }

            // Check if the user is following another user
        public function isFollowing(User $user): bool
        {
            return $this->following()->where('followee_id', $user->id)->exists();
        }

            // Check if the user is followed by another user
        public function isFollowedBy(User $user): bool
        {
            return $this->followers()->where('follower_id', $user->id)->exists();
        }
        /**
         * createing the block relationship between users
         * and there are many functions :
         * 1- the blocking to know what users we are blocking
         * 2- the blockers to know what users are blocking us
         * 3- is blocking (USER) to check if we block this user (will help us in the privacy policies)
         * 4- is blocked by (USER) to ckeck if this user block us (will help us in the privacy policies)
         */
         // Users that this user has blocked
    public function blockedUsers(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'user_blocks',
            'blocker_id',
            'blocked_id'
        )->withTimestamps();
    }

    // Users who have blocked this user
    public function blockedByUsers(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'user_blocks',
            'blocked_id',
            'blocker_id'
        )->withTimestamps();
    }

    // Check if the user has blocked another user
    public function hasBlocked(User $user): bool
    {
        return $this->blockedUsers()->where('blocked_id', $user->id)->exists();
    }

    // Check if the user is blocked by another user
    public function isBlockedBy(User $user): bool
    {
        return $this->blockedByUsers()->where('blocker_id', $user->id)->exists();
    }



/**
     * All follow‐requests made *to* this user.
     */
    public function requests()
    {
        return $this->morphMany(Request::class, 'requestable');
    }
}
