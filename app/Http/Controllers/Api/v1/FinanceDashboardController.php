<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\MerchantTransaction;
use App\Models\Shipment;
use Carbon\Carbon;

class FinanceDashboardController extends Controller
{
    /**
     * Get aggregated financial dashboard data
     */
    public function index(Request $request)
    {
        $request->validate([
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
        ]);

        $from = $request->input('from') 
            ? Carbon::parse($request->input('from'))->startOfDay() 
            : now()->startOfMonth();
        $to = $request->input('to') 
            ? Carbon::parse($request->input('to'))->endOfDay() 
            : now()->endOfDay();

        // Get driver account summaries
        $driverStats = $this->getDriverStats($from, $to);

        // Get merchant account summaries
        $merchantStats = $this->getMerchantStats($from, $to);

        // Get workspace summaries
        $workspaceStats = $this->getWorkspaceStats($from, $to);

        // Get recent transactions
        $recentTransactions = $this->getRecentTransactions();

        // Get collection stats
        $collectionStats = $this->getCollectionStats($from, $to);

        return response()->json([
            'success' => true,
            'data' => [
                'summary' => [
                    'total_driver_balance' => $driverStats['total_balance'] ?? 0,
                    'total_merchant_balance' => $merchantStats['total_balance'] ?? 0,
                    'total_workspace_balance' => $workspaceStats['total_balance'] ?? 0,
                    'pending_cod_collections' => $collectionStats['cod_pending_amount'] ?? 0,
                    'pending_pickup_collections' => $collectionStats['pickup_pending_amount'] ?? 0,
                    'total_settlements_today' => $merchantStats['settlements_today'] ?? 0,
                    'net_financial_position' => ($driverStats['total_balance'] ?? 0) + 
                                               ($merchantStats['total_balance'] ?? 0) + 
                                               ($workspaceStats['total_balance'] ?? 0),
                ],
                'drivers' => $driverStats,
                'merchants' => $merchantStats,
                'workspaces' => $workspaceStats,
                'recent_transactions' => $recentTransactions,
                'collection_stats' => $collectionStats,
            ],
        ]);
    }

    /**
     * Get accounts summary for quick cards
     */
    public function accountsSummary(Request $request)
    {
        $driverStats = $this->getDriverStats();
        $merchantStats = $this->getMerchantStats();
        $workspaceStats = $this->getWorkspaceStats();

        return response()->json([
            'success' => true,
            'data' => [
                'drivers' => [
                    'total_balance' => $driverStats['total_balance'] ?? 0,
                    'count' => $driverStats['total_count'] ?? 0,
                    'positive_count' => $driverStats['positive_balance_count'] ?? 0,
                    'negative_count' => $driverStats['negative_balance_count'] ?? 0,
                ],
                'merchants' => [
                    'total_balance' => $merchantStats['total_balance'] ?? 0,
                    'count' => $merchantStats['total_count'] ?? 0,
                    'pending_settlements' => $merchantStats['pending_settlements'] ?? 0,
                ],
                'workspaces' => [
                    'total_balance' => $workspaceStats['total_balance'] ?? 0,
                    'count' => $workspaceStats['total_count'] ?? 0,
                    'by_type' => $workspaceStats['by_type'] ?? [],
                ],
            ],
        ]);
    }

    /**
     * Get driver account statistics
     */
    private function getDriverStats($from = null, $to = null)
    {
        // Try to get stats from driver_transactions or driver_accounts table
        $query = DB::table('driver_transactions')
            ->whereNull('deleted_at')
            ->where('status', 'completed');

        if ($from && $to) {
            $query->whereBetween('created_at', [$from, $to]);
        }

        $stats = $query->selectRaw("
            COUNT(DISTINCT driver_id) as total_count,
            SUM(amount) as total_balance
        ")->first();

        // Get positive/negative balance counts
        $balanceStats = DB::table('driver_transactions')
            ->select('driver_id')
            ->whereNull('deleted_at')
            ->where('status', 'completed')
            ->groupBy('driver_id')
            ->get()
            ->map(function ($row) {
                $balance = DB::table('driver_transactions')
                    ->where('driver_id', $row->driver_id)
                    ->whereNull('deleted_at')
                    ->where('status', 'completed')
                    ->sum('amount');
                return (object) ['driver_id' => $row->driver_id, 'balance' => $balance];
            });

        $positiveCount = $balanceStats->where('balance', '>', 0)->count();
        $negativeCount = $balanceStats->where('balance', '<', 0)->count();

        return [
            'total_count' => (int) ($stats->total_count ?? 0),
            'total_balance' => (float) ($stats->total_balance ?? 0),
            'positive_balance_count' => $positiveCount,
            'negative_balance_count' => $negativeCount,
        ];
    }

    /**
     * Get merchant account statistics
     */
    private function getMerchantStats($from = null, $to = null)
    {
        $query = DB::table('merchant_transactions')
            ->whereNull('deleted_at')
            ->where('status', 'completed');

        if ($from && $to) {
            $query->whereBetween('created_at', [$from, $to]);
        }

        $stats = $query->selectRaw("
            COUNT(DISTINCT merchant_id) as total_count,
            SUM(amount) as total_balance,
            SUM(CASE WHEN type = 'settlement' THEN ABS(amount) ELSE 0 END) as total_settlements
        ")->first();

        // Get today's settlements
        $settlementsToday = DB::table('merchant_transactions')
            ->whereNull('deleted_at')
            ->where('status', 'completed')
            ->where('type', 'settlement')
            ->whereDate('created_at', today())
            ->sum(DB::raw('ABS(amount)'));

        // Get pending settlements (merchants with positive balance)
        $pendingSettlements = DB::table('merchant_transactions')
            ->select('merchant_id')
            ->whereNull('deleted_at')
            ->where('status', 'completed')
            ->groupBy('merchant_id')
            ->havingRaw('SUM(amount) > 0')
            ->get()
            ->sum(function ($row) {
                return DB::table('merchant_transactions')
                    ->where('merchant_id', $row->merchant_id)
                    ->whereNull('deleted_at')
                    ->where('status', 'completed')
                    ->sum('amount');
            });

        return [
            'total_count' => (int) ($stats->total_count ?? 0),
            'total_balance' => (float) ($stats->total_balance ?? 0),
            'total_settlements' => (float) ($stats->total_settlements ?? 0),
            'settlements_today' => (float) $settlementsToday,
            'pending_settlements' => (float) $pendingSettlements,
        ];
    }

    /**
     * Get workspace account statistics
     */
    private function getWorkspaceStats($from = null, $to = null)
    {
        // This depends on your workspace transaction structure
        // Assuming there's a workspace_transactions or similar table
        $totalBalance = 0;
        $totalCount = 0;
        $byType = [
            'hub' => 0,
            'station' => 0,
            'branch' => 0,
        ];

        // Try to get from hubs
        $hubs = DB::table('hubs')->count();
        $stations = DB::table('stations')->count();
        $branches = DB::table('branches')->count();

        $totalCount = $hubs + $stations + $branches;

        return [
            'total_balance' => $totalBalance,
            'total_count' => $totalCount,
            'by_type' => $byType,
        ];
    }

    /**
     * Get recent transactions across all account types
     */
    private function getRecentTransactions($limit = 10)
    {
        $merchantTransactions = DB::table('merchant_transactions')
            ->join('users', 'merchant_transactions.merchant_id', '=', 'users.id')
            ->select(
                'merchant_transactions.id',
                'merchant_transactions.type',
                DB::raw("'merchant' as entity_type"),
                'users.name as entity_name',
                'merchant_transactions.amount',
                'merchant_transactions.created_at'
            )
            ->whereNull('merchant_transactions.deleted_at')
            ->where('merchant_transactions.status', 'completed')
            ->orderByDesc('merchant_transactions.created_at')
            ->limit($limit)
            ->get();

        $driverTransactions = DB::table('driver_transactions')
            ->join('users', 'driver_transactions.driver_id', '=', 'users.id')
            ->select(
                'driver_transactions.id',
                'driver_transactions.type',
                DB::raw("'driver' as entity_type"),
                'users.name as entity_name',
                'driver_transactions.amount',
                'driver_transactions.created_at'
            )
            ->whereNull('driver_transactions.deleted_at')
            ->orderByDesc('driver_transactions.created_at')
            ->limit($limit)
            ->get();

        return $merchantTransactions->merge($driverTransactions)
            ->sortByDesc('created_at')
            ->take($limit)
            ->values()
            ->map(function ($t) {
                return [
                    'id' => $t->id,
                    'type' => $t->type,
                    'entity_type' => $t->entity_type,
                    'entity_name' => $t->entity_name,
                    'amount' => (float) $t->amount,
                    'created_at' => $t->created_at,
                ];
            });
    }

    /**
     * Get collection statistics
     */
    private function getCollectionStats($from = null, $to = null)
    {
        $query = Shipment::query();

        if ($from && $to) {
            $query->whereBetween('created_at', [$from, $to]);
        }

        // COD stats
        $codPending = (clone $query)
            ->where('payment_type', 'COD')
            ->whereNotIn('status', ['delivered', 'returned', 'cancelled'])
            ->count();

        $codCompleted = (clone $query)
            ->where('payment_type', 'COD')
            ->where('status', 'delivered')
            ->count();

        $codPendingAmount = (clone $query)
            ->where('payment_type', 'COD')
            ->whereNotIn('status', ['delivered', 'returned', 'cancelled'])
            ->sum('value');

        // Pickup stats
        $pickupPending = DB::table('merchant_pickup_tasks')
            ->whereIn('status', ['pending', 'assigned'])
            ->when($from && $to, fn($q) => $q->whereBetween('created_at', [$from, $to]))
            ->count();

        $pickupCompleted = DB::table('merchant_pickup_tasks')
            ->where('status', 'completed')
            ->when($from && $to, fn($q) => $q->whereBetween('created_at', [$from, $to]))
            ->count();

        return [
            'cod_pending' => $codPending,
            'cod_completed' => $codCompleted,
            'cod_pending_amount' => (float) $codPendingAmount,
            'pickup_pending' => $pickupPending,
            'pickup_completed' => $pickupCompleted,
            'pickup_pending_amount' => 0, // Calculate if needed
        ];
    }
}

