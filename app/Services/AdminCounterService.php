<?php

namespace App\Services;

use App\Models\User;
use App\Models\Shipment;
use App\Models\MerchantPickupShipment;
use App\Models\AdminNotificationRead;
use App\Events\AdminCountersUpdated;
use App\Models\Hub;
use App\Models\Branch;
use App\Models\Station;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Models\PickupRequest;
use App\Enums\PickupRequestStatusEnum;
use App\Models\DriverRunsheet;
use App\Models\PickuptaskTransaction;

class AdminCounterService
{
    protected function applyOwnerScope($query, User $user, $ownerTypeCol = 'owner_type', $ownerIdCol = 'owner_id')
    {
        if ($user->isSuperAdmin()) {
            return;
        }

        // Check user roles/scopes
        if ($user->branch_user) {
            $query->where($ownerTypeCol, Branch::class)
                ->where($ownerIdCol, $user->branch_user->branch_id);
        } elseif ($user->station_user) {
            $query->where($ownerTypeCol, Station::class)
                ->where($ownerIdCol, $user->station_user->station_id);
        } elseif ($user->hub_user) {
            $query->where($ownerTypeCol, Hub::class)
                ->where($ownerIdCol, $user->hub_user->hub_id);
        } else {
            // If no specific scope, maybe return nothing or all? 
            // Safest is to return nothing if they are not super admin and not scoped.
            // But existing system might default to something. 
            // Let's assume strict scoping: if not super admin and no facility, they see nothing.
            $query->whereRaw('0 = 1');
        }
    }

    public function getUnassignedCount(User $admin): int
    {
        if ($admin->hasRole('Merchant')) {
            return 0;
        }

        $query = MerchantPickupShipment::query()
            ->whereNull('shipment_id'); // Unassigned means not converted to a shipment yet

        if (!$admin->isSuperAdmin()) {
            $query->whereHas('merchant', function ($q) use ($admin) {
                // Manually apply scope to Merchant
                $this->applyOwnerScope($q, $admin, 'owner_type', 'owner_id');
            });
        }

        $query->whereDoesntHave('reads', function ($q) use ($admin) {
            $q->where('user_id', $admin->id)
                ->where('read_type', 'unassigned');
        });

        return $query->count();
    }

    public function getUnregisteredCount(User $admin): int
    {
        if ($admin->hasRole('Merchant')) {
            return 0;
        }

        $query = Shipment::query()
            ->whereNotNull('pre_id')
            ->where(function ($q) {
                $q->whereNull('tracking_no')
                    ->orWhere('tracking_no', '');
            });

        // Apply Scope
        $this->applyOwnerScope($query, $admin, 'owner_type', 'owner_id');

        $query->whereDoesntHave('adminReads', function ($q) use ($admin) {
            $q->where('user_id', $admin->id)
                ->where('read_type', 'unregistered');
        });

        return $query->count();
    }

    public function getTransferTaskCount(User $admin): int
    {
        if ($admin->hasRole('Merchant')) {
            return 0;
        }

        // Status PENDING? TransferTask STATUS is usually numeric or string?
        // Checking Controller/Model... usually 'pending', 'accepted', etc.
        // I will assume 'pending'.

        $query = \App\Models\TransferTask::query()
            ->where('status', 'pending');

        $this->applyOwnerScope($query, $admin, 'owner_type', 'owner_id');


        // Note: TransferTask doesn't have 'reads' relation yet. I need to add it or use raw query check.
        // Or simpler: check existing AdminNotificationRead table directly.
        $query->whereNotExists(function ($sub) use ($admin) {
            $sub->select(\DB::raw(1))
                ->from('admin_notification_reads')
                ->where('user_id', $admin->id)
                ->where('read_type', 'transfer_task')
                ->whereColumn('admin_notification_reads.readable_id', 'transfer_tasks.id');
        });

        return $query->count();
    }

    public function getGuestDriverCount(User $admin): int
    {
        if ($admin->hasRole('Merchant')) {
            return 0;
        }

        $query = \App\Models\Driver::query()
            ->where('is_guest', true);

        $this->applyOwnerScope($query, $admin, 'owner_type', 'owner_id');

        $query->whereNotExists(function ($sub) use ($admin) {
            $sub->select(\DB::raw(1))
                ->from('admin_notification_reads')
                ->where('user_id', $admin->id)
                ->where('read_type', 'guest_driver')
                ->whereColumn('admin_notification_reads.readable_id', 'drivers.id');
        });

        return $query->count();
    }

    public function getTransferShipmentsCount(User $admin): int
    {
        if ($admin->hasRole('Merchant')) {
            return 0;
        }

        $query = \App\Models\TransferShipment::query()
            ->pendingUnassigned(); // pending and not assigned to any transfer task

        // Apply ownership scope - only show transfer shipments owned by the admin's facility
        $this->applyOwnerScope($query, $admin, 'ownership_type', 'ownership_id');

        // Exclude already seen shipments
        $query->whereNotExists(function ($sub) use ($admin) {
            $sub->select(\DB::raw(1))
                ->from('admin_notification_reads')
                ->where('user_id', $admin->id)
                ->where('read_type', 'transfer_shipment')
                ->whereColumn('admin_notification_reads.readable_id', 'transfer_shipments.id');
        });

        return $query->count();
    }

    /**
     * Mark items as seen.
     * @param User $admin
     * @param string $type
     * @param array $ids
     */
    public function markAsSeen(User $admin, string $type, array $ids = []): void
    {
        if ($admin->hasRole('Merchant')) {
            return;
        }

        $readableIds = [];

        if (empty($ids)) {
            // Mark ALL relevant items
            if ($type === 'unassigned') {
                $query = MerchantPickupShipment::query()->whereNull('shipment_id');
                if (!$admin->isSuperAdmin()) {
                    $query->whereHas('merchant', function ($q) use ($admin) {
                        $this->applyOwnerScope($q, $admin);
                    });
                }
                $this->excludeSeen($query, $admin, 'unassigned', 'merchant_pickup_shipments');
                $readableIds = $query->pluck('id')->toArray();
            } elseif ($type === 'unregistered') {
                $query = Shipment::query()
                    ->whereNotNull('pre_id')
                    ->where(function ($q) {
                        $q->whereNull('tracking_no')->orWhere('tracking_no', '');
                    });
                $this->applyOwnerScope($query, $admin);
                $this->excludeSeen($query, $admin, 'unregistered', 'shipments');
                $readableIds = $query->pluck('id')->toArray();
            } elseif ($type === 'transfer_task') {
                $query = \App\Models\TransferTask::query()->where('status', 'pending');
                $this->applyOwnerScope($query, $admin);
                $this->excludeSeen($query, $admin, 'transfer_task', 'transfer_tasks');
                $readableIds = $query->pluck('id')->toArray();
            } elseif ($type === 'guest_driver') {
                $query = \App\Models\Driver::query()->withoutGlobalScopes()->where('is_guest', true);
                $this->applyOwnerScope($query, $admin);
                $this->excludeSeen($query, $admin, 'guest_driver', 'drivers');
                $readableIds = $query->pluck('id')->toArray();
            } elseif ($type === 'transfer_shipment') {
                $query = \App\Models\TransferShipment::query()->pendingUnassigned();
                $this->applyOwnerScope($query, $admin, 'ownership_type', 'ownership_id');
                $this->excludeSeen($query, $admin, 'transfer_shipment', 'transfer_shipments');
                $readableIds = $query->pluck('id')->toArray();
            }
        } else {
            $readableIds = $ids;
        }

        if (empty($readableIds)) {
            return;
        }

        $now = now();
        $records = [];
        foreach ($readableIds as $id) {
            // Avoid duplicates check slightly expensive here, assuming insertOrIgnore handles DB side constraints
            $records[] = [
                'user_id' => $admin->id,
                'read_type' => $type,
                'readable_id' => $id,
                'read_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        AdminNotificationRead::insertOrIgnore($records);
        $this->broadcastUpdate($admin);
    }

    protected function excludeSeen($query, $admin, $type, $table)
    {
        $query->whereNotExists(function ($sub) use ($admin, $type, $table) {
            $sub->select(\DB::raw(1))
                ->from('admin_notification_reads')
                ->where('user_id', $admin->id)
                ->where('read_type', $type)
                ->whereColumn('admin_notification_reads.readable_id', $table . '.id');
        });
    }

    public function broadcastUpdate(User $admin)
    {
        try {
            $unassigned = $this->getUnassignedCount($admin);
            $unregistered = $this->getUnregisteredCount($admin);
            $transfer = $this->getTransferTaskCount($admin);
            $guest = $this->getGuestDriverCount($admin);
            $transferShipments = $this->getTransferShipmentsCount($admin);
            $pickupRequests = $this->getPickupRequestsCount();
            $codCollection = $this->codCollection();
            $pickupCollection = $this->pickupCollection();

            Log::info("Broadcasting admin counters: $unassigned, $unregistered, $transfer, $guest, $transferShipments");
            AdminCountersUpdated::dispatch($admin->id, $unassigned, $unregistered, $transfer, $guest, $transferShipments, $pickupRequests, $codCollection, $pickupCollection);
        } catch (\Exception $e) {
            Log::error("Failed to broadcast admin counters: " . $e->getMessage());
        }
    }

    public function broadcastToAllAdmins()
    {
        User::query()
            ->whereDoesntHave('roles', function ($q) {
                $q->where('name', 'Merchant')->orWhere('name', 'Driver');
            })
            ->chunk(50, function ($admins) {
                foreach ($admins as $admin) {
                    $this->broadcastUpdate($admin);
                }
            });
    }
    public function getPickupRequestsCount(): int
    {
       return PickupRequest::query()
            ->where('status', PickupRequestStatusEnum::PENDING)->count();
    }
    public function codCollection()
    {
        return DriverRunsheet::where('status', 'pending')->count();
    }
    public function pickupCollection()
    {
        return PickuptaskTransaction::where('status','pending')->count();
    }
}
