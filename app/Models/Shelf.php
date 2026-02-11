<?php

namespace App\Models;

use App\Observers\ShelfObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Auth;

#[ObservedBy(ShelfObserver::class)]
class Shelf extends Model
{
    use HasFactory;

    // protected static function booted()
    // {
    //     static::addGlobalScope(new ShelfScope());
    // }

    protected $fillable = [
        'area',
        'shelf_number',
        'layer_number',
        'partition_number',
        'barcode',
        'created_by',
        'location',
        'category_id',
        'owner_id',
        'owner_type',
    ];



    public function hub()
    {
        return $this->belongsTo(Hub::class)->withDefault();
    }

    public function station()
    {
        return $this->belongsTo(Station::class)->withDefault();
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class)->withDefault();
    }

    public function items()
    {
        return $this->hasMany(ShelfItem::class);
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

    public function scopeSearchByBarcode($query, $search)
    {
        return $query->when($search, function ($q) use ($search) {
            $q->where('barcode', $search);
        });
    }

    public function shipments_assigned()
    {
        return $this->hasMany(AssignShipmentToShelf::class, 'barcode');
    }


    public function category()
    {
        return $this->belongsTo(ShelfCategory::class, 'category_id');
    }



    public function shipments()
    {
        return $this->hasManyThrough(Shipment::class, AssignShipmentToShelf::class, 'barcode', 'tracking_no');
    }
}
