<?php

namespace App\Models;

use App\Models\Shipment;
use App\Models\PartnerKey;
use App\Models\PartnerWebhook;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Partner extends Model
{
    protected $fillable = ['name', 'contact_email', 'allowed_scopes', 'is_active', 'rate_limit'];
    protected $casts = [
        'allowed_scopes' => 'array',
        'rate_limit' => 'array',
        'is_active' => 'boolean',
    ];
    public function keys(): HasMany
    {
        return $this->hasMany(PartnerKey::class);
    }

    public function webhooks(): HasMany
    {
        return $this->hasMany(PartnerWebhook::class);
    }

    public function marketplaceShipments(): HasMany
    {
        return $this->hasMany(Shipment::class, 'marketplace_partner_id');
    }
}
