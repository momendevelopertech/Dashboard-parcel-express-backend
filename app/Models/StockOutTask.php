<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockOutTask extends Model
{
    use HasFactory;

    protected $fillable = [
        'owner_type',
        'owner_id',
        'created_by',
        'status',
        'notes'
    ];

    public function owner()
    {
        return $this->morphTo();
    }

    public function created_by()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function shipments()
    {
        return $this->hasMany(StockOutTaskShipment::class, 'stock_out_task_id');
    }
}
