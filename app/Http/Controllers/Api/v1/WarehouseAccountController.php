<?php

namespace App\Http\Controllers\Api\v1;
use App\Http\Controllers\Controller;


use Illuminate\Http\Request;
use App\Models\WarehouseTransaction;
use App\Models\Station;
use App\Models\Hub;
use App\Models\TransferTaskShipment;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class WarehouseAccountController extends Controller
{
    public function showHub(Request $r, Hub $hub)
    {
        [$from, $to] = $this->range($r);

        $q = WarehouseTransaction::query()
            ->where('warehouse_type', Hub::class)
            ->where('warehouse_id', $hub->id)
            ->whereBetween('created_at', [$from, $to]);

        $summary = $this->buildSummary(clone $q);
        $rows = $this->rows(clone $q);

        return sendResponse('Hub account', [
            'accountable' => $hub->only(['id', 'name']),
            'summary' => $summary,
            'transactions' => $rows,
        ], []);
    }

    public function showStation(Request $r, Station $station)
    {
        [$from, $to] = $this->range($r);

        $q = WarehouseTransaction::query()
            ->where('warehouse_type', Station::class)
            ->where('warehouse_id', $station->id)
            ->whereBetween('created_at', [$from, $to]);

        $summary = $this->buildSummary(clone $q);
        $rows = $this->rows(clone $q);

        return sendResponse('Station account', [
            'accountable' => $station->only(['id', 'name']),
            'summary' => $summary,
            'transactions' => $rows,
        ], []);
    }

    private function range(Request $r): array
    {
        $from = Carbon::parse($r->input('from', now()->startOfMonth()))->startOfDay();
        $to = Carbon::parse($r->input('to', now()))->endOfDay();
        return [$from, $to];
    }

    private function buildSummary($q): array
    {
        // الأنواع المتوقّعة من الجدول:
        // cash_in  | cash_out | expense | ibt_in | ibt_out
        $agg = $q->selectRaw("
            SUM(CASE WHEN type='cash_in'  THEN amount ELSE 0 END) AS cash_in,
            SUM(CASE WHEN type='cash_out' THEN amount ELSE 0 END) AS cash_out,
            SUM(CASE WHEN type='expense'  THEN amount ELSE 0 END) AS expenses,
            SUM(CASE WHEN type='ibt_in'   THEN amount ELSE 0 END) AS ibt_in,
            SUM(CASE WHEN type='ibt_out'  THEN amount ELSE 0 END) AS ibt_out
        ")->first();

        $cashIn = (float) $agg->cash_in;
        $cashOut = (float) $agg->cash_out;
        $exp = (float) $agg->expenses;

        return [
            'cash_in' => $cashIn,
            'cash_out' => $cashOut,
            'expenses' => $exp,
            'net_balance' => $cashIn - $cashOut - $exp,
            'ibt' => [
                'in' => (float) $agg->ibt_in,
                'out' => (float) $agg->ibt_out,
            ],
        ];
    }

    // private function rows($q): array
    // {
    //     $rows = $q->orderBy('created_at', 'desc')->get();

    //     $userNames = DB::table('users')
    //         ->whereIn('id', $rows->pluck('created_by')->filter()->unique()->values())
    //         ->pluck('name', 'id');

    //     return $rows->map(function ($r) use ($userNames) {
    //         return [
    //             'date' => $r->created_at,
    //             'reference' => $r->reference,
    //             'type' => $r->type,
    //             'description' => $r->description,
    //             'amount' => (float) $r->amount,
    //             'user_name' => $userNames[$r->created_by] ?? '-',

    //             // ✅ الجديد: ضيف التتبع لو متاح
    //             'shipment_tracking_no' => $r->shipment_tracking_no
    //                 ?? optional($r->shipment)->tracking_no
    //                 ?? null,
    //         ];
    //     })->all();
    // }


    private function rows($q): array
    {
        $rows = $q->orderBy('created_at', 'desc')->get();

        $userNames = DB::table('users')
            ->whereIn('id', $rows->pluck('created_by')->filter()->unique()->values())
            ->pluck('name', 'id');

        // استخرج كل أرقام الـ runsheet من المراجع COD-RS-{id} أو من الوصف
        $rsIds = [];
        foreach ($rows as $r) {
            $ref = (string) ($r->reference ?? '');
            $desc = (string) ($r->description ?? '');
            $idFromRef = null;
            if (str_starts_with($ref, 'COD-RS-')) {
                $idFromRef = (int) str_replace('COD-RS-', '', $ref);
            } elseif (preg_match('/runsheet\s*#(\d+)/i', $desc, $m)) {
                $idFromRef = (int) $m[1];
            }
            if ($idFromRef)
                $rsIds[] = $idFromRef;
        }
        $rsIds = array_values(array_unique(array_filter($rsIds)));

        // هات الأوردارات المسلّمة لكل runsheet (لعدّ التحويلات فقط)
        $deliveredCounts = [];
        if (!empty($rsIds)) {
            $rsWithShipments = \App\Models\DriverRunsheet::query()
                ->whereIn('id', $rsIds)
                ->with(['delivered_shipments.shipment.shipment_delivery'])
                ->get()
                ->keyBy('id');

            foreach ($rsWithShipments as $rsId => $rs) {
                $count = 0;
                foreach ($rs->delivered_shipments as $ro) {
                    $od = $ro->shipment?->shipment_delivery;
                    $collected = (float) (($od->payment_cash ?? 0) + ($od->payment_bank_transfer ?? 0));
                    if ($collected > 0)
                        $count++;
                }
                $deliveredCounts[$rsId] = $count;
            }
        }

        return $rows->map(function ($r) use ($userNames, $deliveredCounts) {
            $row = [
                'date' => $r->created_at,
                'type' => $r->type,
                'description' => $r->description,
                'amount' => (float) $r->amount,
                'fee' => isset($r->fee) ? (float) $r->fee : null,
                'cod' => isset($r->cod) ? (float) $r->cod : null,
                'user_name' => $userNames[$r->created_by] ?? '-',
                // لو الصف مربوط بأوردر واحد ساعتها ممكن نبعته:
                'shipment_tracking_no' => $r->shipment_tracking_no ?? optional($r->shipment)->tracking_no ?? null,
            ];

            // runsheet_id detection
            $runsheetId = null;
            $ref = (string) ($r->reference ?? '');
            $desc = (string) ($r->description ?? '');
            if (str_starts_with($ref, 'COD-RS-')) {
                $runsheetId = (int) str_replace('COD-RS-', '', $ref);
            } elseif (preg_match('/runsheet\s*#(\d+)/i', $desc, $m)) {
                $runsheetId = (int) $m[1];
            }

            if ($runsheetId) {
                $row['runsheet_id'] = $runsheetId;
                $row['transferables_count'] = $deliveredCounts[$runsheetId] ?? 0;
            } else {
                $row['transferables_count'] = 0;
            }

            return $row;
        })->all();
    }


    // نفس الـhelper اللي عندك مع fallback للـtransfer task
    private function resolveShipmentDestination(\App\Models\Shipment $shipment): array
    {
        if ($shipment->origin_owner_type && $shipment->origin_owner_id) {
            return [$shipment->origin_owner_type, (int) $shipment->origin_owner_id];
        }

        $task = \App\Models\TransferTaskShipment::query()
            ->where('shipment_tracking_no', $shipment->tracking_no)
            ->latest('id')
            ->first();

        if ($task) {
            $currentType = auth()->user()->owner_type;
            $currentId = auth()->user()->owner_id;
            if ($task->to_type === $currentType && (int) $task->to_id === (int) $currentId) {
                return [$task->from_type, (int) $task->from_id];
            }
            return [$task->to_type, (int) $task->to_id];
        }

        return [null, null];
    }


    public function show(Request $r, string $type, int $id)
    {
        [$wType, $model] = $this->resolveWarehouse(type: $type);

        $from = $r->date('from')?->startOfDay() ?? now()->startOfMonth();
        $to = $r->date('to')?->endOfDay() ?? now()->endOfDay();

        $accountable = $model::findOrFail($id);

        $base = WarehouseTransaction::query()
            ->where('warehouse_type', $wType)
            ->where('warehouse_id', $id)
            ->whereBetween('created_at', [$from, $to]);

        // KPIs
        $cashIn = (float) (clone $base)->where('type', 'cash_in')->sum('amount');
        $cashOut = (float) (clone $base)->where('type', 'cash_out')->sum('amount');
        $expenses = (float) (clone $base)->whereIn('type', ['expense', 'opex'])->sum('amount');

        $trIn = (float) (clone $base)->where('type', 'transfer_in')->sum('amount');
        $trOut = (float) (clone $base)->where('type', 'transfer_out')->sum('amount');

        $net = ($cashIn + $trIn) - ($cashOut + $expenses + $trOut);

        // Transactions (للعرض في الجدول)
        $rows = $base->clone()
            ->leftJoin('users', 'users.id', '=', 'warehouse_transactions.created_by')
            ->orderBy('created_at', 'desc')
            ->get([
                'warehouse_transactions.created_at as date',
                'warehouse_transactions.reference',
                'warehouse_transactions.type',
                'warehouse_transactions.description',
                'warehouse_transactions.amount',
                DB::raw('COALESCE(users.name, "-") as user_name'),
            ]);

        return sendResponse('Warehouse account retrieved.', [
            'accountable' => $accountable->only(['id', 'name']),
            'summary' => [
                'cash_in' => $cashIn,
                'cash_out' => $cashOut,
                'expenses' => $expenses,
                'ibt' => ['in' => $trIn, 'out' => $trOut],
                'net_balance' => $net,
            ],
            'transactions' => $rows,
        ]);
    }

    private function resolveWarehouse(string $type): array
    {
        $type = strtolower($type);
        if ($type === 'station')
            return [Station::class, Station::class];
        if ($type === 'hub')
            return [Hub::class, Hub::class];
        abort(404, 'Unknown warehouse type.');
    }
}
