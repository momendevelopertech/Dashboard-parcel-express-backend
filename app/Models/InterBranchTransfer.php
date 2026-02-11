<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InterBranchTransfer extends Model
{
    protected $fillable = [
        'code',
        'from_type',
        'from_id',
        'to_type',
        'to_id',
        'amount',
        'shipments_count',
        'status',
        'notes',
        'created_by',
        'approved_by',
        'approved_at',
        'rejected_by',
        'rejected_at',
        'reject_comment',
        'receipt_path',
    ];
    protected $casts = [
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'amount' => 'decimal:2',
    ];
    public function from()
    {
        return $this->morphTo();
    }
    public function to()
    {
        return $this->morphTo();
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function shipments()
    {
        return $this->hasMany(InterBranchTransferShipment::class, 'transfer_id');
    }

}
