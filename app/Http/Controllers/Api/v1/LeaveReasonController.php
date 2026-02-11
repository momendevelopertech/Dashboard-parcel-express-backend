<?php

namespace App\Http\Controllers\Api\v1;
use App\Http\Controllers\Controller;


use App\Http\Requests\StoreLeaveReasonRequest;
use App\Http\Requests\UpdateLeaveReasonRequest;
use App\Http\Resources\LeaveReasonResource;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use App\Models\LeaveReason;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * @OA\Tag(
 *     name="HRM",
 *     description="Human Resource Management endpoints"
 * )
 * @OA\Controller(
 *     path="/leave_reasons",
 *     tags={"HRM"},
 *     description="Leave Reason Management"
 * )
 */
class LeaveReasonController extends Controller
{
    /**
     * @OA\Get(
     *     path="/leave_reasons",
     *     summary="Get a list of leave reasons",
     *     description="Retrieves a list of leave reasons with pagination and search functionality.",
     *     tags={"HRM"},
     *     @OA\Parameter(
     *         name="query",
     *         in="query",
     *         description="Search query for leave reason name (en or ar)",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Pagination page number",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Leave reasons retrieved successfully",
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function index()
    {
        $leaveReasons = LeaveReason::query();
        if (request()->filled('query')) {
            $query = request()->input('query');
            $results = $leaveReasons
                ->where(function ($q) use ($query) {
                    $q->whereRaw('LOWER(name_en) LIKE ?', ['%' . strtolower($query) . '%'])
                        ->orWhereRaw('LOWER(name_ar) LIKE ?', ['%' . strtolower($query) . '%']);
                })
                ->orderBy('id', 'desc')
                ->get();
            $page = request()->input('page', 1);
            $perPage = 8;
            $total = $results->count();
            $paginated = new LengthAwarePaginator(
                $results->forPage($page, $perPage),
                $total,
                $perPage,
                $page,
                ['path' => request()->url(), 'query' => request()->query()]
            );
            return sendResponse(
                "Leave Reason retrieved successfully.",
                LeaveReasonResource::collection($paginated)->response()->getData(),
                []
            );
        } else {
            $leaveReasons = $leaveReasons->orderBy('id', 'desc')->paginate(8);
            return sendResponse(
                "Leave Reason retrieved successfully.",
                LeaveReasonResource::collection($leaveReasons)->response()->getData(),
                []
            );
        }
    }

    /**
     * @OA\Post(
     *     path="/leave_reasons/store",
     *     summary="Create a new leave reason",
     *     description="Creates a new leave reason.",
     *     tags={"HRM"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name_en", type="string", description="Leave reason name (en) (required)", example="Annual Leave"),
     *             @OA\Property(property="name_ar", type="string", description="Leave reason name (ar) (required)", example="إجازة سنوية"),
     *             @OA\Property(property="description", type="string", description="Leave reason description (nullable)", example="Annual leave for employees"),
     *             @OA\Property(property="is_active", type="boolean", description="Leave reason active status (nullable)", example=true),
     *         ),
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Leave reason created successfully",
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error or error occurred while creating leave reason",
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function store(StoreLeaveReasonRequest $request)
    {
        try {
            $LeaveReason = LeaveReason::create($request->validated());
            return sendResponse("Leave Reason created successfully.", new LeaveReasonResource($LeaveReason));
        } catch (QueryException $e) {
            return sendResponse("Error occurred while creating leave reason.", [], [$e->getMessage()], 422);
        }
    }

    /**
     * @OA\Post(
     *     path="/leave_reasons/update",
     *     summary="Update a leave reason",
     *     description="Updates an existing leave reason.",
     *     tags={"HRM"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", description="Leave reason ID (required)"),
     *             @OA\Property(property="name_en", type="string", description="Leave reason name (en) (nullable)", example="Sick Leave"),
     *             @OA\Property(property="name_ar", type="string", description="Leave reason name (ar) (nullable)", example="إجازة مرضية"),
     *             @OA\Property(property="description", type="string", description="Leave reason description (nullable)", example="Leave due to illness"),
     *             @OA\Property(property="is_active", type="boolean", description="Leave reason active status (nullable)", example=false),
     *         ),
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Leave reason updated successfully",
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error or error occurred while updating leave reason",
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function update(UpdateLeaveReasonRequest $request)
    {
        try {
            $LeaveReason = LeaveReason::findOrFail($request->id);
            $LeaveReason->update($request->validated());
            return sendResponse("Leave Reason updated successfully.", new LeaveReasonResource($LeaveReason));
        } catch (QueryException $e) {
            return sendResponse("Error occurred while updating leave reason.", [], [$e->getMessage()], 422);
        }
    }

    /**
     * @OA\Post(
     *     path="/leave_reasons/delete",
     *     summary="Delete a leave reason",
     *     description="Deletes a leave reason.",
     *     tags={"HRM"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", description="Leave reason ID (required)"),
     *         ),
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Leave reason deleted successfully",
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error occurred while deleting leave reason",
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function delete(Request $request)
    {
        try {
            LeaveReason::where('id', $request->id)->delete();
        } catch (QueryException $e) {
            return sendResponse("Error Occured.", [], [$e->getMessage()], 422);
        }
        return sendResponse("Leave Reason deleted successfully.", []);
    }

    /**
     * @OA\Get(
     *     path="/leave_reasons/all",
     *     summary="Get all leave reasons",
     *     description="Retrieves all leave reasons.",
     *     tags={"HRM"},
     *     @OA\Response(
     *         response=200,
     *         description="Leave reasons retrieved successfully",
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function all()
    {
        return sendResponse("Leave Reasons", new LeaveReasonResource(LeaveReason::all()));
    }
}