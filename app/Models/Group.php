<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Group extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'privacy', 'owner_id','bio'];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function media(): MorphOne
    {
        return $this->morphOne(Media::class, 'mediable');
    }

    public function posts()
    {
        return $this->hasMany(Post::class);
    }

    public function members()
    {
        return $this->belongsToMany(User::class, 'members')
                    ->withPivot('role')
                    ->withTimestamps();
    }
    public function requests(): MorphMany
    {
        return $this->morphMany(Request::class, 'requestable');
    }

    public function isMember($user_id){
        if($this->owner_id == $user_id){
            return true;
        }
        else if($this->members()->where('user_id',$user_id)->exists()){
            return true;
        }else{
            return false;
        }
    }

    public function joinStatus($isMember,$isRequested){
        if($isMember){
            return "joined";
        }else if($isRequested){
            return "pending";
        }else{
            return "not_joined";
        }
    }
}
