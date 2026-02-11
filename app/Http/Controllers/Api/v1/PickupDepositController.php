<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\PickuptaskTransaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\WarehouseTransaction;

class PickupDepositController extends Controller
{
    public function index(Request $request, $status = 'pending', $driver_id = null)
    {
        $ownerDriverIds = User::byOwner()->pluck('id');

        $query = PickuptaskTransaction::query()
            ->where('status', $status)
            ->whereHas('pickuptask', function ($q) use ($ownerDriverIds) {
                $q->whereIn('driver_id', $ownerDriverIds);
            });

        // ✅ Driver filter (from route OR request)
        $selectedDriverId = $request->input('driver_id') ?? $driver_id;

        if (!empty($selectedDriverId)) {
            $query->whereHas('pickuptask', function ($q) use ($selectedDriverId) {
                $q->where('driver_id', (int) $selectedDriverId);
            });
        }

        // ✅ Company filter
        if ($request->filled('company_id')) {
            $company_id = $request->company_id;

            $query->whereHas('pickuptask.driver', function ($q) use ($company_id) {
                $q->where('company_id', $company_id);
            });
        }

        // ✅ from filter
        if ($request->has('from')) {
            $query->where('created_at', ">=", $request->from);
        }

        // ✅ to filter
        if ($request->has('to')) {
            $query->where('created_at', "<=", $request->to);
        }
        // Pagination
        $pickupdeposits = $query
            ->with([
                'pickuptask:id,ref'
            ])
            ->orderByDesc('id')
            ->paginate(
                $request->input('per_page', 8),
                ['*'],
                'page',
                $request->input('page', 1)
            );
        return sendResponse(
            'Pickup deposits retrieved successfully.',
            [
                'data' => $pickupdeposits,
            ]
        );
    }


    public function updateStatus(Request $request)
    {
        try {

            $validated = $request->validate([
                'id'     => 'required|exists:pickuptask_transactions,id',
                'status' => 'required|in:pending,hold,completed',
                'paid_by_cash' => ['required_without:paid_by_bank', 'nullable', 'numeric'],
                'paid_by_bank' => ['required_without:paid_by_cash', 'nullable', 'numeric'],
            ]);

            $facilityId = Auth::user()->owner_id;
            $facilityType = Auth::user()->owner_type;

            $ptt = PickuptaskTransaction::find($validated['id']);
            $ptt->update([
                'status' => $validated['status'],
                'paid_by_cash' => $validated['paid_by_cash'],
                'paid_by_bank' => $validated['paid_by_bank'],
                'received_by' => Auth::id(),
                'remitted' => $validated['status'] == 'completed' ? 1 : 0,
            ]);



            app(\App\Services\MerchantTransactionService::class)->recordPickupDeposit($ptt);

            if($validated['status']=="completed"){   
                WarehouseTransaction::updateOrCreate(
                    [
                        // 🔑 Uniqueness criteria
                        'warehouse_type' => $facilityType,
                        'warehouse_id' => $facilityId,
                        'type' => 'cash_in',
                        'source' => 'pickup_deposit',
                        'reference' => 'PD-PT-' . $ptt->id,
                    ],
                    [
                        // ✏️ Fields that can change
                        'amount' => (float) $ptt->pickuptask->received_amount,
                        'description' => "PD deposit for pickup task #{$ptt->id}",
                        'created_by' => Auth::id(),
                    ]
                );
            }


            activityLog('pickup task deposit',"pickup task deposit with id {$ptt->id} updated for status {$ptt->status}");

            return sendResponse(
                'Pickup Task Deposit updated successfully.',
                [],
                []
            );
        } catch (\Throwable $e) {
            return sendResponse("Request failed.", [], false, [$e->getMessage()], 500);
        }
    }
}
