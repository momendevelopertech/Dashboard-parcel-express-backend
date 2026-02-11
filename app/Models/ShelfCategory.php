<?php

namespace App\Models;

use App\Models\Scopes\ShelfCategoryScope;
use App\Observers\ShelfCategoryObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Model;

#[ObservedBy(ShelfCategoryObserver::class)]

class ShelfCategory extends Model
{
    protected $fillable = [
        "owner_type",
        "owner_id",
        'name',
        'barcode'
    ];

    protected static function booted()
    {
        static::addGlobalScope(new ShelfCategoryScope());
    }

    public function owner()
    {
        return $this->morphTo();
    }

    public function scopeByOwner($query)
    {
        $facility = facility();

        if ($facility) {
            $query->where('owner_type', $facility->type)
                ->where('owner_id', $facility->id);
        }

        return $query;
    }

    /**
     * Get all of the shelves for the ShelfCategory
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */

    public function shelves()
    {
        return $this->hasMany(Shelf::class, 'category_id');
    }
}
