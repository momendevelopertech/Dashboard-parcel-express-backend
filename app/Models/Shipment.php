<?php

namespace App\Models;

use App\Traits\ResolvesZone;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Traits\LogsUserActions;
use App\Observers\ShipmentObserver;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use App\Domain\Pickup\Status\MerchantShipmentStatusMapper;
use App\Enums\MerchantPickupTaskStatusEnum;
use App\Enums\ShipmentStatusEnum;
use Illuminate\Support\Facades\DB;

#[ObservedBy([ShipmentObserver::class])]
class Shipment extends Model
{
    use HasFactory, LogsUserActions, SoftDeletes, Notifiable, ResolvesZone;

    public function adminReads()
    {
        return $this->hasMany(AdminNotificationRead::class, 'readable_id')->where('read_type', 'unregistered');
    }


    /**
     * Boot the model and apply global scopes.
     * Excludes return shipments from regular queries by default.
     */
    protected static function booted()
    {
        static::addGlobalScope(new \App\Models\Scopes\ExcludeReturnShipmentsScope());
    }

    public function routeNotificationForWhatsapp()
    {
        return $this->cellphone;
    }

    public function routeNotificationForMail()
    {
        return $this->email ?? "mailtests@parcelexpress.om";
    }
    protected $appends = [
        'company',
        'warehouse',
        'owner',
        'ndr_status',
        'attempt_date',
    ];
    protected $casts = [
        // 'is_alert' => 'boolean',
        // 'value' => 'decimal:2',
        // 'amount' => 'decimal:2',
        // 'delivery_fee' => 'decimal:2',
        'value' => 'decimal:3',
        'total_cod' => 'decimal:3',
        'delivery_fee' => 'decimal:3',
        'delivery_fee_before_discount' => 'decimal:3',
        'delivery_fee_discount' => 'decimal:3',
        'return_fee_before_discount' => 'decimal:3',
        'return_fee_discount' => 'decimal:3',
        'return_fee' => 'decimal:3',
        'is_return' => 'bool',
        'in_exception' => 'bool',
        'is_sorted' => 'bool',
        'allow_return' => 'bool',
        'is_walkin' => 'bool',
        'is_outsourced' => 'bool',
        'delivered_at' => 'datetime',
        'picked_at' => 'datetime',
    ];

    protected $fillable = [
        // "facility_id",
        // "facility_type",
        // "owner_id",
        // "owner_type",
        // "driver_id",
        // "assignment_id",
        // "consignee_id",
        // "shipper_id",
        // "merchant_id",
        // "shipment_type_id",
        // "tracking_no",
        // "from_hub_id",
        // "current_hub_id",
        // "final_hub_id",
        // "in_exception",
        // "notes",
        // "value",
        // 'delivery_fee',
        // "amount",
        // "is_return",
        // "created_by",
        // "payment_type",
        // "is_walkin",
        // "is_outsourced",
        // "customer_name",
        // "customer_phone",
        // "customer_id_card",
        // "fee_payer",
        // 'country_id',
        // 'governorate_id',
        // 'state_id',
        // 'place_id',
        // 'city_id',
        // 'zipcode',
        // 'streetAddress',
        // 'longitude',
        // 'latitude',
        // "allow_return",
        // "delivery_priority",
        // "delivery_time",
        // "sender_district",
        // "sender_location_url",
        // "sender_notes",
        // "sender_streetAddress",
        // "sender_zipcode",
        // "need_invoice",
        // "sender_country_id",
        // "sender_governorate_id",
        // "sender_state_id",
        // "sender_place_id",
        // "is_alert",


        'partner_shipment_tracking_number',
        'facility_type',
        'facility_id',
        'owner_type',
        'owner_id',
        'consignee_id',
        'customer_id',
        'shipper_id',
        'marketplace_partner_id',
        'merchant_id',
        'driver_id',
        'shipment_type_id',
        'assignment_id',
        'tracking_no',
        'exception_type',
        'pre_id',
        // Legacy hub ID fields (deprecated, use polymorphic fields instead)
        'from_hub_id',
        'current_hub_id',
        'target_hub_id',
        'final_hub_id',
        // New polymorphic hub information fields
        'final_owner_type',
        'final_owner_id',
        'current_owner_type',
        'current_owner_id',
        'from_owner_type',
        'from_owner_id',
        'destination_owner_type',
        'destination_owner_id',
        'value',
        'total_cod',
        'delivery_fee',
        'delivery_fee_before_discount',
        'delivery_fee_discount',
        'payment_type',
        'pickup_address_id',
        'pickup_request_id',
        'delivery_address_id',
        'return_kind',
        // 'return_type' removed - column doesn't exist
        'return_to_type',
        'return_to_id',
        'return_fee_before_discount',
        'return_fee_discount',
        'return_fee',
        'return_fee_source',
        'return_request_id', // Renamed from parent_reverse_shipment_id
        'parent_shipment_id', // Original shipment being returned
        'is_return',
        'in_exception',
        'is_sorted',
        'created_by',
        'notes',
        'status',
        'allow_return',
        'delivery_priority',
        'delivery_time',
        'sender_district',
        'sender_notes',
        'sender_streetAddress',
        'sender_zipcode',
        'sender_country_id',
        'sender_governorate_id',
        'sender_state_id',
        'sender_place_id',
        'sender_location_url',
        'sender_longitude',
        'sender_latitude',
        'is_walkin',
        'customer_name',
        'customer_phone',
        'customer_id_card',
        'fee_payer',
        'is_outsourced',
        'latitude',
        'longitude',
        'location_url',
        'created_source',
        'delivered_at',
        'picked_at',
        'container_id',
        'current_container_id',
    ];
    public function scopeInhouse($q)
    {
        return $q->where('is_outsourced', 0);
    }
    public function feeAllocations(): HasMany
    {
        return $this->hasMany(FeeAllocation::class, 'shipment_id');
    }
    public function pickupAddress()
    {
        return $this->belongsTo(Address::class, 'pickup_address_id');
    }
    public function deliveryAddress()
    {
        return $this->belongsTo(Address::class, 'delivery_address_id');
    }
    public function addressRevisions()
    {
        return $this->hasMany(ShipmentAddressRevision::class);
    }
    public function scopeOutsourced($q)
    {
        return $q->where('is_outsourced', 1);
    }
    public function scopeWithStatus($query, $status)
    {
        return $query->whereHas('shipmentHistories', function ($q) use ($status) {
            $q->where('name', $status);
        });
    }

    public function coreStatus()
    {
        return $this->shipmentHistories()->first();
    }
    public function changeDeliveryAddress(array $newAddressData, ?string $reason = null, ?int $changedBy = null): Address
    {
        return \DB::transaction(function () use ($newAddressData, $reason, $changedBy) {
            $old = $this->deliveryAddress;

            $new = Address::create(array_merge($newAddressData, [
                'consignee_id' => $this->consignee_id,
                'approved' => false,
                'is_active' => true,
            ]));

            $this->update(['delivery_address_id' => $new->id]);

            ShipmentAddressRevision::create([
                'shipment_id' => $this->id,
                'old_address_id' => optional($old)->id,
                'new_address_id' => $new->id,
                'changed_by' => $changedBy,
                'reason' => $reason,
            ]);

            // Recalculate final hub based on new address
            $hubService = app(\App\Services\HubInformationService::class);
            $hubService->recalculateFinalHub($this);
            $this->save();

            return $new;
        });
    }

    /**
     * اعتماد العنوان عند التسليم الناجح (deliver) + تتبّع الاستخدام.
     */
    public function approveDeliveryAddressOnDeliver(int $driverUserId): void
    {
        if (!$this->delivery_address_id || !$this->consignee_id)
            return;

        \DB::transaction(function () use ($driverUserId) {
            $address = Address::where('id', $this->delivery_address_id)
                ->where('consignee_id', $this->consignee_id)
                ->lockForUpdate()
                ->first();

            if ($address) {
                $updates = [
                    'approved' => true,
                    'approved_at' => $address->approved_at ?: now(),
                    'approved_by' => $address->approved_by ?: $driverUserId,
                    'times_used' => \DB::raw('times_used + 1'),
                    'last_used_at' => now(),
                    // أهم سطور التوثيق:
                    'is_verified' => true,
                    'verification_method' => Address::VM_DELIVERY,
                    'verified_at' => now(),
                ];
                $address->update($updates);
            }
        });
    }
    // public function approveDeliveryAddressOnDeliver(int $driverUserId): void
    // {
    //     if (!$this->delivery_address_id || !$this->consignee_id)
    //         return;

    //     \DB::transaction(function () use ($driverUserId) {
    //         $address = Address::where('id', $this->delivery_address_id)
    //             ->where('consignee_id', $this->consignee_id)
    //             ->lockForUpdate()
    //             ->first();

    //         if ($address) {
    //             $updates = [
    //                 'approved' => true,
    //                 'approved_at' => $address->approved_at ?: now(),
    //                 'approved_by' => $address->approved_by ?: $driverUserId,
    //                 'times_used' => \DB::raw('times_used + 1'),
    //                 'last_used_at' => now(),
    //             ];
    //             if (!$address->first_approved_shipment_id) {
    //                 $updates['first_approved_shipment_id'] = $this->id;
    //             }
    //             $address->update($updates);
    //         }
    //     });
    // }
    public function core_status()
    {
        return $this
            ->hasOne(ShipmentHistory::class, 'shipment_id')
            ->orderBy('id', 'desc');
    }

    public function core_exception()
    {
        return $this
            ->hasOne(ShipmentHistory::class, 'shipment_id')
            ->where('name', 'DELIVERY_EXCEPTION')
            ->orderBy('id', 'desc');
    }
    public function core_delivered()
    {
        return $this
            ->hasOne(ShipmentHistory::class, 'shipment_id')
            ->where('name', 'DELIVERED')
            ->orderBy('id', 'desc');
    }


    public function consignee()
    {
        return $this->belongsTo(Consignee::class, 'consignee_id');
    }

    public function container()
    {
        return $this->belongsTo(Container::class, 'container_id');
    }

    /**
     * Active Container - Current physical location inside a container
     */
    public function currentContainer()
    {
        return $this->belongsTo(Container::class, 'current_container_id');
    }

    public function country()
    {
        return $this->belongsTo(Country::class, 'country_id');
    }

    public function governorate()
    {
        return $this->belongsTo(Governorate::class, 'governorate_id');
    }

    public function state()
    {
        return $this->belongsTo(State::class, 'state_id');
    }

    public function place()
    {
        return $this->belongsTo(Place::class, 'place_id');
    }
    public function senderCountry()
    {
        return $this->belongsTo(Country::class, 'sender_country_id');
    }

    public function senderGovernorate()
    {
        return $this->belongsTo(Governorate::class, 'sender_governorate_id');
    }

    public function senderState()
    {
        return $this->belongsTo(State::class, 'sender_state_id');
    }

    public function senderPlace()
    {
        return $this->belongsTo(Place::class, 'sender_place_id');
    }

    public function city()
    {
        return $this->belongsTo(City::class, 'city_id');
    }

    public function shipper()
    {
        return $this->belongsTo(Shipper::class, 'shipper_id');
    }

    public function marketplacePartner()
    {
        return $this->belongsTo(Partner::class, 'marketplace_partner_id');
    }

    public function shipment_amounts()
    {
        return $this->hasMany(ShipmentAmount::class, 'shipment_id');
    }

    public function shipment_items()
    {
        return $this->hasMany(ShipmentItem::class, 'shipment_id');
    }


    /**
     * Get the driver warnings associated with this shipment.
     */
    public function driverWarnings()
    {
        return $this->hasMany(DriverWarning::class, 'shipment_tracking_no', 'tracking_no');
    }

    public function shipment_information()
    {
        return $this->hasOne(ShipmentInformation::class, 'shipment_id');
    }

    public function merchant()
    {
        return $this->belongsTo(User::class, 'merchant_id');
    }

    public function shipmentHistories()
    {
        return $this->hasMany(ShipmentHistory::class, 'shipment_id')->orderBy('id', 'desc');
    }

    public function driverAssignments()
    {
        return $this->hasMany(DriverShipmentAssignment::class, 'shipment_id');
    }

    public function shipment_delivery()
    {
        return $this->hasOne(ShipmentDelivery::class, 'shipment_id');
    }

    public function incrementOFDCount()
    {
        $shipmentDelivery = $this->shipment_delivery;
        if ($shipmentDelivery) {
            $shipmentDelivery->addOFDCount();
        }
    }

    public function owner()
    {
        return $this->morphTo();
    }

    public function driver_warnings()
    {
        return $this->hasMany(DriverWarning::class, 'shipment_tracking_no', 'tracking_no');
    }
    public function destinationOwner()
    {
        return $this->morphTo('destination_owner', 'destination_owner_type', 'destination_owner_id');
    }

    /**
     * Final Hub - Ultimate destination (IMMUTABLE, set at creation from zone)
     */
    public function finalOwner()
    {
        return $this->morphTo('final_owner', 'final_owner_type', 'final_owner_id');
    }

    /**
     * Current Hub - Physical location NOW (updated by operational events)
     */
    public function currentOwner()
    {
        return $this->morphTo('current_owner', 'current_owner_type', 'current_owner_id');
    }

    /**
     * Previous Hub - Where shipment came FROM (set during transfers)
     */
    public function fromOwner()
    {
        return $this->morphTo('from_owner', 'from_owner_type', 'from_owner_id');
    }

    public function facility()
    {
        return $this->morphTo();
    }

    public function assigned_to_shelf()
    {
        return $this->hasOne(AssignShipmentToShelf::class, 'tracking_no', 'tracking_no');
    }

    public function shelf()
    {
        return $this->hasOneThrough(Shelf::class, AssignShipmentToShelf::class, "tracking_no", "barcode");
    }

    public function waybill()
    {
        return $this->belongsTo(MerchantWaybill::class, 'tracking_no', 'tracking_no');
    }

    public function transfer_task()
    {
        return $this->hasOne(TransferShipment::class, 'shipment_tracking_no');
    }

    public function zone_shipment()
    {
        return $this->hasOne(ZoneShipment::class, 'shipment_tracking_no', 'tracking_no');
    }

    public function shipment_integration()
    {
        return $this->hasOne(ShipmentIntegration::class, 'shipment_tracking_no', 'tracking_no');
    }

    public function transfer_shipment()
    {
        return $this->hasOne(TransferTaskShipment::class, 'shipment_tracking_no', 'shipment_tracking_no');
    }

    public function shipment_finance()
    {
        return $this->hasOne(ShipmentFinance::class, 'shipment_tracking_no', 'tracking_no');
    }

    public function merchant_commission_transaction()
    {
        return $this->hasOne(MerchantCommissionTransaction::class, 'shipment_id');
    }

    public function shipment_fine()
    {
        return $this->hasOne(ShipmentFine::class, 'shipment_tracking_no', 'tracking_no');
    }

    public function abnormalities()
    {
        return $this->hasMany(Abnormality::class, 'shipment_tracking_no', 'tracking_no');
    }

    public function invoice_shipment()
    {
        return $this->hasOne(InvoiceShipment::class, 'shipment_tracking_no', 'tracking_no');
    }

    public function runsheet_shipment()
    {
        return $this->hasOne(DriverRunsheetShipment::class, 'shipment_tracking_no', 'tracking_no');
    }

    public function merchant_pickup_shipment()
    {
        return $this->hasOne(MerchantPickupShipment::class, 'shipment_id', 'id');
    }

    /**
     * DEPRECATED: Use returnRequest() instead
     * Kept for backward compatibility during migration
     */
    public function reverseShipments()
    {
        return $this->hasMany(ReverseShipment::class, 'parent_shipment_id');
    }

    /**
     * Return request that created this return shipment
     * Only applicable when is_return = true and return_type = 'reverse_pickup'
     */
    public function returnRequest()
    {
        return $this->belongsTo(ReturnRequest::class, 'return_request_id');
    }

    /**
     * Pickup task for this return shipment
     * Only applicable when is_return = true and return_type = 'reverse_pickup'
     */
    public function pickupTask()
    {
        return $this->hasOne(PickupTask::class, 'shipment_id');
    }

    /**
     * Parent shipment (for returns of a specific original shipment)
     */
    public function parentShipment()
    {
        return $this->belongsTo(Shipment::class, 'parent_shipment_id');
    }

    /**
     * Return shipments created from this original shipment
     */
    public function returnShipments()
    {
        return $this->hasMany(Shipment::class, 'parent_shipment_id')
            ->where('is_return', true);
    }



    /**
     * Compute the amount a driver should collect or be credited for this shipment
     * according to payment_type and fee_payer rules.
     *
     * Rules:
     * - If payment_type == 'COD':
     *   - If fee_payer == 'merchant': driver collects the COD value (prefer `value`, else amount net of delivery_fee when possible)
     *   - If fee_payer == 'customer' or anyone else: driver collects the COD amount (prefer amount, else value)
     * - Else (payment_type != 'COD'):
     *   - If fee_payer == 'customer': driver collects only the delivery_fee
     *   - Else (merchant pays fees): driver does not collect any money
     */
    /**
     * Calculate the amount a driver should collect for this shipment.
     *
     * Delegates to CalculationLogicService for centralized calculation logic.
     *
     * @return float Amount driver should collect
     */
    public function getDriverCollectibleAmount(): float
    {
        return app(\App\Services\CalculationLogicService::class)
            ->getDriverCollectibleAmount($this);
    }

    /**
     * Scopes for return shipments
     */
    public function scopeReturns($query)
    {
        return $query->where('is_return', true);
    }

    public function scopeOutbound($query)
    {
        return $query->where('is_return', false);
    }

    public function scopeReversePickup($query)
    {
        return $query->where('is_return', true)
            ->where('is_return', true);
    }

    public function scopeRTO($query)
    {
        return $query->where('is_return', true)
            ->where('return_kind', 'rto');
    }

    public function scopeReturnToMerchant($query)
    {
        return $query->where('is_return', true)
            ->where('return_to_type', 'merchant');
    }

    public function scopeReturnToPartner($query)
    {
        return $query->where('is_return', true)
            ->where('return_to_type', 'marketplace_partner');
    }

    public function scopeByOwner($query)
    {
        $user = Auth::user();
        $selectedWorkspaceId = request('selected_workspace');

        if ($user && $user->isSuperAdmin()) {
            return $query;
        }

        if ($user) {
            $query->where(function ($q) use ($user, $selectedWorkspaceId) {
                if ($user->branch_user && $selectedWorkspaceId) {
                    $q->where('owner_type', Branch::class)
                        ->where('owner_id', $selectedWorkspaceId);
                }

                if ($user->station_user && $selectedWorkspaceId) {
                    $q->orWhere(function ($q) use ($selectedWorkspaceId) {
                        $q->where('owner_type', Station::class)
                            ->where('owner_id', $selectedWorkspaceId);
                    });
                }

                if ($user->hub_user && $selectedWorkspaceId) {
                    $q->orWhere(function ($q) use ($selectedWorkspaceId) {
                        $q->where('owner_type', Hub::class)
                            ->where('owner_id', $selectedWorkspaceId);
                    });
                }
            });

            // Merchants can only see their own shipments
            if ($user->hasRole('Merchant')) {
                $query->where('merchant_id', $user->id);
            }
        }

        return $query;
    }


    public function quick_notes()
    {
        return $this->hasMany(QuickNote::class, 'shipment_tracking_no', 'tracking_no');
    }

    public function current_driver()
    {
        // return $this->belongsTo(Driver::class, 'driver_id');
        return $this->hasOne(Driver::class, 'user_id', 'driver_id');
    }

    public function current_assignment()
    {
        return $this->belongsTo(DriverShipmentAssignment::class, 'assignment_id');
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class, 'shipment_id');
    }

    public function driver_notifications()
    {
        return $this->hasMany(DriverNotification::class, 'shipment_tracking_no', 'tracking_no');
    }

    public function scopeInvoiceable($query)
    {
        return $query->where('status', 'DELIVERED')->whereDoesntHave('invoice_shipment');
    }

    public function getDriverCommissionAttribute()
    {
        return $this->shipment_finance->driver_delivery_fee ?? 0;
    }

    public function getMerchantCommissionAttribute()
    {
        return $this->shipment_finance->merchant_balance ?? 0;
    }

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function driver_status()
    {
        return $this->hasOneThrough(DriverStatus::class, User::class, 'id', 'driver_id', 'driver_id', 'id');
    }

    public function stock_out_task_shipments()
    {
        return $this->hasOne(StockOutTaskShipment::class, 'shipment_tracking_no', 'tracking_no');
    }

    public function crm_task()
    {
        return $this->hasOne(CrmTask::class, 'shipment_id');
    }

    public function shipment_type()
    {
        return $this->belongsTo(ShipmentType::class, 'shipment_type_id');
    }

    public function instant_delivery_assignment()
    {
        return $this->hasOne(DriverShipmentAssignment::class, 'shipment_id', 'id')
            ->whereNotNull('accepted_at')
            ->whereIn('status', ['ASSIGNED', 'DISPATCH', 'PICKED_UP', 'IN_TRANSIT']);
    }



    public function scopeArchived($query)
    {
        return $query->onlyTrashed();
    }
    public function scopeActive($query)
    {
        return $query->whereNull('deleted_at');
    }
    public function proofs()
    {
        return $this->hasMany(ShipmentProof::class);
    }
    public function scopePickupUnassigned($q)
    {
        return $q
            ->whereNull('consignee_id')
            ->whereNotNull('merchant_id')
            ->where('is_walkin', false);
    }

    public function scopeExcludePickupUnassigned($q)
    {
        return $q->where(function ($qq) {
            $qq->whereNotNull('consignee_id')
                ->orWhereNull('merchant_id')
                ->orWhere('is_walkin', true);
        });
    }

    public function getCompanyAttribute()
    {
        $drv = $this->relationLoaded('current_driver')
            ? $this->current_driver
            : $this->current_driver()->with('company:id,name')->first();

        if (!$drv) {
            return null;
        }

        $id = $drv->company_id ?? null;                // من جدول drivers
        $name = $drv->company_name ?? ($drv->company->name ?? null);

        return ($id || $name) ? ['id' => $id, 'name' => $name] : null;
    }



    public function getWarehouseAttribute()
    {
        // ✅ FIXED: Return CURRENT location (where shipment IS NOW), not final destination

        // 1) Priority: Use current_owner_type/id (physical location NOW)
        $currentType = $this->getAttribute('current_owner_type');
        $currentId = $this->getAttribute('current_owner_id');

        if (!empty($currentType) && !empty($currentId)) {
            try {
                $model = app($currentType)::find($currentId);
                if ($model) {
                    return [
                        'id' => $model->id,
                        'name' => $model->name ?? null,
                        'type' => $this->mapOwnerTypeToKey($currentType),
                    ];
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }

        // 2) Fallback: Use owner_type/id (legacy current location)
        $ownType = $this->getAttribute('owner_type');
        $ownId = $this->getAttribute('owner_id');

        if (!empty($ownType) && !empty($ownId)) {
            try {
                $model = app($ownType)::find($ownId);
                if ($model) {
                    return [
                        'id' => $model->id,
                        'name' => $model->name ?? null,
                        'type' => $this->mapOwnerTypeToKey($ownType),
                    ];
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }

        return null;
    }

    public function getOwnerAttribute()
    {
        $ownerType = $this->getAttribute('owner_type');
        $ownerId = $this->getAttribute('owner_id');
        if (!empty($ownerType) && !empty($ownerId)) {
            try {
                $model = app($ownerType)::find($ownerId);
                if ($model) {
                    return [
                        'id' => $model->id,
                        'name' => $model->name ?? null,
                        'type' => $this->mapOwnerTypeToKey($ownerType), // ← الجديد
                    ];
                }
            } catch (\Throwable $e) {
            }
        }
        return null;
    }
    private function mapOwnerTypeToKey(string $fqcn): string
    {
        // لو أنواع معروفة:
        if ($fqcn === \App\Models\Hub::class)
            return 'hub';
        if ($fqcn === \App\Models\Station::class)
            return 'station';
        if ($fqcn === \App\Models\Branch::class)
            return 'branch';

        // افتراضي: خُد اسم الكلاس وحوّله snake_case
        return Str::of(class_basename($fqcn))->snake()->toString();
    }


    public function getNdrStatusAttribute()
    {

        $rescheduleTypes = ['RESCHEDULE', 'RESCHEDULED', 'RE_SCHEDULE', 'RE-SCHEDULE'];
        $closeTypes = ['CLOSED', 'NDR_CLOSED', 'CANCELLED', 'CANCELED', 'RTO', 'RETURNED_TO_ORIGIN', 'RETURN_TO_ORIGIN'];

        $hist = $this->relationLoaded('shipmentHistories')
            ? $this->shipmentHistories
            : $this->shipmentHistories()->take(20)->get();

        $hasRescheduled = $hist->contains(function ($h) use ($rescheduleTypes) {
            return in_array(strtoupper((string) $h->type), $rescheduleTypes, true)
                || in_array(strtoupper((string) $h->name), $rescheduleTypes, true);
        });

        if ($hasRescheduled) {
            return 'Rescheduled';
        }

        $hasClosed = !$this->in_exception || $hist->contains(function ($h) use ($closeTypes) {
            return in_array(strtoupper((string) $h->type), $closeTypes, true)
                || in_array(strtoupper((string) $h->name), $closeTypes, true);
        });

        if ($hasClosed) {
            return 'Closed';
        }

        return 'Pending';
    }
    public function getAttemptDateAttribute()
    {
        $ex = $this->relationLoaded('core_exception')
            ? $this->core_exception
            : $this->core_exception()->first();

        if (!$ex) {
            return null;
        }

        // بنفضّل time لو موجود، غير كده updated_at
        return $ex->time ?: $ex->updated_at;
    }
    public function scopeApplyFilters(Builder $query, Request $request): void
    {
        $trackingNo = $request->query('query');
        $status = $request->query('status');
        $today = $request->query('today');
        $date = $request->query('date');
        $from = $request->query('from');
        $to = $request->query('to');
        $facilityId = $request->query('facility_id');
        $facilityType = $request->query('facility_type');
        $consigneeName = $request->query('consignee_name');
        $consigneeEmail = $request->query('consignee_email');
        $consigneePhone = $request->query('consignee_phone');
        $consigneeAltPhone = $request->query('consignee_alt_phone');
        $consigneeCountryId = $request->query('consignee_country_id');
        $consigneeGovernorateId = $request->query('consignee_governorate_id');
        $consigneeStateId = $request->query('consignee_state_id');
        $consigneePlaceId = $request->query('consignee_place_id');
        $senderName = $request->query('sender_name');
        $senderEmail = $request->query('sender_email');
        $senderPhone = $request->query('sender_phone');
        $senderAltPhone = $request->query('sender_alt_phone');
        $senderCountryId = $request->query('sender_country_id');
        $senderGovernorateId = $request->query('sender_governorate_id');
        $senderStateId = $request->query('sender_state_id');
        $senderPlaceId = $request->query('sender_place_id');

        $exceptionType = $request->query('exception_type');
        $exceptionFrom = $request->query('exception_from');
        $exceptionTo = $request->query('exception_to');
        $createdFrom = $request->query('created_from');
        $createdTo = $request->query('created_to');
        $deliveredFrom = $request->query('delivered_from');
        $deliveredTo = $request->query('delivered_to');
        $merchantId = $request->query('merchant_id');
        $pickupRef = $request->query('pickup_ref');
        $createdSource = $request->query('created_source');
        $isOutsourced = $request->query('is_outsourced');

        $shipperId = $request->query('shipper_id');

        $shipperEmail = $request->query('sender_email');

        if ($trackingNo) {
            if (is_array($trackingNo)) {
                $query->whereIn('tracking_no', $trackingNo);
            } else {
                $query->where('tracking_no', 'like', "%{$trackingNo}%");
            }
        }

        // Search by pickup task ref
        if ($pickupRef) {
            $query->whereHas('merchant_pickup_shipment.pickup_task', function ($sub) use ($pickupRef) {
                $sub->where('ref', 'like', "%{$pickupRef}%");
            });
        }

        if ($createdSource) {
            $query->where('created_source', $createdSource);
        }

        if ($isOutsourced !== null && $isOutsourced !== '') {
            $query->where('is_outsourced', filter_var($isOutsourced, FILTER_VALIDATE_BOOLEAN));
        }

        if ($facilityId && $facilityType) {
            $query->where('owner_id', $facilityId)
                ->where('owner_type', $facilityType);
        }

        if ($status) {
            switch ($status) {
                case 'in_exception':
                    $query->where('in_exception', true);
                    break;
                case 'OFD':
                    $query->where('status', 'OFD');
                    break;
                case 'DELIVERED':
                    $query->where('status', 'DELIVERED');
                    break;
                default:
                    $query->where('status', $status);
                    break;
            }
        }

        if ($today === "true") {
            $query->whereDate('created_at', Carbon::today());
        } elseif ($from && $to) {
            try {
                $fromDateTime = Carbon::parse($from);
                $toDateTime = Carbon::parse($to);
                if ($status === 'DELIVERED') {
                    $query->whereHas('shipmentHistories', function ($q) use ($fromDateTime, $toDateTime) {
                        $q->where('name', 'DELIVERED')
                            ->whereBetween('time', [$fromDateTime, $toDateTime]);
                    });
                } else {
                    $query->whereBetween('created_at', [$fromDateTime, $toDateTime]);
                }
            } catch (\Exception $e) {
                // ignore
            }
        } elseif ($date) {
            try {
                $selectedDate = Carbon::createFromFormat('Y-m-d', $date)->startOfDay();
                $query->whereDate('created_at', $selectedDate);
            } catch (\Exception $e) {
                // ignore
            }
        }

        if ($consigneeName) {
            $query->whereHas('consignee', fn($q) => $q->where('name', 'like', "%$consigneeName%"));
        }
        if ($consigneeEmail) {
            $query->whereHas('consignee', fn($q) => $q->where('email', 'like', "%$consigneeEmail%"));
        }
        if ($consigneePhone) {
            $query->whereHas('consignee', fn($q) => $q->where('cellphone', 'like', "%$consigneePhone%"));
        }
        if ($consigneeAltPhone) {
            $query->whereHas('consignee', fn($q) => $q->where('alternatePhone', 'like', "%$consigneeAltPhone%"));
        }

        if ($consigneeCountryId) {
            $query->whereHas('deliveryAddress', fn($q) => $q->where('country_id', $consigneeCountryId));
        }
        if ($consigneeGovernorateId) {
            $query->whereHas('deliveryAddress', fn($q) => $q->where('governorate_id', $consigneeGovernorateId));
        }
        if ($consigneeStateId) {
            $query->whereHas('deliveryAddress', fn($q) => $q->where('state_id', $consigneeStateId));
        }
        if ($consigneePlaceId) {
            $query->whereHas('deliveryAddress', fn($q) => $q->where('place_id', $consigneePlaceId));
        }

        // فلاتر المرسل (لسه في جدول shipments) زي ما هي
        if ($senderName) {
            $query->where('customer_name', 'like', "%$senderName%");
        }
        if ($senderEmail) {
            $query->whereHas('merchant', fn($q) => $q->where('email', 'like', "%$senderEmail%"));
        }
        if ($senderPhone) {
            $query->where('customer_phone', 'like', "%$senderPhone%");
        }
        if ($senderCountryId) {
            $query->where('sender_country_id', $senderCountryId);
        }
        if ($senderGovernorateId) {
            $query->where('sender_governorate_id', $senderGovernorateId);
        }
        if ($senderStateId) {
            $query->where('sender_state_id', $senderStateId);
        }
        if ($senderPlaceId) {
            $query->where('sender_place_id', $senderPlaceId);
        }

        if ($exceptionType) {
            $query->whereHas(
                'shipmentHistories',
                fn($q) => $q
                    ->where('type', $exceptionType)
                    ->where('name', 'DELIVERY_EXCEPTION')
            );
        }
        if ($exceptionFrom) {
            try {
                $exceptionFromDateTime = Carbon::parse($exceptionFrom);
                $query->whereHas(
                    'shipmentHistories',
                    fn($q) => $q
                        ->where('name', 'DELIVERY_EXCEPTION')
                        ->where('time', '>=', $exceptionFromDateTime)
                );
            } catch (\Exception $e) {
            }
        }
        if ($exceptionTo) {
            try {
                $exceptionToDateTime = Carbon::parse($exceptionTo);
                $query->whereHas(
                    'shipmentHistories',
                    fn($q) => $q
                        ->where('name', 'DELIVERY_EXCEPTION')
                        ->where('time', '<=', $exceptionToDateTime)
                );
            } catch (\Exception $e) {
            }
        }

        if ($createdFrom) {
            $createdFromDateTime = Carbon::parse($createdFrom);
            $query->where('created_at', '>=', $createdFromDateTime);
        }
        if ($createdTo) {
            try {
                $createdToDateTime = Carbon::parse($createdTo);
                $query->where('created_at', '<=', $createdToDateTime);
            } catch (\Exception $e) {
            }
        }
        if ($deliveredFrom) {
            try {
                $deliveredFromDateTime = Carbon::parse($deliveredFrom);
                $query->whereHas(
                    'shipmentHistories',
                    fn($q) => $q
                        ->where('name', 'DELIVERED')
                        ->where('time', '>=', $deliveredFromDateTime)
                );
            } catch (\Exception $e) {
            }
        }
        if ($deliveredTo) {
            try {
                $deliveredToDateTime = Carbon::parse($deliveredTo);
                $query->whereHas(
                    'shipmentHistories',
                    fn($q) => $q
                        ->where('name', 'DELIVERED')
                        ->where('time', '<=', $deliveredToDateTime)
                );
            } catch (\Exception $e) {
            }
        }
        // filter by merchant
        if ($merchantId) {
            $query->where('merchant_id', $merchantId);
        }
        // filter by shipper
        if ($shipperId) {
            $query->where('shipper_id', $shipperId);
        }
        if ($shipperEmail) {
            $query->where('shipper_email', $shipperEmail);
        }
    }
    public function zone()
    {
        $addr = $this->relationLoaded('deliveryAddress')
            ? $this->deliveryAddress
            : $this->deliveryAddress()->with(['state', 'governorate', 'place'])->first();

        if (!$addr) {
            if ($this->relationLoaded('consignee') ? $this->consignee : $this->consignee()->first()) {
                $c = $this->consignee;
                return (method_exists($c, 'zone')) ? $c->zone() : null;
            }
            return null;
        }

        if ($addr->state_id) {
            $mapped = \DB::table('state_zones as sz')
                ->join('zones as z', 'z.id', '=', 'sz.zone_id')
                ->where('sz.state_id', $addr->state_id)
                ->select('z.id', 'z.name', 'z.owner_id', 'z.owner_type')
                ->first();
            if ($mapped)
                return $mapped;
        }

        // 2) نقطة الإحداثيات من عنوان الأوردر
        if ($addr->latitude && $addr->longitude) {
            if ($z = $this->matchPointToZone((float) $addr->longitude, (float) $addr->latitude)) {
                return $z;
            }
        }

        // 3) place مرتبط بالعنوان
        if ($addr->relationLoaded('place') ? $addr->place : $addr->place()->first()) {
            $place = $addr->place;
            if ($place && $place->lng && $place->lat) {
                if ($z = $this->matchPointToZone((float) $place->lng, (float) $place->lat)) {
                    return $z;
                }
            }
        }

        // 4) state polygon/point من علاقات العنوان
        if ($addr->relationLoaded('state') ? $addr->state : $addr->state()->first()) {
            $state = $addr->state;
            if ($state) {
                if ($z = $this->matchPolygonToZone('states', $state->id, 'polygon'))
                    return $z;
                if ($state->lng && $state->lat) {
                    if ($z = $this->matchPointToZone((float) $state->lng, (float) $state->lat))
                        return $z;
                }
            }
        }

        // 5) governorate polygon/point من علاقات العنوان
        if ($addr->relationLoaded('governorate') ? $addr->governorate : $addr->governorate()->first()) {
            $gov = $addr->governorate;
            if ($gov) {
                if ($z = $this->matchPolygonToZone('governorates', $gov->id, 'polygon'))
                    return $z;
                if ($gov->lng && $gov->lat) {
                    if ($z = $this->matchPointToZone((float) $gov->lng, (float) $gov->lat))
                        return $z;
                }
            }
        }

        // 6) آخر حل: منطقة الـ consignee
        if ($this->relationLoaded('consignee') ? $this->consignee : $this->consignee()->first()) {
            $c = $this->consignee;
            return (method_exists($c, 'zone')) ? $c->zone() : null;
        }

        return null;
    }

    public function getBaseDeliveryFeeAttribute()
    {
        // $merchantId = $this->merchant_id ?? null;

        // // Try to get state_id from deliveryAddress relationship first
        // $stateId = null;
        // if ($this->relationLoaded('deliveryAddress') && $this->deliveryAddress) {
        //     $stateId = $this->deliveryAddress->state_id;
        // } elseif ($this->delivery_address_id) {
        //     // Load the relationship if not already loaded
        //     $this->load('deliveryAddress');
        //     $stateId = $this->deliveryAddress->state_id ?? null;
        // }

        // if (!$merchantId) {
        // Fallback to delivery_fee_before_discount or delivery_fee
        return $this->delivery_fee_before_discount ?? $this->delivery_fee_before_discount ?? null;
        // }

        // PRIORITY 1: Check for state-specific merchant commission
        // if ($stateId) {
        //     $cc = MerchantCommission::where('merchant_id', $merchantId)
        //         ->where('state_id', $stateId)
        //         ->first();

        //     if ($cc && $cc->base_delivery_fee !== null) {
        //         return $cc->base_delivery_fee;
        //     }
        // }

        // PRIORITY 2: Check for global merchant commission (state_id = NULL)
        // $globalCc = MerchantCommission::where('merchant_id', $merchantId)
        //     ->whereNull('state_id')
        //     ->first();

        // if ($globalCc && $globalCc->base_delivery_fee !== null) {
        //     return $globalCc->base_delivery_fee;
        // }

        // // PRIORITY 3: Fallback to delivery_fee_before_discount or delivery_fee
        // return $this->delivery_fee_before_discount ?? $this->delivery_fee_before_discount ?? null;
    }

    public function getBaseReturnFeeAttribute()
    {
        $merchantId = $this->merchant_id ?? null;

        $stateId = null;
        if ($this->relationLoaded('deliveryAddress') && $this->deliveryAddress) {
            $stateId = $this->deliveryAddress->state_id;
        } elseif ($this->delivery_address_id) {
            $this->load('deliveryAddress');
            $stateId = $this->deliveryAddress->state_id ?? null;
        }

        if (!$merchantId) {
            return $this->return_fee ?? null;
        }

        // PRIORITY 1: Check for state-specific merchant commission
        if ($stateId) {
            $cc = MerchantCommission::where('merchant_id', $merchantId)
                ->where('state_id', $stateId)
                ->first();

            if ($cc && $cc->base_return_fee !== null) {
                return $cc->base_return_fee;
            }
        }

        // PRIORITY 2: Check for global merchant commission (state_id = NULL)
        $globalCc = MerchantCommission::where('merchant_id', $merchantId)
            ->whereNull('state_id')
            ->first();

        if ($globalCc && $globalCc->base_return_fee !== null) {
            return $globalCc->base_return_fee;
        }

        // PRIORITY 3: Fallback to return_fee
        return $this->return_fee ?? null;
    }
    public function getPublicIdAttribute(): ?string
    {
        return $this->tracking_no ?: $this->pre_id;
    }

    public function scopeUnregistered($q)
    {
        return $q->whereNull('tracking_no')->whereNotNull('pre_id');
    }

    public function scopeNeedsWarehouseFinalization($q)
    {
        return $q->whereNull('tracking_no')->whereNotNull('pre_id');
    }

    /**
     * Sync the shipment row when a driver completes pickup.
     */
    public function markAsPicked(?int $driverUserId = null, string $merchantPickupStatus = MerchantPickupTaskStatusEnum::PICKED): void
    {
        $resolvedStatus = MerchantShipmentStatusMapper::toShipment($merchantPickupStatus);

        if (!$resolvedStatus) {
            $statusInfo = status(ShipmentStatusEnum::PICKED);
            $resolvedStatus = $statusInfo['name'] ?? ($statusInfo['label'] ?? ShipmentStatusEnum::PICKED);
        }

        $updates = ['status' => $resolvedStatus];

        if ($driverUserId) {
            $updates['driver_id'] = $driverUserId;
        }

        $this->fill($updates);

        if ($this->isDirty()) {
            $this->save();
        }
    }

    /**
     * Move CREATED shipments for a merchant to TO_PICKUP status and return them.
     */
    public static function moveCreatedShipmentsToPickupForMerchant(int $merchantId): \Illuminate\Support\Collection
    {
        $shipments = static::query()
            ->where('merchant_id', $merchantId)
            ->where('status', ShipmentStatusEnum::CREATED)
            ->select('id', 'tracking_no', 'pre_id')
            ->get();

        if ($shipments->isEmpty()) {
            return collect();
        }

        // Bulk update
        static::query()
            ->where('merchant_id', $merchantId)
            ->where('status', ShipmentStatusEnum::CREATED)
            ->update([
                'status' => ShipmentStatusEnum::TO_PICKUP,
                'updated_at' => now(),
            ]);

        // Bulk history insert
        $sortStatus = ShipmentStatusEnum::TO_PICKUP;
        $statusMeta = status($sortStatus);

        DB::table('shipment_histories')->insert(
            $shipments->map(fn($shipment) => [
                'shipment_id' => $shipment->id,
                'description' => $statusMeta['description'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ])->toArray()
        );

        return $shipments;
    }


    public function scopeFilterByFromCurrentFinal(Builder $query, Request $request): void
    {
        $normalizeType = fn($type) => $type ? str_replace('\\\\', '\\', $type) : null;
        $finalOwnerType = $normalizeType($request->query('final_owner_type'));
        $finalOwnerId = $normalizeType($request->query('final_owner_id'));
        $currentOwnerType = $normalizeType($request->query('current_owner_type'));
        $currentOwnerId = $normalizeType($request->query('current_owner_id'));
        $fromOwnerType = $normalizeType($request->query('from_owner_type'));
        $fromOwnerId = $normalizeType($request->query('from_owner_id'));
        if ($fromOwnerType && $fromOwnerId) {
            $query->where('from_owner_type', $fromOwnerType)->where('from_owner_id', $fromOwnerId);
        }
        if ($currentOwnerType && $currentOwnerId) {
            $query->where('current_owner_type', $currentOwnerType)->where('current_owner_id', $currentOwnerId);
        }
        if ($finalOwnerType && $finalOwnerId) {
            $query->where('final_owner_type', $finalOwnerType)->where('final_owner_id', $finalOwnerId);
        }
    }
}
