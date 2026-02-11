<?php

namespace App\Http\Controllers\Api\v1;
use App\Http\Controllers\Controller;


use App\Models\StockTransaction;
use App\Models\InventoryItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Helpers\helpers as ResponseHelper;

/**
 * @OA\Tag(name="Other", description="Stock Transaction Management")
 */
class StockTransactionController extends Controller
{
    /**
     * @OA\Get(
     *     path="/stock-transactions",
     *     summary="Retrieve stock transactions",
     *     description="Retrieves a list of stock transactions with optional filters.",
     *     tags={"Other"},
     *     @OA\Parameter(
     *         name="date_from",
     *         in="query",
     *         description="Filter transactions from this date (YYYY-MM-DD)",
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="date_to",
     *         in="query",
     *         description="Filter transactions to this date (YYYY-MM-DD)",
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="inventory_item_id",
     *         in="query",
     *         description="Filter transactions by inventory item ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="type",
     *         in="query",
     *         description="Filter transactions by type (in/out)",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Stock transactions retrieved successfully",
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad Request"
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function index(Request $request)
    {
        $query = StockTransaction::with(['item', 'user']);

        if ($from = $request->query('date_from')) {
            $query->whereDate('transaction_date', '>=', $from);
        }
        if ($to = $request->query('date_to')) {
            $query->whereDate('transaction_date', '<=', $to);
        }
        if ($itemId = $request->query('inventory_item_id')) {
            $query->where('inventory_item_id', $itemId);
        }
        if ($type = $request->query('type')) {
            $query->where('type', $type);
        }

        $transactions = $query->orderBy('transaction_date', 'desc')->paginate(15);
        return sendResponse('Stock transactions retrieved successfully', $transactions);
    }

    /**
     * @OA\Post(
     *     path="/stock-transactions",
     *     summary="Record a new stock transaction",
     *     description="Records a new stock transaction.",
     *     tags={"Other"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="inventory_item_id", type="integer", description="ID of the inventory item"),
     *             @OA\Property(property="type", type="string", description="Type of transaction (in|out)"),
     *             @OA\Property(property="quantity", type="integer", description="Quantity of items"),
     *             @OA\Property(property="notes", type="string", description="Optional notes for the transaction")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Transaction recorded successfully",
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Unprocessable Entity"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Failed to record transaction",
     *     ),
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *          name="inventory_item_id",
     *          in="query",
     *          description="ID of the inventory item (required, exists:inventory_items,id)",
     *          required=true,
     *          @OA\Schema(type="integer")
     *      ),
     *      @OA\Parameter(
     *          name="type",
     *          in="query",
     *          description="Type of transaction (in|out) (required, in:in,out)",
     *          required=true,
     *          @OA\Schema(type="string")
     *      ),
     *      @OA\Parameter(
     *          name="quantity",
     *          in="query",
     *          description="Quantity of items (required, integer, min:1)",
     *          required=true,
     *          @OA\Schema(type="integer")
     *      ),
     *      @OA\Parameter(
     *          name="notes",
     *          in="query",
     *          description="Optional notes for the transaction (nullable, string)",
     *          @OA\Schema(type="string")
     *      )
     * )
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'inventory_item_id' => 'required|exists:inventory_items,id',
            'type'              => 'required|in:in,out',
            'quantity'          => 'required|integer|min:1',
            'notes'             => 'nullable|string',
        ]);

        try {
            $transaction = DB::transaction(function () use ($data, $request) {
                // Create stock transaction
                $tx = StockTransaction::create([
                    'inventory_item_id' => $data['inventory_item_id'],
                    'type'              => $data['type'],
                    'quantity'          => $data['quantity'],
                    'user_id'           => $request->user()->id,
                    'notes'             => $data['notes'] ?? null,
                ]);

                // Update inventory item stock
                $item = $tx->item;
                $delta = $tx->type === 'in' ? $tx->quantity : -$tx->quantity;
                $item->current_stock += $delta;
                $item->save();

                return $tx;
            });

            return sendResponse('Transaction recorded successfully', $transaction);
        } catch (\Exception $e) {
            return sendResponse(
                'Failed to record transaction',
                [],
                false,
                ['error' => $e->getMessage()],
                500
            );
        }
    }

    /**
     * @OA\Get(
     *     path="/inventory-items/{item}/transactions",
     *     summary="Retrieve transactions for a specific inventory item",
     *     description="Retrieves a list of transactions for a specific inventory item.",
     *     tags={"Other"},
     *     @OA\Parameter(
     *         name="item",
     *         in="path",
     *         description="ID of the inventory item",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Transactions retrieved successfully",
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Inventory item not found"
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function forItem(InventoryItem $item)
    {
        $txs = $item->transactions()
                    ->with('user')
                    ->orderBy('transaction_date', 'desc')
                    ->paginate(20);
        return sendResponse('Transactions retrieved successfully', $txs);
    }
}
