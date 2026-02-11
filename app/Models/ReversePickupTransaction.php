<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReversePickupTransaction extends Model
{
    protected $fillable = [
        'reverse_shipment_id',
        'user_id',
        'user_type',
        'transaction_type',
        'amount',
        'status',
        'executed_at',
        'reference_type',
        'reference_id',
        'note',
    ];

    protected $casts = [
        'amount' => 'decimal:3',
        'executed_at' => 'datetime',
    ];

    /**
     * Relationships
     */
    public function reverseShipment()
    {
        return $this->belongsTo(ReverseShipment::class, 'reverse_shipment_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function reference()
    {
        return $this->morphTo();
    }

    /**
     * Scopes
     */
    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByType($query, $transactionType)
    {
        return $query->where('transaction_type', $transactionType);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }
}
