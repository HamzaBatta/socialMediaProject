<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class Highlight extends Model
{

    protected $fillable = ['user_id','text'];

    public function user():BelongsTo{
        return $this->belongsTo(User::class);
    }

    public function statuses() {
        return $this->belongsToMany(Status::class,'status_highlights')
                    ->withPivot('added_at')
                    ->withTimestamps();
    }

    public function media(): MorphOne
    {
        return $this->morphOne(Media::class, 'mediable');
    }


}
