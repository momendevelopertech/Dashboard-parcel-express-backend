<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LowStockAlert extends Model
{
    protected $fillable = [
        'inventory_item_id',
        'minimum_stock_level',
        'notification_methods',
        'status',
        'alert_date',
    ];

    protected $casts = [
        'notification_methods' => 'array',
        'status' => 'string',
        'alert_date' => 'datetime',
    ];

    /**
     * Related inventory item.
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }
}
