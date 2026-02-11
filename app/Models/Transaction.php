<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Transaction extends Model
{
    protected $fillable = [
        "from_id",
        "from_type",
        "to_id",
        "to_type",
        "shipment_id",
        "amount",
        'reference',
        'description',
        'created_by',
        "type",
        'receipt_path',
        'receipt_uploaded_by',
        'receipt_uploaded_at',
        'warehouse_id',
        'payout_id',
        'settled_at',
        'isPaid',
        'advance_id',
    ];
    protected $casts = [
        'receipt_uploaded_at' => 'datetime',
        'settled_at' => 'datetime',   // جديد
        'amount' => 'decimal:2',
    ];
    public function from()
    {
        // لو الأعمدة اسمها from_type/from_id فده يضمن التعيين الصحيح دائمًا
        return $this->morphTo(__FUNCTION__, 'from_type', 'from_id');
    }

    public function to()
    {
        return $this->morphTo(__FUNCTION__, 'to_type', 'to_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    public function shipment()
    {
        return $this->belongsTo(Shipment::class, 'shipment_id');
    }
    protected $appends = ['receipt_url'];

    public function getReceiptUrlAttribute(): ?string
    {
        return $this->receipt_path ? Storage::disk('public')->url($this->receipt_path) : null;
    }
    public function advance()
    {
        return $this->belongsTo(Advance::class, 'advance_id');
    }

}
