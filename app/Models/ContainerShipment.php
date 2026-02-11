<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContainerShipment extends Model
{
    use HasFactory;

    protected $fillable = [
        'container_id',
        'shipment_id',
        'added_at',
        'added_by',
        'removed_at',
        'removed_by',
        'unloaded_at',
        'unloaded_by',
        'notes',
    ];

    protected $casts = [
        'added_at' => 'datetime',
        'removed_at' => 'datetime',
        'unloaded_at' => 'datetime',
    ];

    public function container(): BelongsTo
    {
        return $this->belongsTo(Container::class);
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function adder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }

    public function remover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'removed_by');
    }

    public function unloader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'unloaded_by');
    }
}
