<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Request extends Model
{
    use HasFactory;

    protected $fillable = ['state', 'requested_at', 'responded_at'];

    public function requestable(): MorphTo
    {
        return $this->morphTo();
    }

}
