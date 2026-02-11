<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Advance extends Model
{
     use HasFactory;

    protected $fillable = [
        'driver_id',
        'warehouse_id',
        'warehouse_type',
        'amount',
        'voucher_no',
        'reference',
        'notes',
        'created_by',
        'image',
    ];

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function warehouse()
    {
        return $this->morphTo(__FUNCTION__, 'warehouse_type', 'warehouse_id');
    }
    public function transaction()
    {
        return $this->hasOne(Transaction::class, 'advance_id');
    }
}
