<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PartnerWebhook extends Model
{
    protected $fillable = ['partner_id', 'url', 'secret_hash', 'events', 'is_active', 'last_success_at', 'failure_count'];
    protected $casts = ['events' => 'array', 'is_active' => 'boolean', 'last_success_at' => 'datetime'];
    public function partner()
    {
        return $this->belongsTo(Partner::class);
    }
    public function deliveries()
    {
        return $this->hasMany(WebhookDelivery::class, 'webhook_id');
    }
}
