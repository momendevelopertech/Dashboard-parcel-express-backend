<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class FinanceTopPerformersController extends Controller
{
    /**
     * Get top performing drivers and merchants by delivered shipments count
     */
    public function index(Request $request)
    {
        $request->validate([
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
            'limit' => 'nullable|integer|min:1|max:50',
        ]);

        $from = $request->input('from');
        $to = $request->input('to');
        $limit = $request->input('limit', 5);

        $topDrivers = $this->getTopDrivers($from, $to, $limit);
        $topMerchants = $this->getTopMerchants($from, $to, $limit);

        return response()->json([
            'success' => true,
            'data' => [
                'drivers' => $topDrivers,
                'merchants' => $topMerchants,
            ],
        ]);
    }

    /**
     * Get top drivers by delivered shipments count
     */
    private function getTopDrivers($from = null, $to = null, $limit = 5)
    {
        $query = DB::table('shipments')
            ->join('users', 'shipments.driver_id', '=', 'users.id')
            ->select(
                'shipments.driver_id as id',
                'users.name',
                'users.phone',
                DB::raw("COUNT(CASE WHEN shipments.status = 'delivered' THEN 1 END) as delivered_count"),
                DB::raw("COUNT(CASE WHEN shipments.status = 'picked_up' OR shipments.pickup_driver_id = shipments.driver_id THEN 1 END) as pickup_count"),
                DB::raw("COUNT(*) as total_shipments")
            )
            ->whereNotNull('shipments.driver_id')
            ->whereNull('shipments.deleted_at');

        // Apply date filter on delivered_at or updated_at for delivered shipments
        if ($from) {
            $query->where(function ($q) use ($from) {
                $q->where('shipments.delivered_at', '>=', Carbon::parse($from)->startOfDay())
                  ->orWhere(function ($q2) use ($from) {
                      $q2->whereNull('shipments.delivered_at')
                         ->where('shipments.updated_at', '>=', Carbon::parse($from)->startOfDay());
                  });
            });
        }

        if ($to) {
            $query->where(function ($q) use ($to) {
                $q->where('shipments.delivered_at', '<=', Carbon::parse($to)->endOfDay())
                  ->orWhere(function ($q2) use ($to) {
                      $q2->whereNull('shipments.delivered_at')
                         ->where('shipments.updated_at', '<=', Carbon::parse($to)->endOfDay());
                  });
            });
        }

        $drivers = $query
            ->groupBy('shipments.driver_id', 'users.name', 'users.phone')
            ->orderByDesc('delivered_count')
            ->limit($limit)
            ->get();

        return $drivers->map(function ($driver) {
            // Try to get workspace info
            $workspace = DB::table('users')
                ->where('id', $driver->id)
                ->select('owner_id', 'owner_type')
                ->first();

            $workspaceName = '';
            if ($workspace && $workspace->owner_id && $workspace->owner_type) {
                $type = class_basename($workspace->owner_type);
                $tableName = strtolower($type) . 's';
                $ws = DB::table($tableName)->where('id', $workspace->owner_id)->first();
                $workspaceName = $ws->name ?? '';
            }

            return [
                'id' => $driver->id,
                'name' => $driver->name ?? 'Unknown',
                'phone' => $driver->phone ?? '',
                'workspace' => $workspaceName,
                'delivered_count' => (int) $driver->delivered_count,
                'pickup_count' => (int) $driver->pickup_count,
                'total_shipments' => (int) $driver->total_shipments,
            ];
        });
    }

    /**
     * Get top merchants by delivered shipments count
     */
    private function getTopMerchants($from = null, $to = null, $limit = 5)
    {
        $query = DB::table('shipments')
            ->join('users', 'shipments.merchant_id', '=', 'users.id')
            ->leftJoin('merchants', 'users.id', '=', 'merchants.user_id')
            ->leftJoin('companies', 'merchants.company_id', '=', 'companies.id')
            ->select(
                'shipments.merchant_id as id',
                'users.name',
                'companies.name as company',
                DB::raw("COUNT(CASE WHEN shipments.status = 'delivered' THEN 1 END) as delivered_count"),
                DB::raw("COUNT(CASE WHEN shipments.status IN ('pending', 'processing', 'in_transit', 'out_for_delivery') THEN 1 END) as pending_count"),
                DB::raw("COUNT(*) as total_shipments")
            )
            ->whereNotNull('shipments.merchant_id')
            ->whereNull('shipments.deleted_at');

        // Apply date filter
        if ($from) {
            $query->where(function ($q) use ($from) {
                $q->where('shipments.delivered_at', '>=', Carbon::parse($from)->startOfDay())
                  ->orWhere(function ($q2) use ($from) {
                      $q2->whereNull('shipments.delivered_at')
                         ->where('shipments.created_at', '>=', Carbon::parse($from)->startOfDay());
                  });
            });
        }

        if ($to) {
            $query->where(function ($q) use ($to) {
                $q->where('shipments.delivered_at', '<=', Carbon::parse($to)->endOfDay())
                  ->orWhere(function ($q2) use ($to) {
                      $q2->whereNull('shipments.delivered_at')
                         ->where('shipments.created_at', '<=', Carbon::parse($to)->endOfDay());
                  });
            });
        }

        $merchants = $query
            ->groupBy('shipments.merchant_id', 'users.name', 'companies.name')
            ->orderByDesc('delivered_count')
            ->limit($limit)
            ->get();

        return $merchants->map(function ($merchant) {
            return [
                'id' => $merchant->id,
                'name' => $merchant->name ?? 'Unknown',
                'company' => $merchant->company ?? '',
                'delivered_count' => (int) $merchant->delivered_count,
                'pending_count' => (int) $merchant->pending_count,
                'total_shipments' => (int) $merchant->total_shipments,
            ];
        });
    }
}

