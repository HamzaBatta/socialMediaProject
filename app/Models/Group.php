<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Group extends Model
{
    /** @use HasFactory<\Database\Factories\GroupFactory> */
    use HasFactory;

    public function media(): MorphOne
    {
        return $this->morphOne(Media::class, 'mediable'); // one-to-one polymorphic :contentReference[oaicite:8]{index=8}
    }
}
