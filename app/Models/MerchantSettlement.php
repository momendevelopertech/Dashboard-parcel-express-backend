<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class MerchantSettlement extends Model
{
    protected $fillable = ['merchant_id', 'reference', 'amount', 'status', 'notes', 'created_by', 'receipt_path', 'receipt_uploaded_by', 'receipt_uploaded_at'];

    public function merchant()
    {
        return $this->belongsTo(User::class, 'merchant_id');
    }
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    protected $appends = ['receipt_url'];

    // public function getReceiptUrlAttribute(): ?string
    // {
    //     return $this->receipt_path ? url($this->receipt_path) : null;
    // }
}
