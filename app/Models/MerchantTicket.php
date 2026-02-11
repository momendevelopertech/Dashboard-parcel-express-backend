<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MerchantTicket extends Model
{
    use HasFactory;

    protected $fillable = [
        'merchant_id',
        'subject',
        'status',
        'initial_message',
    ];

    public function messages()
    {
        return $this->hasMany(MerchantTicketMessage::class);
    }

    public function merchant()
    {
        return $this->belongsTo(User::class, 'merchant_id');
    }
}
