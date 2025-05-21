<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PostSavedPosts extends Model
{
    use HasFactory;
    protected $table = 'post_saved_posts';
    protected $fillable=['saved_post_id','post_id',];

}
