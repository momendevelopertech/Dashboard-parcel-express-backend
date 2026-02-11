<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WarehouseTransaction extends Model
{
    public $timestamps = false;
    protected $fillable = ['warehouse_type', 'warehouse_id', 'type', 'amount', 'reference', 'description', 'created_by', 'created_at','source'];
    public function warehouse()
    {
        return $this->morphTo();
    }
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

}
