<?php

namespace App\Http\Controllers\Api\v1;
use App\Http\Controllers\Controller;


use App\Http\Requests\StoreLeaveRequestApprovalRequest;
use App\Http\Requests\UpdateLeaveRequestApprovalRequest;
use App\Http\Resources\LeaveRequestApprovalResource;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use App\Models\LeaveRequestApproval;

/**
 * @OA\Tag(name="HRM", description="Leave Request Approval Management")
 * @OA\Controller(description="Manage leave request approvals")
 */
class LeaveRequestApprovalController extends Controller
{
    /**
     * @OA\Get(
     *     path="/leave_request_approvals",
     *     summary="Get a list of leave request approvals",
     *     description="Retrieve a paginated list of leave request approvals.",
     *     tags={"HRM"},
     *     @OA\Response(
     *         response=200,
     *         description="Leave Request Approval retrieved successfully."
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error occurred while retrieving leave request approvals."
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function index()
    {
        $leaveRequestApprovals = LeaveRequestApproval::query()->with(['approver', 'leaveRequest', 'hierarchyLevel']);
        $leaveRequestApprovals = $leaveRequestApprovals->orderBy('id', 'desc')->paginate(8);
        return sendResponse("Leave Request Approval reterived successfully.", LeaveRequestApprovalResource::collection($leaveRequestApprovals)->response()->getData(), []);
    }

    /**
     * @OA\Post(
     *     path="/leave_request_approvals/store",
     *     summary="Create a new leave request approval",
     *     description="Create a new leave request approval.",
     *     tags={"HRM"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="leave_request_id", type="integer", description="Leave Request ID (required, exists:leave_requests,id)"),
     *             @OA\Property(property="approver_id", type="integer", description="Approver ID (required, exists:employees,id)"),
     *             @OA\Property(property="hierarchy_level_id", type="integer", description="Hierarchy Level ID (required, exists:hierarchy_levels,id)"),
     *             @OA\Property(property="status", type="string", description="Status (nullable, in:pending,approved,rejected)"),
     *             @OA\Property(property="comment", type="string", description="Comment (nullable)"),
     *             @OA\Property(property="approved_at", type="string", format="date", description="Approved At (nullable)")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Leave Request Approval created successfully."
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error occurred while creating leave request approval."
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function store(StoreLeaveRequestApprovalRequest $request)
    {
        try {
            $LeaveRequestApproval = LeaveRequestApproval::create($request->validated());
            return sendResponse("Leave Request Approval created successfully.", new LeaveRequestApprovalResource($LeaveRequestApproval));
        } catch (QueryException $e) {
            return sendResponse("Error occurred while creating leave request approval.", [], [$e->getMessage()], 422);
        }
    }

    /**
     * @OA\Post(
     *     path="/leave_request_approvals/update",
     *     summary="Update a leave request approval",
     *     description="Update an existing leave request approval.",
     *     tags={"HRM"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", description="ID of the leave request approval to update (required)"),
     *             @OA\Property(property="leave_request_id", type="integer", description="Leave Request ID (required, exists:leave_requests,id)"),
     *             @OA\Property(property="approver_id", type="integer", description="Approver ID (required, exists:employees,id)"),
     *             @OA\Property(property="hierarchy_level_id", type="integer", description="Hierarchy Level ID (required, exists:hierarchy_levels,id)"),
     *             @OA\Property(property="status", type="string", description="Status (nullable, in:pending,approved,rejected)"),
     *             @OA\Property(property="comment", type="string", description="Comment (nullable)"),
     *             @OA\Property(property="approved_at", type="string", format="date", description="Approved At (nullable)")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Leave Request Approval updated successfully."
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error occurred while updating leave request approval."
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function update(UpdateLeaveRequestApprovalRequest $request)
    {
        try {
            $LeaveRequestApproval = LeaveRequestApproval::findOrFail($request->id);
            $LeaveRequestApproval->update($request->validated());
            return sendResponse("Leave Request Approval updated successfully.", new LeaveRequestApprovalResource($LeaveRequestApproval));
        } catch (QueryException $e) {
            return sendResponse("Error occurred while updating leave request approval.", [], [$e->getMessage()], 422);
        }
    }

    /**
     * @OA\Post(
     *     path="/leave_request_approvals/delete",
     *     summary="Delete a leave request approval",
     *     description="Delete an existing leave request approval.",
     *     tags={"HRM"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", description="ID of the leave request approval to delete (required)")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Leave Request Approval deleted successfully."
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error occurred while deleting leave request approval."
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function delete(Request $request)
    {
        try {
            LeaveRequestApproval::where('id', $request->id)->delete();
        } catch (QueryException $e) {
            return sendResponse("Error Occured.", [], [$e->getMessage()], 422);
        }
        return sendResponse("Leave Request Approval deleted successfully.", []);
    }

    /**
     * @OA\Get(
     *     path="/leave_request_approvals/all",
     *     summary="Get all leave request approvals",
     *     description="Retrieve all leave request approvals.",
     *     tags={"HRM"},
     *     @OA\Response(
     *         response=200,
     *         description="Leave Request Approvals"
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function all()
    {
        return sendResponse("Leave Request Approvals", LeaveRequestApprovalResource::collection(LeaveRequestApproval::all()));
    }
}
