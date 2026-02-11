<?php

namespace App\Http\Controllers\Api\v1;

use App\Models\Shelf;
use Illuminate\Http\Request;
use App\Models\ShelfCategory;
use App\Models\Scopes\ShelfScope;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Http\Resources\ShelfResource;
use App\Models\AssignShipmentToShelf;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;

/**
 * @OA\Tag(name="WMS", description="WMS API endpoints")
 * @OA\Server(url="/api", description="WMS API Server")
 */
class ShelfController extends Controller
{

    /**
     * @OA\Get(
     *     path="/shelves",
     *     summary="Get a list of shelves",
     *     description="Retrieves a paginated list of shelves.",
     *     tags={"WMS"},
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search query",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Shelves retrieved successfully",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     ),
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function index(Request $request)
    {
        $search = $request->string('search')->toString();
        $perPage = $request->integer('per_page', 50);
        $categoryId = $request->integer('category_id'); // NEW

        $shelvesQuery = Shelf::byOwner()
            ->with(['hub', 'station', 'branch', 'category'])
            ->when($search !== '', function ($q) use ($search) {
                // If you already have a scope searchByBarcode($search), keep it:
                // return $q->searchByBarcode($search);
                // Or do a broader search:
                $q->where(function ($qq) use ($search) {
                    $qq->where('barcode', 'like', "%{$search}%")
                        ->orWhere('location', 'like', "%{$search}%");
                });
            })
            ->when($categoryId, fn($q) => $q->where('category_id', $categoryId)) // NEW
            ->orderBy('id', 'desc');

        $paginator = $shelvesQuery
            ->paginate($perPage)
            ->appends($request->only(['search', 'per_page', 'category_id'])); // keep params

        $payload = ShelfResource::collection($paginator)
            ->response()
            ->getData(true);

        return sendResponse(
            "Shelves retrieved successfully.",
            $payload,
            true
        );
    }


    /**
     * @OA\Post(
     *     path="/shelves/store",
     *     summary="Create new shelves",
     *     description="Creates multiple shelves based on provided parameters.",
     *     tags={"WMS"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="area", type="string", description="Area of the shelves", example="Area A"),
     *             @OA\Property(property="shelves", type="integer", description="Number of shelves", example=5),
     *             @OA\Property(property="layers", type="integer", description="Number of layers per shelf", example=3),
     *             @OA\Property(property="partitions", type="integer", description="Number of partitions per layer", example=2),
     *             @OA\Property(property="category", type="integer", description="Category ID", example=1),
     *             @OA\Property(property="hub_id", type="integer", description="Hub ID", example=1),
     *             @OA\Property(property="station_id", type="integer", description="Station ID", example=1),
     *             @OA\Property(property="branch_id", type="integer", description="Branch ID", example=1)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Shelves created successfully"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error or error occurred"
     *     ),
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'area' => 'required|string',
            'shelves' => 'required|integer|min:1',
            'layers' => 'required|integer|min:1',
            'partitions' => 'required|integer|min:1',
            'category' => 'required',
        ]);

        DB::beginTransaction();

        try {
            for ($shelf = 1; $shelf <= $validated['shelves']; $shelf++) {
                for ($layer = 1; $layer <= $validated['layers']; $layer++) {
                    for ($partition = 1; $partition <= $validated['partitions']; $partition++) {
                        Shelf::create([
                            'area' => $validated['area'],
                            'shelf_number' => $shelf,
                            'layer_number' => $layer,
                            'partition_number' => $partition,
                            'barcode' => generateShelfBarcode(),
                            'location' => "{$validated['area']}-{$shelf}-{$layer}-{$partition}",
                            'created_by' => Auth::id(),
                            'category_id' => $request->category,
                        ]);
                 activityLog('shelf created',"new shelf created with shelf number {$validated['shelves']}");

                    }
                }
            }
            DB::commit();

            return sendResponse("Shelves created successfully", [], true, [], 200);
        } catch (\Exception $e) {
            DB::rollBack();

            return sendResponse("Error Occured.", [], false, [$e->getMessage()], 422);
        }
    }

    /**
     * @OA\Post(
     *     path="/shelves/delete",
     *     summary="Delete a shelf",
     *     description="Deletes a shelf by ID.",
     *     tags={"WMS"},
     *     @OA\Parameter(
     *         name="id",
     *         in="query",
     *         description="ID of the shelf to delete",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Shelf deleted successfully"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Shelf not found"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error occurred while deleting the shelf"
     *     ),
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function delete()
    {
        $shelfId = request()->id;
        $shelf = Shelf::withoutGlobalScope(ShelfScope::class)->findOrFail($shelfId);
        $shelf->delete();
        activityLog('shelf deleted',"shelf with id {$shelfId} deleted");
        return sendResponse("Shelf deleted successfully.", []);
    }

    public function getSingle()
    {
        try {
            $shelfId = request()->id;
            $shelf = Shelf::withoutGlobalScope(ShelfScope::class)
                ->with('hub', 'station', 'branch', 'items')
                ->where('id', $shelfId)
                ->first();
            if (!$shelf) {
                return sendResponse("Shelf not found.", [], [], 404);
            }
        } catch (QueryException $e) {
            return sendResponse("Error occurred while fetching the shelf.", [], [$e->getMessage()], 422);
        }
        return sendResponse("Shelf fetched successfully.", new ShelfResource($shelf));
    }

    /**
     * @OA\Get(
     *     path="/shelves/printShelf",
     *     summary="Print a shelf",
     *     description="Generates HTML for printing a shelf's barcode.",
     *     tags={"WMS"},
     *     @OA\Parameter(
     *         name="id",
     *         in="query",
     *         description="ID of the shelf to print",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="HTML for printing the shelf"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Shelf not found"
     *     ),
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function printShelf()
    {
        $id = request('id');
        $shelf = Shelf::withoutGlobalScope(ShelfScope::class)->findOrFail($id);
        return $this->generateBarcodeAndRenderHtml($shelf);
    }

    /**
     * @OA\Get(
     *     path="/shelves/printShelfByCategory",
     *     summary="Print shelves by category",
     *     description="Generates HTML for printing shelves of a specific category.",
     *     tags={"WMS"},
     *     @OA\Parameter(
     *         name="categoryId",
     *         in="query",
     *         description="ID of the category to print shelves for",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="HTML for printing shelves"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Category not found"
     *     ),
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function printShelfByCategory()
    {
        $categoryId = request('categoryId');
        $category = ShelfCategory::with([
            'shelves' => function ($query) {
                $query->withoutGlobalScope(ShelfScope::class);
            }
        ])->findOrFail($categoryId);
        Log::info([
            'category' => $category
        ]);
        $html = view('printShelf', compact('category'))->render();
        return response($html);
    }

    public function generateBarcodeAndRenderHtml($shelf)
    {
        $html = view('printShelf', compact('shelf'))->render();
        return response($html);
    }

    /**
     * @OA\Get(
     *     path="/shelves/shipments",
     *     summary="Get a list of shipments assigned to shelves",
     *     description="Retrieves a paginated list of shipments assigned to shelves with various filtering options.",
     *     tags={"WMS"},
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search query (tracking number)",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="category_id",
     *         in="query",
     *         description="Filter by category ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="shelf_barcode",
     *         in="query",
     *         description="Filter by shelf barcode",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="assigned_by",
     *         in="query",
     *         description="Filter by assigned user ID",
     *         @OA\Schema(type="integer")
     *     ),
     *      @OA\Parameter(
     *         name="date_start",
     *         in="query",
     *         description="Filter by start date (YYYY-MM-DD HH:mm:ss)",
     *         @OA\Schema(type="string", format="date-time")
     *     ),
     *      @OA\Parameter(
     *         name="date_end",
     *         in="query",
     *         description="Filter by end date (YYYY-MM-DD HH:mm:ss)",
     *         @OA\Schema(type="string", format="date-time")
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filter by shipment status",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="exception_type",
     *         in="query",
     *         description="Filter by exception type",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="future_date_start",
     *         in="query",
     *         description="Filter by future delivery start date (YYYY-MM-DD HH:mm:ss)",
     *         @OA\Schema(type="string", format="date-time")
     *     ),
     *     @OA\Parameter(
     *         name="future_date_end",
     *         in="query",
     *         description="Filter by future delivery end date (YYYY-MM-DD HH:mm:ss)",
     *         @OA\Schema(type="string", format="date-time")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Shipments retrieved successfully",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     ),
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function shipments(Request $request)
    {
        try {
            $search = $request->search;
            $categoryId = $request->category_id;
            $shelfBarcode = $request->shelf_barcode;
            $perPage = $request->input('per_page', 8);
            $assignedBy = $request->assigned_by;
            $status = $request->status;
            $exceptionType = $request->exception_type;
            $futureDateStart = $request->future_date_start;
            $futureDateEnd = $request->future_date_end;

            $shipments = AssignShipmentToShelf::whereDoesntHave('stock_out_task_shipments', function ($query) {
                $query->where('status', 'pending');
            })
                ->byOwner()
                ->with([
                    "shipment.crm_task",
                    "assigned_by",
                    "shipment.core_exception",
                    "shelf",
                ])
                ->searchByTrackingNo($search)
                ->when($categoryId, function ($q) use ($categoryId) {
                    $q->whereHas('shelf', function ($shelfQ) use ($categoryId) {
                        $shelfQ->where('category_id', $categoryId);
                    });
                })
                ->when($shelfBarcode, function ($q) use ($shelfBarcode) {
                    $q->where('barcode', $shelfBarcode);
                })
                ->when($assignedBy, function ($q) use ($assignedBy) {
                    $q->where('assigned_by', $assignedBy);
                })
                ->when($request->has('from') && $request->has('to'), function ($q) use ($request) {
                    try {
                        $fromDate = Carbon::createFromFormat('Y-m-d H:i', $request->input('from'));
                        $toDate = Carbon::createFromFormat('Y-m-d H:i', $request->input('to'));
                        if ($fromDate && $toDate) {
                            $q->whereBetween('created_at', [$fromDate, $toDate]);
                        }
                    } catch (\Exception $e) {
                        // Invalid date format, ignore the filter
                        Log::warning("Invalid date format for from/to: {$request->input('from')} / {$request->input('to')}", ['exception' => $e->getMessage()]);
                    }
                })
                ->when($status, function ($q) use ($status) {
                    $q->whereHas('shipment.core_status', function ($sq) use ($status) {
                        $sq->where('status', $status);
                    });
                })
                ->when($exceptionType, function ($q) use ($exceptionType) {
                    $q->whereHas('shipment.core_exception', function ($sq) use ($exceptionType) {
                        $sq->where('name', $exceptionType);
                    });
                })
                ->when($futureDateStart && $futureDateEnd, function ($q) use ($futureDateStart, $futureDateEnd) {
                    $q->whereHas('shipment.shipment_delivery', function ($sq) use ($futureDateStart, $futureDateEnd) {
                        $dateEndWithTime = preg_match('/\d{2}:\d{2}:\d{2}$/', $futureDateEnd) ? $futureDateEnd : $futureDateEnd . ' 23:59:59';
                        $sq->whereBetween('future_delivery_date', [$futureDateStart, $dateEndWithTime]);
                    });
                })
                ->orderBy('id', 'desc');

            $shipments = $shipments->paginate($perPage);

            return sendResponse(
                "Shelves retrieved successfully.",
                new ShelfResource($shipments),
                true
            );
        } catch (\Exception $e) {
            return sendResponse(
                "Error retrieving shelves.",
                [],
                false,
                [$e->getMessage()],
                500
            );
        }
    }
    public function printMultiple(Request $request)
    {
        $ids = $request->input('ids', []);
        if (!is_array($ids) || empty($ids)) {
            return response('No shelf IDs provided.', 422);
        }

        $shelves = Shelf::withoutGlobalScope(ShelfScope::class)
            ->whereIn('id', $ids)
            ->get();

        if ($shelves->isEmpty()) {
            return response('No shelves found for the provided IDs.', 404);
        }

        // هنستخدم نفس الـ Blade بعد ما نحدّثه ليدعم $shelves
        $html = view('printShelf', ['shelves' => $shelves])->render();
        return response($html);
    }

}
