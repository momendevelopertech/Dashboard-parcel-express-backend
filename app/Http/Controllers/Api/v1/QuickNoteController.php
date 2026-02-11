<?php

namespace App\Http\Controllers\Api\v1;
use App\Http\Controllers\Controller;


use App\Http\Requests\StoreQuickNoteRequest;
use App\Http\Resources\QuickNoteResource;
use Illuminate\Database\QueryException;
use App\Models\QuickNote;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class QuickNoteController extends Controller
{
    /**
     * Get Quick Notes for Shipment
     *
     * Retrieve all quick notes created by the authenticated driver for a specific shipment.
     * Notes are returned in descending shipment by creation date.
     *
     * @OA\Post(
     *     path="/driver/quick_notes",
     *     summary="Get quick notes for shipment",
     *     description="Retrieve all quick notes created by driver for specific shipment",
     *     operationId="getQuickNotes",
     *     tags={"Driver App"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="shipment_tracking_no", type="string", example="PE041225123456", description="Shipment tracking number")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Quick notes retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Quick Notes reterived successfully."),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
                     type="object",
                     @OA\Property(property="id", type="integer"),
                     @OA\Property(property="shipment_tracking_no", type="string"),
                     @OA\Property(property="content", type="string"),
                     @OA\Property(property="driver_id", type="integer"),
                     @OA\Property(property="created_at", type="string", format="date-time")
                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     )
     * )
     */
    public function index(Request $request)
    {
        $request->validate([
            "shipment_tracking_no" => "required"
        ]);
        $states = QuickNote::where("shipment_tracking_no", $request->shipment_tracking_no)->where('driver_id', Auth::id())->orderBy('id', 'desc')->get();
        return sendResponse("Quick Notes reterived successfully.", new QuickNoteResource(resource: $states), []);
    }

    /**
     * Create Quick Note
     *
     * Create a new quick note for an shipment by the authenticated driver.
     * Useful for recording important delivery information or customer interactions.
     *
     * @OA\Post(
     *     path="/driver/quick_notes/store",
     *     summary="Create quick note",
     *     description="Create a new quick note for an shipment with driver attribution",
     *     operationId="createQuickNote",
     *     tags={"Driver App"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="shipment_tracking_no", type="string", example="PE041225123456", description="Shipment tracking number"),
     *             @OA\Property(property="content", type="string", example="Customer requested specific delivery time", description="Note content")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Quick note created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Quick Note created successfully."),
     *             @OA\Property(
                 property="data",
                 type="object",
                 @OA\Property(property="id", type="integer"),
                 @OA\Property(property="shipment_tracking_no", type="string"),
                 @OA\Property(property="content", type="string"),
                 @OA\Property(property="driver_id", type="integer"),
                 @OA\Property(property="created_at", type="string", format="date-time")
             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error or database error",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     )
     * )
     */
    public function store(Request $request)
    {
        $request->validate([
            "shipment_tracking_no" => "required|exists:shipments,tracking_no",
            "content" => "required",
        ]);

        try {
            $data = $request->all();
            $data['driver_id'] = Auth::id();
            $state = QuickNote::create($data);
        } catch (QueryException $e) {
            return sendResponse("Error Occured.", [], [$e->getMessage()], 422);
        }
        return sendResponse("Quick Note created successfully.", new QuickNoteResource($state));
    }
}
