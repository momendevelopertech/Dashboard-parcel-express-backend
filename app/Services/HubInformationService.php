<?php

namespace App\Services;

use App\Models\Shipment;
use App\Http\Controllers\Api\v1\SorterController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Service to manage shipment hub information (final, current, from)
 * 
 * RULES:
 * - Final Hub = Set ONCE at creation from address → zone → owner (IMMUTABLE)
 * - Current Hub = Updated by operational events (inbound/unload sorting)
 * - From Hub = Previous current location before transfer
 */
class HubInformationService
{
    /**
     * Set the final hub for a shipment (called at creation)
     * This resolves the zone from the shipment address and sets the final destination
     * 
     * @param Shipment $shipment
     * @return bool Whether final hub was successfully set
     */
    public function setFinalHub(Shipment $shipment): bool
    {

        try {
            // Resolve zone from shipment address
            $zone = app(SorterController::class)->resolveZoneForShipment($shipment);

            
            if (!$zone) {
                Log::warning('Could not resolve zone for shipment', [
                    'shipment_id' => $shipment->id,
                    'tracking_no' => $shipment->tracking_no,
                ]);
                return false;
            }
            // Set final hub (IMMUTABLE - ultimate destination)
            $shipment->final_owner_type = $zone->owner_type;
            $shipment->final_owner_id = $zone->owner_id;
            
            // Also set destination_owner for backward compatibility
            $shipment->destination_owner_type = $zone->owner_type;
            $shipment->destination_owner_id = $zone->owner_id;
            
            // Backward compat: Set final_hub_id if zone owner is a hub
            if ($zone->owner_type === \App\Models\Hub::class) {
                $shipment->final_hub_id = $zone->owner_id;
            }
            
            Log::info('Final hub set for shipment', [
                'shipment_id' => $shipment->id,
                'tracking_no' => $shipment->tracking_no,
                'final_owner_type' => $shipment->final_owner_type,
                'final_owner_id' => $shipment->final_owner_id,
            ]);
            
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to set final hub for shipment', [
                'shipment_id' => $shipment->id,
                'tracking_no' => $shipment->tracking_no,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
    
    /**
     * Set the initial owner for a shipment (called at creation)
     * 
     * NOTE: current_owner and from_owner should ONLY be set by sorting operations!
     * This method ONLY sets the legacy owner_type/id fields for backward compatibility.
     * 
     * @param Shipment $shipment
     * @param string|null $ownerType Override owner type (defaults to Auth user's owner_type)
     * @param int|null $ownerId Override owner ID (defaults to Auth user's owner_id)
     * @return void
     */
    public function setInitialCurrentHub(Shipment $shipment, ?string $ownerType = null, ?int $ownerId = null): void
    {
        $ownerType = $ownerType ?? Auth::user()->owner_type;
        $ownerId = $ownerId ?? Auth::user()->owner_id;
        
        // Keep legacy owner_type/id field updated (for backward compatibility only)
        $shipment->owner_type = $ownerType;
        $shipment->owner_id = $ownerId;
        
        // CRITICAL: Explicitly set current_owner and from_owner to NULL
        // These should ONLY be set by sorting operations (inbound/unload)
        // Setting them explicitly to NULL ensures no default values or stale data
        $shipment->current_owner_type = null;
        $shipment->current_owner_id = null;
        $shipment->current_hub_id = null; // Backward compat legacy field
        
        $shipment->from_owner_type = null;
        $shipment->from_owner_id = null;
        $shipment->from_hub_id = null; // Backward compat legacy field
        
        Log::info('Initial owner set for shipment (current_owner/from_owner explicitly set to NULL)', [
            'shipment_id' => $shipment->id,
            'tracking_no' => $shipment->tracking_no,
            'owner_type' => $shipment->owner_type,
            'owner_id' => $shipment->owner_id,
            'current_owner_type' => null,
            'current_owner_id' => null,
            'from_owner_type' => null,
            'from_owner_id' => null,
        ]);
    }
    
    /**
     * Update current hub during sorting operations (inbound/unload)
     * This tracks where the shipment is NOW and where it came FROM
     * 
     * RULES:
     * - Only called by: inbound sort, unload sort (NEVER by load sort or other operations)
     * - First time (current_owner is NULL): Set current_owner, leave from_owner NULL
     * - Subsequent times: Copy old current_owner to from_owner, then set new current_owner
     * 
     * @param Shipment $shipment
     * @param string $newOwnerType Facility type where shipment is now (from facility())
     * @param int $newOwnerId Facility ID where shipment is now (from facility())
     * @return void
     */
    public function updateCurrentHub(Shipment $shipment, string $newOwnerType, int $newOwnerId): void
    {
        // CRITICAL: Read current values DIRECTLY from attributes to avoid any accessor interference
        $existingCurrentType = $shipment->getAttributes()['current_owner_type'] ?? null;
        $existingCurrentId = $shipment->getAttributes()['current_owner_id'] ?? null;
        
        // Log values BEFORE any changes for debugging
        Log::info('updateCurrentHub CALLED - values BEFORE update', [
            'shipment_id' => $shipment->id,
            'tracking_no' => $shipment->tracking_no,
            'existing_current_owner_type' => $existingCurrentType,
            'existing_current_owner_id' => $existingCurrentId,
            'existing_from_owner_type' => $shipment->getAttributes()['from_owner_type'] ?? null,
            'existing_from_owner_id' => $shipment->getAttributes()['from_owner_id'] ?? null,
            'final_owner_type' => $shipment->getAttributes()['final_owner_type'] ?? null,
            'final_owner_id' => $shipment->getAttributes()['final_owner_id'] ?? null,
            'new_owner_type' => $newOwnerType,
            'new_owner_id' => $newOwnerId,
        ]);
        
        // Check if current_owner is already set (has shipment been in a hub before?)
        // Use strict check: both must be non-null AND non-empty
        $hasExistingCurrentHub = !is_null($existingCurrentType) && $existingCurrentType !== '' 
                               && !is_null($existingCurrentId) && $existingCurrentId !== '' && $existingCurrentId !== 0;
        
        if ($hasExistingCurrentHub) {
            // Subsequent inbound/unload: Copy old current to from
            $shipment->from_owner_type = $existingCurrentType;
            $shipment->from_owner_id = $existingCurrentId;
            
            // Backward compat: Set from_hub_id if previous owner was a hub
            if ($existingCurrentType === \App\Models\Hub::class) {
                $shipment->from_hub_id = $existingCurrentId;
            } else {
                $shipment->from_hub_id = null;
            }
            
            Log::info('Previous hub tracked (subsequent inbound/unload)', [
                'shipment_id' => $shipment->id,
                'tracking_no' => $shipment->tracking_no,
                'from_owner_type' => $shipment->from_owner_type,
                'from_owner_id' => $shipment->from_owner_id,
            ]);
        } else {
            // First inbound: EXPLICITLY set from_owner to NULL
            $shipment->from_owner_type = null;
            $shipment->from_owner_id = null;
            $shipment->from_hub_id = null;
            
            Log::info('First inbound - from_owner explicitly set to NULL', [
                'shipment_id' => $shipment->id,
                'tracking_no' => $shipment->tracking_no,
            ]);
        }
        
        // Update current location
        $shipment->current_owner_type = $newOwnerType;
        $shipment->current_owner_id = $newOwnerId;
        
        // Backward compat: Set current_hub_id if new owner is a hub
        if ($newOwnerType === \App\Models\Hub::class) {
            $shipment->current_hub_id = $newOwnerId;
        } else {
            $shipment->current_hub_id = null;
        }
        
        // Keep legacy owner_type/id field updated
        $shipment->owner_type = $newOwnerType;
        $shipment->owner_id = $newOwnerId;
        
        Log::info('updateCurrentHub COMPLETED - values AFTER update', [
            'shipment_id' => $shipment->id,
            'tracking_no' => $shipment->tracking_no,
            'is_first_time' => !$hasExistingCurrentHub,
            'from_owner_type' => $shipment->from_owner_type,
            'from_owner_id' => $shipment->from_owner_id,
            'current_owner_type' => $shipment->current_owner_type,
            'current_owner_id' => $shipment->current_owner_id,
        ]);
    }
    
    /**
     * Recalculate and update final hub when address changes
     * This is called when delivery address is updated
     * 
     * @param Shipment $shipment
     * @return bool Whether final hub was successfully updated
     */
    public function recalculateFinalHub(Shipment $shipment): bool
    {
        try {
            $oldFinalOwnerType = $shipment->final_owner_type;
            $oldFinalOwnerId = $shipment->final_owner_id;
            
            // Re-resolve zone from new address
            $zone = app(SorterController::class)->resolveZoneForShipment($shipment);
            
            if (!$zone) {
                Log::warning('Could not resolve zone for shipment after address change', [
                    'shipment_id' => $shipment->id,
                    'tracking_no' => $shipment->tracking_no,
                ]);
                return false;
            }
            
            // Update final hub with new zone owner
            $shipment->final_owner_type = $zone->owner_type;
            $shipment->final_owner_id = $zone->owner_id;
            
            // Also update destination_owner for backward compatibility
            $shipment->destination_owner_type = $zone->owner_type;
            $shipment->destination_owner_id = $zone->owner_id;
            
            // Backward compat: Update final_hub_id if zone owner is a hub
            if ($zone->owner_type === \App\Models\Hub::class) {
                $shipment->final_hub_id = $zone->owner_id;
            } else {
                $shipment->final_hub_id = null;
            }
            
            Log::info('Final hub recalculated after address change', [
                'shipment_id' => $shipment->id,
                'tracking_no' => $shipment->tracking_no,
                'old_final_owner_type' => $oldFinalOwnerType,
                'old_final_owner_id' => $oldFinalOwnerId,
                'new_final_owner_type' => $shipment->final_owner_type,
                'new_final_owner_id' => $shipment->final_owner_id,
            ]);
            
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to recalculate final hub after address change', [
                'shipment_id' => $shipment->id,
                'tracking_no' => $shipment->tracking_no,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}

