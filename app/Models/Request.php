<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Request extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'requestable_id',
        'requestable_type',
        'state',
        'requested_at',
        'responded_at',
    ];

    /**
     * The user who created this request.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * The target model (e.g. a User) being requested.
     */
    public function requestable()
    {
        return $this->morphTo();
    }

    public static function isRequested($requests,$user_id,$requestable_type,$requestable_id){
        if($requests
            ->where('user_id',$user_id)
            ->where('requestable_type',$requestable_type)
            ->where('requestable_id',$requestable_id)
            ->where('state','pending')
            ->exists()){
            return true;
        }else{
            return false;
        }
    }

}
