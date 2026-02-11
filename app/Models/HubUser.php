<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HubUser extends Model
{
    protected $fillable = [
        "user_id",
        "hub_id",
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function hub()
    {
        return $this->belongsTo(Hub::class, 'hub_id');
    }
}
