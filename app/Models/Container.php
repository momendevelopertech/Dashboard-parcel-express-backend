<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Container extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code',
        'container_number',
        'pre_id',
        'tracking_no',
        'container_type', // BAG, PALLET, BOX
        'max_weight',
        'current_weight',
        'max_volume',
        'current_volume',
        'shipment_count',
        'status', // OPEN, SEALED, IN_TRANSFER, ARRIVED, INBOUNDED, UNLOADED, CLOSED, CANCELLED
        'facility_type',
        'facility_id',
        'owner_type',
        'owner_id',
        'from_hub_id',
        'current_hub_id',
        'target_hub_id',
        'final_hub_id',
        'sealed_at',
        'sealed_by',
        'unloaded_at',
        'unloaded_by',
        'closed_at',
        'seal_no',
        'created_by',
        'notes',
    ];

    protected $casts = [
        'max_weight' => 'decimal:2',
        'current_weight' => 'decimal:2',
        'max_volume' => 'decimal:2',
        'current_volume' => 'decimal:2',
        'shipment_count' => 'integer',
        'sealed_at' => 'datetime',
        'unloaded_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    // Status Constants
    const STATUS_OPEN = 'OPEN';
    const STATUS_SEALED = 'SEALED';
    const STATUS_IN_TRANSFER = 'IN_TRANSFER';
    const STATUS_ARRIVED = 'ARRIVED'; // or INBOUNDED
    const STATUS_INBOUNDED = 'INBOUNDED';
    const STATUS_UNLOADED = 'UNLOADED';
    const STATUS_CLOSED = 'CLOSED';
    const STATUS_CANCELLED = 'CANCELLED';

    /**
     * Get all shipments currently considered "inside" this container (via state pointer).
     * This is the "fast state" query.
     */
    public function currentShipments(): HasMany
    {
        return $this->hasMany(Shipment::class, 'current_container_id');
    }

    /**
     * Get all shipments history for this container.
     */
    public function containerShipments(): HasMany
    {
        return $this->hasMany(ContainerShipment::class);
    }

    /**
     * Get shipment history records (membership).
     */
    public function shipmentsHistory()
    {
         return $this->belongsToMany(Shipment::class, 'container_shipments')
                    ->withPivot(['added_at', 'removed_at', 'notes'])
                    ->using(ContainerShipment::class);
    }
    
    /**
     * Get the facility this container belongs to (polymorphic)
     * Legacy/Existing logic
     */
    public function facility(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Polymorphic owner (Shipment-like ownership)
     */
    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    public function fromHub(): BelongsTo
    {
        return $this->belongsTo(Hub::class, 'from_hub_id');
    }

    public function currentHub(): BelongsTo
    {
        return $this->belongsTo(Hub::class, 'current_hub_id');
    }

    public function targetHub(): BelongsTo
    {
        return $this->belongsTo(Hub::class, 'target_hub_id');
    }

    public function finalHub(): BelongsTo
    {
        return $this->belongsTo(Hub::class, 'final_hub_id');
    }

    /**
     * Get the user who created this container
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function sealer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sealed_by');
    }

    public function unloader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'unloaded_by');
    }

    /**
     * Check if container can accept more weight
     */
    public function canAcceptWeight(float $weight): bool
    {
        if (!$this->max_weight) {
            return true;
        }
        return ($this->current_weight + $weight) <= $this->max_weight;
    }

    /**
     * Check if container is full
     */
    public function isFull(): bool
    {
        if ($this->max_weight && $this->current_weight >= $this->max_weight) {
            return true;
        }
        if ($this->max_volume && $this->current_volume >= $this->max_volume) {
            return true;
        }
        return false;
    }

    /**
     * Get remaining capacity weight
     */
    public function getRemainingWeight(): ?float
    {
        if (!$this->max_weight) {
            return null;
        }
        return max(0, $this->max_weight - $this->current_weight);
    }

    /**
     * Get remaining capacity volume
     */
    public function getRemainingVolume(): ?float
    {
        if (!$this->max_volume) {
            return null;
        }
        return max(0, $this->max_volume - $this->current_volume);
    }

    /**
     * Get container utilization percentage
     */
    public function getUtilizationPercentage(): ?float
    {
        if ($this->max_weight) {
            return ($this->current_weight / $this->max_weight) * 100;
        }
        if ($this->max_volume) {
            return ($this->current_volume / $this->max_volume) * 100;
        }
        return null;
    }
    /**
     * Check if container is open for adding shipments
     */
    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    /**
     * Check if container is sealed
     */
    public function isSealed(): bool
    {
        return $this->status === self::STATUS_SEALED;
    }

    /**
     * Check if container allows adding shipments (Must be OPEN)
     */
    public function canAddShipment(): bool
    {
        return $this->isOpen();
    }

    /**
     * Check if container allows removing shipments (Must be OPEN)
     */
    public function canRemoveShipment(): bool
    {
        return $this->isOpen();
    }
}
