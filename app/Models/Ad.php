<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ad extends Model
{
    protected $table = 'ads'; // must match the ads service table name
    public $timestamps = false; // if ads table doesn't have timestamps
}
