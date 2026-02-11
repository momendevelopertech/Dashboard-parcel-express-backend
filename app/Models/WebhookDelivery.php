<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebhookDelivery extends Model
{
    protected $fillable = ['webhook_id', 'event_type', 'delivery_id', 'payload', 'status', 'attempts', 'next_retry_at', 'delivered_at', 'last_error'];
    protected $casts = ['payload' => 'array', 'next_retry_at' => 'datetime', 'delivered_at' => 'datetime'];
    public function webhook()
    {
        return $this->belongsTo(PartnerWebhook::class, 'webhook_id');
    }
}
