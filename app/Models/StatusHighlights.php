<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StatusHighlights extends Model
{
    protected $table = 'status_highlights';
    protected $fillable=['status_id','highlight_id','added_at'];
}
