<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Carbon\Carbon;

class LegalDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_id',
        'document_name',
        'type',
        'expiry_date',
        'uploaded_by',
        'file_path',
    ];

    protected $casts = [
        'expiry_date' => 'date',
    ];

    protected $appends = [
        'status'
    ];

    protected function status(): Attribute
    {
        return Attribute::get(function () {
            if (!$this->expiry_date) {
                return 'unknown';
            }
            
            $today = Carbon::today();
            $expiryDate = Carbon::parse($this->expiry_date);
            
            if ($expiryDate->lt($today)) {
                return 'expired';
            } elseif ($expiryDate->diffInDays($today) <= 30) {
                return 'expiring_soon';
            } else {
                return 'valid';
            }
        });
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function isExpired(): bool
    {
        return $this->status === 'expired';
    }

    public function isExpiringSoon(): bool
    {
        return $this->status === 'expiring_soon';
    }

    public function isValid(): bool
    {
        return $this->status === 'valid';
    }

    public function daysUntilExpiry(): int
    {
        if (!$this->expiry_date) {
            return 0;
        }
        
        return Carbon::today()->diffInDays(Carbon::parse($this->expiry_date), false);
    }

    public function getFileUrlAttribute(): string
    {
        return asset('storage/' . $this->file_path);
    }

    public function scopeExpired($query)
    {
        return $query->whereDate('expiry_date', '<', Carbon::today());
    }

    public function scopeExpiringSoon($query)
    {
        return $query->whereDate('expiry_date', '>=', Carbon::today())
                     ->whereDate('expiry_date', '<=', Carbon::today()->addDays(30));
    }

    public function scopeValid($query)
    {
        return $query->whereDate('expiry_date', '>', Carbon::today()->addDays(30));
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }
}
