<?php

namespace App\Http\Controllers\Api\v1;
use App\Http\Controllers\Controller;


use App\Models\InventoryItem;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Helpers\helpers;

/**
 * @OA\Tag(name="Other", description="Inventory Item Management")
 * @OA\Server(url="{{ config('app.url') }}/api/documentation")

 */
class InventoryItemController extends Controller
{
    /**
     * @OA\Get(
     *     path="/inventory-items",
     *     summary="Retrieve a list of inventory items",
     *     description="Retrieves a paginated list of inventory items.  Allows searching by item name and/or category.",
     *     tags={"Other"},
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search term for item name or category",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="category",
     *         in="query",
     *         description="Filter by category",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Inventory items retrieved successfully",
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error retrieving inventory items",
     *     ),
     *     security={{ "bearerAuth": {} }}
     * )
     */
    public function index(Request $request)
    {
        try {
            $query = InventoryItem::query();
            if ($search = $request->query('search')) {
                $query->where('item_name', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%");
            }
            if ($category = $request->query('category')) {
                $query->where('category', $category);
            }
            $items = $query->orderBy('item_name')->paginate(15);
            $items->getCollection()->transform(fn($item) => array_merge(
                $item->toArray(),
                ['status' => $item->status]
            ));
            return sendResponse('Inventory items retrieved successfully',$items);
        } catch (\Exception $e) {
            return sendResponse('Error retrieving inventory items',[],false,[$e->getMessage()],500);
        }
    }

    /**
     * @OA\Post(
     *     path="/inventory-items",
     *     summary="Create a new inventory item",
     *     description="Creates a new inventory item.",
     *     tags={"Other"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="item_name", type="string", description="Item name", example="Item A"),
     *             @OA\Property(property="category", type="string", description="Category", example="Category A"),
     *             @OA\Property(property="current_stock", type="integer", description="Current stock", example=10),
     *             @OA\Property(property="minimum_stock", type="integer", description="Minimum stock", example=5),
     *         ),
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Inventory item created successfully",
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error creating inventory item",
     *     ),
     *     security={{ "bearerAuth": {} }}
     * )
     */
    public function store(Request $request)
    {
        try {
            $data = $request->validate(['item_name'     => 'required|string','category'      => 'required|string','current_stock' => 'required|integer|min:0','minimum_stock' => 'required|integer|min:0',]);
            $item = InventoryItem::create($data);
            return sendResponse('Inventory item created successfully',$item);
        } catch (\Exception $e) {
            return sendResponse('Error creating inventory item',[],false,[$e->getMessage()],422);
        }
    }

    /**
     * @OA\Get(
     *     path="/inventory-items/{id}",
     *     summary="Retrieve an inventory item",
     *     description="Retrieves a single inventory item by ID.",
     *     tags={"Other"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of the inventory item",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Inventory item retrieved successfully",
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Error retrieving inventory item",
     *     ),
     *     security={{ "bearerAuth": {} }}
     * )
     */
    public function show(InventoryItem $inventoryItem)
    {
        try {
            return sendResponse(
                'Inventory item retrieved successfully',
                array_merge(
                    $inventoryItem->toArray(),
                    ['status' => $inventoryItem->status]
                )
            );
        } catch (\Exception $e) {
            return sendResponse(
                'Error retrieving inventory item',
                [],
                false,
                [$e->getMessage()],
                404
            );
        }
    }

    /**
     * @OA\Put(
     *     path="/inventory-items",
     *     summary="Update an inventory item",
     *     description="Updates an existing inventory item.",
     *     tags={"Other"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", description="ID of the inventory item to update"),
     *             @OA\Property(property="item_name", type="string", description="Item name"),
     *             @OA\Property(property="category", type="string", description="Category"),
     *             @OA\Property(property="current_stock", type="integer", description="Current stock", format="int32"),
     *             @OA\Property(property="minimum_stock", type="integer", description="Minimum stock", format="int32"),
     *         ),
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Inventory item updated successfully",
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error updating inventory item",
     *     ),
     *     security={{ "bearerAuth": {} }}
     * )
     */
    public function update(Request $request)
    {
        try {
            $data = $request->validate(['item_name'     => 'sometimes|string','category'      => 'sometimes|string','current_stock' => 'sometimes|integer|min:0','minimum_stock' => 'sometimes|integer|min:0',]);
            $inventoryItem = InventoryItem::where('id', $request->id)->first();
            $inventoryItem->update($data);
            return sendResponse(
                'Inventory item updated successfully',
                $inventoryItem
            );
        } catch (\Exception $e) {
            return sendResponse('Error updating inventory item',[],false,[$e->getMessage()],422);
        }
    }

    /**
     * @OA\Post(
     *     path="/inventory-items/delete",
     *     summary="Delete an inventory item",
     *     description="Deletes an inventory item by ID.",
     *     tags={"Other"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", description="ID of the inventory item to delete"),
     *         ),
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Inventory item deleted successfully",
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Inventory item not found",
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error deleting inventory item",
     *     ),
     *     security={{ "bearerAuth": {} }}
     * )
     */
    public function destroy(Request $request)
    {
        try {
            $inventoryItem = InventoryItem::where('id', $request->id)->first();
            if (!$inventoryItem) {
                return sendResponse('Inventory item not found',[],false,['Inventory item not found'],404);
            }
            $inventoryItem->delete();
            return sendResponse('Inventory item deleted successfully',[],true,[],200);
        } catch (\Exception $e) {
            return sendResponse(
                'Error deleting inventory item',[],false,[$e->getMessage()],500);
        }
    }

    /**
     * @OA\Get(
     *     path="/inventory-items/export/csv",
     *     summary="Export inventory items to CSV",
     *     description="Exports all inventory items to a CSV file.",
     *     tags={"Other"},
     *     @OA\Response(
     *         response=200,
     *         description="CSV file exported successfully",
     *         @OA\MediaType(mediaType="text/csv")
     *     ),
     *     security={{ "bearerAuth": {} }}
     * )
     */
    public function exportCsv(): StreamedResponse
    {
        $headers = ['Content-Type'        => 'text/csv','Content-Disposition' => 'attachment; filename=\"inventory_items.csv\"',];
        $columns = ['Item ID', 'Item Name', 'Category', 'Current Stock', 'Minimum Stock', 'Status'];
        $callback = function () use ($columns) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $columns);
            InventoryItem::orderBy('item_name')->chunk(100, function ($items) use ($handle) {
                foreach ($items as $item) {
                    fputcsv($handle, [
                        $item->item_name,
                        $item->category,
                        $item->current_stock,
                        $item->minimum_stock,
                        $item->status,
                    ]);
                }
            });
            fclose($handle);
        };
        return response()->stream($callback, 200, $headers);
    }
}