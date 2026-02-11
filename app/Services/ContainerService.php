<?php

namespace App\Services;

use App\Models\Container;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class ContainerService
{
    /**
     * Create a new container
     */
    public function createContainer(array $data): Container
    {
        // 1. Generate Identity
        $data['code'] = $data['code'] ?? $this->generateContainerCode();
        // Maintain backwards compatibility if needed, or map code to container_number
        $data['container_number'] = $data['code']; 
        $data['tracking_no'] = $data['tracking_no'] ?? $this->generateTrackingNo();

        // 2. Set Defaults
        $data['status'] = Container::STATUS_OPEN;
        
        // 3. Auth context (should be passed in $data or handled by controller, ensuring here)
        if (!isset($data['created_by']) && auth()->check()) {
            $data['created_by'] = auth()->id();
        }

        return Container::create($data);
    }

    public function generateContainerCode(): string
    {
        do {
            $code = 'CNT-' . strtoupper(Str::random(10));
        } while (Container::where('code', $code)->orWhere('container_number', $code)->exists());

        return $code;
    }

    /**
     * Generate a unique container number
     */
    public function generateContainerNumber(): string
    {
        do {
            $number = 'CNT-' . strtoupper(Str::random(8));
        } while (Container::where('container_number', $number)->exists());

        return $number;
    }

    /**
     * Generate a unique tracking number for container
     */
    public function generateTrackingNo(): string
    {
        do {
            $number = 'CTR-' . strtoupper(Str::random(12));
        } while (Container::where('tracking_no', $number)->exists());

        return $number;
    }

    /**
     * Add a shipment to a container
     */
    public function addShipment(Container $container, Shipment $shipment, ?User $user = null): bool
    {
        return DB::transaction(function () use ($container, $shipment, $user) {
            // 1. Checks
            if (!$container->canAddShipment()) {
                throw new \Exception("Container is not OPEN for shipments (Status: {$container->status}).");
            }

            // Enforce "One Active Container" rule
            if ($shipment->current_container_id) {
                if ($shipment->current_container_id === $container->id) {
                    return true; // Already in this container, no-op or idempotent success
                }
                throw new \Exception("Shipment is already active in another container (ID: {$shipment->current_container_id}). Remove it first.");
            }

            $shipmentValue = $shipment->value ?? 0;
            if (!$container->canAcceptWeight($shipmentValue)) {
                throw new \Exception("Container has reached maximum weight capacity.");
            }

            // 2. Create History Record (ContainerShipment)
            \App\Models\ContainerShipment::create([
                'container_id' => $container->id,
                'shipment_id' => $shipment->id,
                'added_at' => now(),
                'added_by' => $user?->id ?? auth()->id(),
            ]);

            // 3. Update State Pointer (Shipment)
            $shipment->update([
                'current_container_id' => $container->id,
                // Legacy support (optional, if you want to keep using container_id for something else or sync it)
                 'container_id' => $container->id, 
            ]);

            // 4. Update Container Stats
            $container->increment('shipment_count');
            $container->increment('current_weight', $shipmentValue);

            // 5. Check Fullness
            if ($container->isFull()) {
                // Optional: Auto-seal or just mark full? 
                // Requirement said "Status" usually manual, but "FULL" is status. 
                // Keeping logic simple: status stays OPEN but maybe UI warns. 
                // Or update status if 'FULL' is a desired valid status.
                // $container->update(['status' => 'FULL']); 
            }

            return true;
        });
    }

    /**
     * Remove a shipment from a container
     */
    public function removeShipment(Container $container, Shipment $shipment, ?User $user = null): bool
    {
        return DB::transaction(function () use ($container, $shipment, $user) {
            // 1. Checks
            if ($shipment->current_container_id !== $container->id) {
                 // Relax check: If it's not here, maybe we just ensure it's removed? 
                 // But strictly, we should error to prevent confusion.
                 throw new \Exception("Shipment is not currently in this container.");
            }
            
            if (!$container->canRemoveShipment()) {
                 // Depending on operational rules, we might allow removing from SEALED if authorized.
                 // For now, adhering to strict rules or check permission.
                 // throw new \Exception("Container is locked/sealed.");
            }

            // 2. Close History Record
            $activeHistory = \App\Models\ContainerShipment::where('container_id', $container->id)
                ->where('shipment_id', $shipment->id)
                ->whereNull('removed_at')
                ->latest('added_at')
                ->first();

            if ($activeHistory) {
                $activeHistory->update([
                    'removed_at' => now(),
                    'removed_by' => $user?->id ?? auth()->id(),
                ]);
            }

            // 3. Update State Pointer
            $shipment->update([
                'current_container_id' => null,
                'container_id' => null, // Legacy sync
            ]);

            // 4. Update Container Stats
            $container->decrement('shipment_count');
            $shipmentValue = $shipment->value ?? 0;
            $container->decrement('current_weight', $shipmentValue);

            return true;
        });
    }

    /**
     * Add multiple shipments to a container
     */
    public function addMultipleShipments(Container $container, array $shipmentIds): array
    {
        $results = [
            'success' => [],
            'failed' => [],
        ];

        foreach ($shipmentIds as $shipmentId) {
            try {
                $shipment = Shipment::findOrFail($shipmentId);
                $this->addShipment($container, $shipment);
                $results['success'][] = $shipmentId;
            } catch (\Exception $e) {
                $results['failed'][] = [
                    'shipment_id' => $shipmentId,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * Remove multiple shipments from a container
     */
    public function removeMultipleShipments(Container $container, array $shipmentIds): array
    {
        $results = [
            'success' => [],
            'failed' => [],
        ];

        foreach ($shipmentIds as $shipmentId) {
            try {
                $shipment = Shipment::findOrFail($shipmentId);
                $this->removeShipment($container, $shipment);
                $results['success'][] = $shipmentId;
            } catch (\Exception $e) {
                $results['failed'][] = [
                    'shipment_id' => $shipmentId,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * Get all shipments in a container
     */
    public function getContainerShipments(Container $container): Collection
    {
        return $container->currentShipments()->get();
    }

    /**
     * Move shipments from one container to another
     */
    public function moveShipments(Container $fromContainer, Container $toContainer, array $shipmentIds): array
    {
        return DB::transaction(function () use ($fromContainer, $toContainer, $shipmentIds) {
            $results = [
                'success' => [],
                'failed' => [],
            ];

            foreach ($shipmentIds as $shipmentId) {
                try {
                    $shipment = Shipment::findOrFail($shipmentId);

                    if ($shipment->container_id !== $fromContainer->id) {
                        throw new \Exception("Shipment is not in source container.");
                    }

                    $this->removeShipment($fromContainer, $shipment);
                    $this->addShipment($toContainer, $shipment);

                    $results['success'][] = $shipmentId;
                } catch (\Exception $e) {
                    $results['failed'][] = [
                        'shipment_id' => $shipmentId,
                        'error' => $e->getMessage(),
                    ];
                }
            }

            return $results;
        });
    }

    /**
     * Empty a container (remove all shipments)
     */
    public function emptyContainer(Container $container): int
    {
        return DB::transaction(function () use ($container) {
            $shipmentCount = $container->currentShipments()->count();

            $container->currentShipments()->update(['container_id' => null]);

            $container->update([
                'shipment_count' => 0,
                'current_weight' => 0,
                'current_volume' => 0,
                'status' => 'ACTIVE',
            ]);

            return $shipmentCount;
        });
    }

    /**
     * Seal a container
     */
    public function seal(Container $container, ?User $user = null): Container
    {
        if (!$container->isOpen()) {
            throw new \Exception("Container must be OPEN to be sealed.");
        }

        $container->update([
            'status' => Container::STATUS_SEALED,
            'sealed_at' => now(),
            'sealed_by' => $user?->id ?? auth()->id(),
            // 'seal_no' => passed in via update? or separate arg? assuming validation happened before
        ]);

        return $container;
    }

    /**
     * Unload a container (Arrival at hub)
     */
    public function unload(Container $container, ?User $user = null): Container
    {
        return DB::transaction(function() use ($container, $user) {
            // Logic: Mark container UNLOADED? Or just empty it?
            // "Unloading" usually means processing shipments inside.
            // Requirement says: "Supports sealing, unloading, auditing"
            // Strategy A: Container is moving unit. On unload, shipments continue lifecycle.
            
            $container->update([
                'status' => Container::STATUS_UNLOADED,
                'unloaded_at' => now(),
                'unloaded_by' => $user?->id ?? auth()->id(),
            ]);
            
            // Optionally: Clear shipments from it? 
            // "When removed/unloaded -> set it back to NULL" (from User Request)
            
            // Bulk remove/unload logic:
            $userId = $user?->id ?? auth()->id();
            
            // Update history for all active shipments
            \App\Models\ContainerShipment::where('container_id', $container->id)
                ->whereNull('removed_at')
                ->update([
                    'removed_at' => now(),
                    'removed_by' => $userId,
                    'unloaded_at' => now(),
                    'unloaded_by' => $userId,
                ]);

            // Clear pointers
            Shipment::where('current_container_id', $container->id)
                ->update(['current_container_id' => null, 'container_id' => null]);

            $container->update([
                'shipment_count' => 0, 
                'current_weight' => 0
            ]);

            return $container;
        });
    }
    
    /**
     * Close/Archive a container
     */
    public function close(Container $container): Container
    {
         $container->update([
             'status' => Container::STATUS_CLOSED,
             'closed_at' => now(),
         ]);
         return $container;
    }

    /**
     * Update container status (Generic)
     */
    public function updateStatus(Container $container, string $status): Container
    {
        // Add specific checks if needed
        $container->update(['status' => $status]);
        return $container;
    }

    /**
     * Get containers with pagination
     */
    public function getContainers(array $filters = [], int $perPage = 15)
    {
        $query = Container::query();


        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['container_type'])) {
            $query->where('container_type', $filters['container_type']);
        }

        if (isset($filters['facility_type'])) {
            $query->where('facility_type', $filters['facility_type']);
        }

        if (isset($filters['facility_id'])) {
            $query->where('facility_id', $filters['facility_id']);
        }

        if (isset($filters['search'])) {
            $query->where('container_number', 'like', '%' . $filters['search'] . '%')
                ->orWhere('tracking_no', 'like', '%' . $filters['search'] . '%');
        }

        return $query->with('currentShipments', 'facility', 'creator')->paginate($perPage);
    }

    /**
     * Get container details with statistics
     */
    public function getContainerDetails(Container $container): array
    {
        return [
            'id' => $container->id,
            'code' => $container->code, // New
            'container_number' => $container->container_number,
            'tracking_no' => $container->tracking_no,
            'container_type' => $container->container_type,
            'status' => $container->status,
            
            'routing' => [
                'from_hub' => $container->fromHub,
                'current_hub' => $container->currentHub,
                'target_hub' => $container->targetHub,
                'final_hub' => $container->finalHub,
            ],
            'lifecycle' => [
                'sealed_at' => $container->sealed_at,
                'sealed_by' => $container->sealer,
                'unloaded_at' => $container->unloaded_at,
                'unloaded_by' => $container->unloader,
                'closed_at' => $container->closed_at,
            ],

            'shipment_count' => $container->shipment_count,
            'max_weight' => $container->max_weight,
            'current_weight' => $container->current_weight,
            'remaining_weight' => $container->getRemainingWeight(),
            'max_volume' => $container->max_volume,
            'current_volume' => $container->current_volume,
            'remaining_volume' => $container->getRemainingVolume(),
            'utilization_percentage' => $container->getUtilizationPercentage(),
            'is_full' => $container->isFull(),
            'notes' => $container->notes,
            // Use currentShipments for verification or listing ids
            'shipments' => $container->currentShipments()->pluck('id', 'tracking_no'), 
            
            'facility' => $container->facility,
            'owner' => $container->owner, // polymorphic
            'created_by' => $container->creator,
            'created_at' => $container->created_at,
            'updated_at' => $container->updated_at,
        ];
    }
}
