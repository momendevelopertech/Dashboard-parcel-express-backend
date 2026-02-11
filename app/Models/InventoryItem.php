<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

class InventoryItem extends Model
{
    protected $fillable = [
        'item_name',
        'category',
        'current_stock',
        'minimum_stock',
    ];

    protected $casts = [
        'current_stock' => 'integer',
        'minimum_stock' => 'integer',
    ];

    /**
     * Computed status attribute.
     */
    protected function status(): Attribute
    {
        return Attribute::get(function () {
            if ($this->current_stock <= 0) {
                return 'out_of_stock';
            }
            if ($this->current_stock < $this->minimum_stock) {
                return 'low_stock';
            }
            return 'in_stock';
        });
    }

    /**
     * Get all transactions for this inventory item.
     */
    public function transactions()
    {
        return $this->hasMany(StockTransaction::class);
    }
}
