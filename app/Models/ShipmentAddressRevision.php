<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShipmentAddressRevision extends Model
{
    protected $fillable = [
        'shipment_id',
        'old_address_id',
        'new_address_id',
        'changed_by',
        'reason',
        'proof',
        'approved',
        'rejected',
        'approved_at',
        'approved_by',
    ];

    protected $casts = [
        'approved' => 'bool',
        'rejected' => 'bool',
        'approved_at' => 'datetime',
    ];

    public function shipment()
    {
        return $this->belongsTo(Shipment::class);
    }
    public function oldAddress()
    {
        return $this->belongsTo(Address::class, 'old_address_id');
    }
    public function newAddress()
    {
        return $this->belongsTo(Address::class, 'new_address_id');
    }
    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
    public function changer()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
