<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FinancialRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'owner_id',
        'owner_type',
        'type',
        'payee_id',
        'period_date',
        'amount',
        'notes',
        'status',
        'created_by',
        'reviewed_by',
        'reviewed_at',
        'financial_proof'
    ];

    protected $casts = [
        'period_date' => 'date',
        'amount' => 'decimal:2',
    ];


    public function owner()
    {
        return $this->morphTo();
    }
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    public function getPayeeAttribute()
    {
        if ($this->type === 'driver_salary' && $this->payee_id) {
            $payeeIds = explode(',', $this->payee_id);
            return User::whereIn('id', $payeeIds)->get();
        }
        return $this->payee_id ? User::where('id', $this->payee_id)->first() : null;
    }
    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    // public function scopeByOwner($q)
    // {
    //     return $q->where('owner_id', facility('id'))
    //         ->where('owner_type', facility('type'));
    // }

    /**
     * توليد الرقم المرجعي بالشكل REQ-YYYY-### لكل سنة.
     * (حل بسيط؛ لو عايز أضبطها ضد الـ race conditions نضيف عمود sequence ونقفل بـ lockForUpdate)
     */
    public static function nextCode(): string
    {
        $year = now()->year;

        // حاول اقرأ آخر كود لنفس السنة واستنتج الرقم
        $lastCode = static::whereYear('created_at', $year)
            ->orderByDesc('id')
            ->value('code');

        if ($lastCode && preg_match('/REQ-' . $year . '-(\d{3})$/', $lastCode, $m)) {
            $seq = (int) $m[1] + 1;
        } else {
            // بديل آمن وبسيط
            $seq = static::whereYear('created_at', $year)->count() + 1;
        }

        return sprintf('REQ-%d-%03d', $year, $seq);
    }

    protected static function booted()
    {
        static::creating(function (self $model) {
            if (empty($model->code)) {
                $model->code = static::nextCode();
            }
        });
    }
}
