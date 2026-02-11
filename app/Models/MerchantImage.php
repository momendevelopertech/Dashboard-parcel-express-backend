<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MerchantImage extends Model
{
    protected $fillable=['merchant_id','image_path'];

    public function merchant()
    {
        return $this->belongsTo(User::class, 'merchant_id');
    }
        
}
