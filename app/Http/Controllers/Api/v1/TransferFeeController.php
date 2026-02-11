<?php

namespace App\Http\Controllers\Api\v1;
use App\Http\Controllers\Controller;



use App\Models\TransferFee;
use App\Models\Hub;
use App\Models\Station;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TransferFeeController extends Controller
{
    public function index(Request $request)
    {
        $perPage = max(1, (int) $request->query('per_page', 10));
        $query = trim((string) $request->query('query', ''));

        $base = TransferFee::query();

        if ($query !== '') {
            $base->where(function ($q) use ($query) {
                $q->where('warehouse', 'like', "%{$query}%")
                    ->orWhere('amount', 'like', "%{$query}%");
            });
        }

        $paginator = $base->orderByDesc('updated_at')->paginate($perPage);

        return sendResponse('Transfer fees retrieved successfully.', [
            'data' => $paginator->items(),
            // 'links' => paginationLinks($paginator) ?? null, // keep your helper if you use it
        ]);
    }

    /**
     * GET /transfer-fees/options
     * For the dropdown on the frontend. You can add more keys later if needed.
     */
    public function options()
    {
        $warehouses = [
            ['value' => 'first_warehouse', 'label' => 'First warehouse'],
            ['value' => 'other_warehouse', 'label' => 'Other warehouse'],
        ];

        return sendResponse('Options retrieved.', [
            'warehouses' => $warehouses,
        ]);
    }

    /**
     * POST /transfer-fees/store
     * Body: { warehouse: 'first_warehouse'|'other_warehouse', amount: number }
     * Creates a row for that warehouse key (unique).
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'warehouse' => 'required|in:first_warehouse,other_warehouse',
            'amount' => 'required|numeric|min:0',
        ]);

        $exists = TransferFee::where('warehouse', $validated['warehouse'])->exists();
        if ($exists) {
            return sendResponse('Fee already exists for this warehouse key.', [], false, [], 422);
        }

        TransferFee::create([
            'warehouse' => $validated['warehouse'],
            'amount' => $validated['amount'],
        ]);
        activityLog('transfer fee created', "new transfer fee created for warehouse : {$validated['warehouse']}");

        return sendResponse('Transfer fee created successfully.', []);
    }

    /**
     * POST /transfer-fees/update
     * Body: { id: number, amount: number }
     */
    public function update(Request $request)
    {
        $validated = $request->validate([
            'id' => 'required|integer|exists:transfer_fees,id',
            'amount' => 'required|numeric|min:0',
        ]);

        $fee = TransferFee::findOrFail($validated['id']);
        $fee->update(['amount' => $validated['amount']]);
        activityLog('transfer fee updated', "transfer fee updated for warehouse : {$fee->warehouse}");

        return sendResponse('Transfer fee updated successfully.', []);
    }

    /**
     * DELETE /transfer-fees/delete?id=
     */
    public function destroy(Request $request)
    {
        $id = (int) $request->query('id');
        $fee = TransferFee::find($id);

        if (!$fee) {
            return sendResponse('Transfer fee not found.', [], false, [], 404);
        }

        $fee->delete();
        activityLog('transfer fee deleted', "transfer fee deleted for warehouse : {$fee->warehouse}");

        return sendResponse('Transfer fee deleted successfully.', []);
    }
}
