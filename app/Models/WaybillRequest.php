<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WaybillRequest extends Model
{
    protected $fillable = [
        'merchant_user_id',
        'shipments_count',
        'scheduled_at',
        'status'
    ];

    public function merchant()
    {
        return $this->belongsTo(User::class, 'merchant_user_id');
    }
}
