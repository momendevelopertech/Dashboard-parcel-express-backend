<?php

namespace App\Models;

use App\Observers\MerchantObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\SoftDeletes;


#[ObservedBy([MerchantObserver::class])]
class Merchant extends Model
{
    use HasFactory,SoftDeletes;

    protected $fillable = [
        'country_id',
        'governorate_id',
        'state_id',
        'place_id',
        'user_id',
        'address',
        'country_code',
        'contact_no',
        'currency',
        'facility_to_facility_fees',
        'lat',
        'lng',
        'is_guest',
        'image',
    ];

    protected $casts = [
        'image' => 'array',
    ];

    public function country()
    {
        return $this->belongsTo(Country::class);
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

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function settings()
    {
        return $this->hasOne(MerchantSetting::class);
    }

    public function commissions()
    {
        return $this->hasMany(MerchantCommission::class, 'merchant_id', 'user_id');
    }

    public function owner()
    {
        return $this->morphTo();
    }
  

    public function merchant_pickup_shipments()
    {
        return $this->hasMany(MerchantPickupShipment::class, 'merchant_id');
    }

    public function merchantPickupTasks()
    {
        return $this->hasMany(MerchantPickupTask::class, 'merchant_id', 'user_id');
    }

    public function getTotalRegisteredShipmentsNoAttribute(): int
    {
        return $this->merchantPickupTasks()->get()->sum(function ($task) {
            return $task->registered_shipments_no;
        });
    }

    public function getTotalExtraShipmentsNoAttribute(): int
    {
        return $this->merchantPickupTasks()->sum('extra_shipments_no');
    }

    public function getTotalStatesCountAttribute(): int
    {
        // Get all shipment state_ids through pickup shipments of this merchant's pickup tasks
        $pickupTaskIds = $this->merchantPickupTasks()->pluck('id');
        $stateIds = \App\Models\MerchantPickupShipment::whereIn('pickup_task_id', $pickupTaskIds)
            ->with('shipment')
            ->get()
            ->pluck('shipment.state_id')
            ->filter()
            ->unique();
        return $stateIds->count();
    }

    public function scopeByOwner($builder)
    {
        $user = Auth::user();

        if ($user) {
            // Apply scope based on branch
            if ($user->branch_user && $branch_id = $user->branch_user->branch_id) {
                $builder->where(function ($query) use ($branch_id) {
                    $query->where('owner_id', $branch_id)
                        ->where('owner_type', Branch::class);
                });
            }

            // Apply scope based on station
            if ($user->station_user && $station_id = $user->station_user->station_id) {
                $builder->orWhere(function ($query) use ($station_id) {
                    $query->where('owner_id', $station_id)
                        ->where('owner_type', Station::class);
                });
            }

            // Apply scope based on hub
            if ($user->hub_user && $hub_id = $user->hub_user->hub_id) {
                $builder->orWhere(function ($query) use ($hub_id) {
                    $query->where('owner_id', $hub_id)
                        ->where('owner_type', Hub::class);
                });
            }
        }

        return $builder;
    }

    /**
     * Get the current credits (balance) for this merchant.
     * Calculates balance from delivered shipments: COD - fees - settlements
     * Uses the same calculation logic as MerchantAccountController::buildMerchantAccountPayload
     *
     * @return float
     */
    public function getCurrentCreditsAttribute(): float
    {
        if (!$this->user_id) {
            return 0.0;
        }

        $merchantId = $this->user_id;
        $from = Carbon::parse('2000-01-01')->startOfDay();
        $to = now()->endOfDay();

        $hasShipmentStatus = Schema::hasColumn('shipments', 'status');
        $hasDA = Schema::hasTable('driver_shipment_assignments')
            && Schema::hasColumn('driver_shipment_assignments', 'delivered_at')
            && Schema::hasColumn('driver_shipment_assignments', 'status')
            && Schema::hasColumn('driver_shipment_assignments', 'shipment_tracking_no');

        $deliveryFeeExpr = "COALESCE(o.delivery_fee,0)";
        $returnFeeExpr = "CASE WHEN COALESCE(o.is_return, 0) = 1 THEN COALESCE(cc.return_fee, 0) ELSE 0 END";

        // COD for merchant: Only COD shipments contribute, uses value field (goods value only)
        $codExpr = "CASE
            WHEN UPPER(o.payment_type)='COD' THEN COALESCE(o.value, 0)
            ELSE 0
        END";

        $shipmentsQ = DB::table('shipments as o')
            ->where(function ($q) use ($merchantId) {
                $q->where('o.merchant_id', $merchantId)
                    ->orWhere('o.shipper_id', $merchantId);
            });

        if ($hasShipmentStatus) {
            $shipmentsQ->whereRaw("UPPER(TRIM(o.status)) = 'DELIVERED'");
        }

        $dateCol = 'o.created_at';
        if ($hasDA) {
            $deliveredSub = DB::table('driver_shipment_assignments')
                ->select('shipment_tracking_no', DB::raw('MAX(delivered_at) as delivered_at'))
                ->whereRaw("UPPER(TRIM(status)) = 'DELIVERED'")
                ->groupBy('shipment_tracking_no');

            $shipmentsQ->joinSub($deliveredSub, 'da', function ($j) {
                $j->on('da.shipment_tracking_no', '=', 'o.tracking_no');
            });

            $dateCol = 'da.delivered_at';
        }

        $shipmentsQ->leftJoin('addresses as addr', 'addr.id', '=', 'o.delivery_address_id');
        $shipmentsQ->join('merchant_commission_transactions as cc', 'cc.shipment_id', '=', 'o.id');

        $shipmentsQ->whereBetween($dateCol, [$from, $to]);

        $baseFeeExpr = "COALESCE(cc.base_delivery_fee, 0)";
        $discountExpr = "COALESCE(cc.delivery_discount_amount, 0)";
        $effectiveExpr = "COALESCE(cc.delivery_fee, GREATEST($baseFeeExpr - $discountExpr, 0))";
        $feeOnMerchantExpr = "CASE WHEN LOWER(o.fee_payer)='merchant' THEN $effectiveExpr ELSE 0 END";
        $feeCreditExpr = "CASE WHEN LOWER(o.fee_payer) <> 'merchant' THEN GREATEST($baseFeeExpr - $effectiveExpr, 0) ELSE 0 END";

        $shipments = $shipmentsQ->select([
            DB::raw("MAX($dateCol) as row_date"),
            'o.tracking_no',
            DB::raw("MAX($codExpr) as cod_for_merchant"),
            DB::raw("MAX($feeOnMerchantExpr) as fee_on_merchant"),
            DB::raw("MAX($feeCreditExpr) as fee_credit"),
            DB::raw("MAX(CASE WHEN LOWER(o.fee_payer)='merchant' THEN $returnFeeExpr ELSE 0 END) as return_fee_on_merchant"),
        ])
            ->groupBy('o.tracking_no')
            ->get();

        $totalCOD = (float) $shipments->sum('cod_for_merchant');
        $totalDeliveryFeesOnMerchant = (float) $shipments->sum('fee_on_merchant');
        $totalReturnFeesOnMerchant = (float) $shipments->sum('return_fee_on_merchant');
        $totalFeesOnMerchant = $totalDeliveryFeesOnMerchant + $totalReturnFeesOnMerchant;
        $totalFeeCredits = (float) $shipments->sum('fee_credit');
        $netFees = $totalFeesOnMerchant - $totalFeeCredits;

        $totalSettlements = (float) DB::table('merchant_settlements')
            ->where('merchant_id', $merchantId)
            ->whereBetween('created_at', [$from, $to])
            ->sum('amount');

        $currentBalance = $totalCOD - $netFees - $totalSettlements;

        return (float) $currentBalance;
    }

    /**
     * Get the parcel value (total COD sum) for this merchant.
     * Sums total_cod from all shipments for this merchant
     *
     * @return float
     */
    public function getParcelValueAttribute(): float
    {
        if (!$this->user_id) {
            return 0.0;
        }

        $merchantId = $this->user_id;

        $totalParcelValue = (float) DB::table('shipments')
            ->where(function ($q) use ($merchantId) {
                $q->where('merchant_id', $merchantId)
                    ->orWhere('shipper_id', $merchantId);
            })
            ->sum('total_cod');

        return (float) $totalParcelValue;
    }
}
