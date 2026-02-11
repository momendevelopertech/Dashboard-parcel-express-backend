<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class MerchantTransaction extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'merchant_id',
        'type',
        'reference',
        'transactionable_type',
        'transactionable_id',
        'shipment_id',
        'pickup_task_id',
        'country_id',
        'state_id',
        'amount',
        'base_amount',
        'discount_amount',
        'currency',
        'fee_payer',
        'description',
        'metadata',
        'paid_by_cash',
        'paid_by_bank',
        'received_by',
        'paid_at',
        'receipt_path',
        'status',
        'completed_at',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:3',
        'base_amount' => 'decimal:3',
        'discount_amount' => 'decimal:3',
        'paid_by_cash' => 'decimal:3',
        'paid_by_bank' => 'decimal:3',
        'metadata' => 'array',
        'paid_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * Transaction type constants
     */
    const TYPE_DELIVERY_FEE = 'delivery_fee';
    const TYPE_REVERSE_DELIVERY_FEE = 'reverse_delivery_fee';
    const TYPE_RETURN_FEE = 'return_fee';
    const TYPE_PICKUP_FEE = 'pickup_fee';
    const TYPE_DELIVERY_DISCOUNT = 'delivery_discount';
    //discount given to merchant for delivery consignee fees
    const TYPE_DELIVERY_REBATE_TO_MERCHANT = 'delivery_rebate_to_merchant';
    const TYPE_RETURN_DISCOUNT = 'return_discount';
    const TYPE_PICKUP_DISCOUNT = 'pickup_discount';
    const TYPE_COD_COLLECTED = 'cod_collected';
    const TYPE_SETTLEMENT = 'settlement';
    const TYPE_FINE = 'fine';
    const TYPE_ADJUSTMENT = 'adjustment';
    const TYPE_PICKUP_DEPOSIT = 'pickup_deposit';
    const TYPE_REGISTRATION = 'registration';

    /**
     * Status constants
     */
    const STATUS_PENDING = 'pending';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_REFUNDED = 'refunded';
    
    // ========== RELATIONSHIPS ==========

    /**
     * Get the merchant that owns the transaction
     */
    public function merchant()
    {
        return $this->belongsTo(User::class, 'merchant_id');
    }

    /**
     * Get the related entity (polymorphic)
     */
    public function transactionable()
    {
        return $this->morphTo();
    }

    /**
     * Get the related shipment
     */
    public function shipment()
    {
        return $this->belongsTo(Shipment::class, 'shipment_id');
    }

    /**
     * Get the related pickup task
     */
    public function pickupTask()
    {
        return $this->belongsTo(MerchantPickupTask::class, 'pickup_task_id');
    }

    /**
     * Get the country
     */
    public function country()
    {
        return $this->belongsTo(Country::class, 'country_id');
    }

    /**
     * Get the state
     */
    public function state()
    {
        return $this->belongsTo(State::class, 'state_id');
    }

    /**
     * Get who received the payment
     */
    public function receiver()
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    /**
     * Get who created the transaction
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    
    // ========== SCOPES ==========

    /**
     * Scope for specific merchant
     */
    public function scopeForMerchant($query, int $merchantId)
    {
        return $query->where('merchant_id', $merchantId);
    }

    /**
     * Scope for specific transaction type
     */
    public function scopeType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope for multiple transaction types
     */
    public function scopeTypes($query, array $types)
    {
        return $query->whereIn('type', $types);
    }

    /**
     * Scope for completed transactions
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    /**
     * Scope for pending transactions
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope for date range
     */
    public function scopeDateRange($query, $from, $to)
    {
        return $query->whereBetween('created_at', [$from, $to]);
    }

    /**
     * Scope for fees only
     */
    public function scopeFees($query)
    {
        return $query->whereIn('type', [
            self::TYPE_DELIVERY_FEE,
            self::TYPE_RETURN_FEE,
            self::TYPE_PICKUP_FEE,
        ]);
    }

    /**
     * Scope for discounts only
     */
    public function scopeDiscounts($query)
    {
        return $query->whereIn('type', [
            self::TYPE_DELIVERY_DISCOUNT,
            self::TYPE_RETURN_DISCOUNT,
            self::TYPE_PICKUP_DISCOUNT,
        ]);
    }

    /**
     * Scope for credits (positive amounts)
     */
    public function scopeCredits($query)
    {
        return $query->where('amount', '>', 0);
    }

    /**
     * Scope for debits (negative amounts)
     */
    public function scopeDebits($query)
    {
        return $query->where('amount', '<', 0);
    }
    
    // ========== ACCESSORS & MUTATORS ==========

    /**
     * Get if transaction is a credit
     */
    public function getIsCreditAttribute(): bool
    {
        return $this->amount > 0;
    }

    /**
     * Get if transaction is a debit
     */
    public function getIsDebitAttribute(): bool
    {
        return $this->amount < 0;
    }

    /**
     * Get the absolute amount
     */
    public function getAbsoluteAmountAttribute(): float
    {
        return abs((float) $this->amount);
    }
}
