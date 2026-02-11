<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class ShipmentProof extends Model
{
    protected $fillable = [
        'shipment_id',
        'type',
        'path',
        'uploaded_by',
    ];

     public function shipment()
    {
        return $this->belongsTo(Shipment::class);
    }

    public function getUrlAttribute(): string
    {
        return Storage::disk(config('filesystems.default'))->url($this->path);
    }
}
