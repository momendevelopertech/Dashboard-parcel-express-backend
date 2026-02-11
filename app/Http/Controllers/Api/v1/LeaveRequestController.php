<?php

namespace App\Http\Controllers\Api\v1;
use App\Http\Controllers\Controller;


use App\Http\Requests\StoreLeaveRequestRequest;
use App\Http\Requests\UpdateLeaveRequestRequest;
use App\Http\Resources\LeaveRequestResource;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Models\Leave;
use App\Models\Employee;
use App\Models\User;

/**
 * @OA\Tag(name="HRM", description="Human Resource Management endpoints")
 * @OA\Controller(description="Leave Request Controller")
 */
class LeaveRequestController extends Controller
{
    /**
     * @OA\Get(
     *     path="/leave_requests",
     *     summary="Get a list of leave requests",
     *     description="Retrieve a paginated list of leave requests.",
     *     tags={"HRM"},
     *     @OA\Response(
     *         response=200,
     *         description="Leave Requests retrieved successfully.",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation errors",
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function index()
    {

        $leaveRequests = Leave::byOwner()->with(['employee', 'reason', 'currentApprover', 'approvedBy'])
            ->orderBy('id', 'desc')
            ->paginate(8);

        return sendResponse("Leave Requests retrieved successfully.", LeaveRequestResource::collection($leaveRequests), []);
    }
    /**
     * @OA\Post(
     *     path="/leave_requests/store",
     *     summary="Create a new leave request",
     *     description="Create a new leave request.",
     *     tags={"HRM"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="employee_id", type="integer", description="ID of the employee", example=1),
     *             @OA\Property(property="reason_id", type="integer", description="ID of the leave reason", example=1),
     *             @OA\Property(property="status", type="string", description="Status of the leave request (pending, approved, rejected)", example="pending"),
     *             @OA\Property(property="start_date", type="string", format="date", description="Start date of the leave", example="2024-03-08"),
     *             @OA\Property(property="end_date", type="string", format="date", description="End date of the leave", example="2024-03-15"),
     *             @OA\Property(property="proof_file", type="file", description="Proof file (jpeg, png, jpg, pdf)", format="binary"),
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Leave Request created successfully.",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation errors or error occurred while creating leave request.",
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     * @param StoreLeaveRequestRequest $request
     */
    public function store(StoreLeaveRequestRequest $request)
    {
        try {
            $data = $request->validated();

            // Upload proof file if provided
            if ($request->hasFile('proof_file')) {
                $data['proof_file'] = uploadFile($request->file('proof_file'), 'public/proof_files');


            }

            // Get the requesting employee
            $employee = User::findOrFail($data['employee_id']);

            // Find the first approver (direct_manager_id)
            $data['current_approver_id'] = $employee->direct_manager_id;

            // Create the leave request
            $leaveRequest = Leave::create($data);

            return sendResponse("Leave Request created successfully.", new LeaveRequestResource($leaveRequest));
        } catch (QueryException $e) {
            return sendResponse("Error occurred while creating leave request.", [], false, [$e->getMessage()], 422);
        }
    }
    /**
     * @OA\Post(
     *     path="/leave_requests/update",
     *     summary="Update a leave request",
     *     description="Update an existing leave request.",
     *     tags={"HRM"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", description="ID of the leave request"),
     *             @OA\Property(property="employee_id", type="integer", description="ID of the employee", example=1),
     *             @OA\Property(property="reason_id", type="integer", description="ID of the leave reason", example=1),
     *             @OA\Property(property="status", type="string", description="Status of the leave request (pending, approved, rejected)", example="pending"),
     *             @OA\Property(property="start_date", type="string", format="date", description="Start date of the leave", example="2024-03-08"),
     *             @OA\Property(property="end_date", type="string", format="date", description="End date of the leave", example="2024-03-15"),
     *             @OA\Property(property="proof_file", type="file", description="Proof file (jpeg, png, jpg, pdf)", format="binary"),
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Leave Request updated successfully.",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation errors or error occurred while updating leave request.",
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     * @param UpdateLeaveRequestRequest $request
     */
    public function update(UpdateLeaveRequestRequest $request)
    {
        try {
            $data = $request->validated();
            $leaveRequest = Leave::findOrFail($request->id);

            // Handle proof file update
            if ($request->hasFile('proof_file')) {
                if ($leaveRequest->proof_file && file_exists(storage_path('app/' . $leaveRequest->proof_file))) {
                    unlink(storage_path('app/' . $leaveRequest->proof_file));
                }
                $data['proof_file'] = uploadFile($request->file('proof_file'), 'public/proof_files');
            }

            $leaveRequest->update($data);

            return sendResponse("Leave Request updated successfully.", new LeaveRequestResource($leaveRequest));
        } catch (QueryException $e) {
            return sendResponse("Error occurred while updating leave request.", [], [$e->getMessage()], 422);
        }
    }
    /**
     * @OA\Post(
     *     path="/leave_requests/delete",
     *     summary="Delete a leave request",
     *     description="Delete an existing leave request.",
     *     tags={"HRM"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", description="ID of the leave request to delete"),
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Leave Request deleted successfully.",
     *     ),
     *      @OA\Response(
     *         response=422,
     *         description="Error Occurred.",
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     * @param Request $request
     */
    public function delete(Request $request)
    {
        try {
            $leaveRequest = Leave::findOrFail($request->id);
            $leaveRequest->delete();
        } catch (QueryException $e) {
            return sendResponse("Error Occurred.", [], [$e->getMessage()], 422);
        }
        return sendResponse("Leave Request deleted successfully.", []);
    }
    /**
     * @OA\Post(
     *     path="/leave_requests/approve",
     *     summary="Approve a leave request",
     *     description="Approve an existing leave request.",
     *     tags={"HRM"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="leave_id", type="integer", description="ID of the leave request to approve"),
     *             @OA\Property(property="approver_id", type="integer", description="ID of the approver"),
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Leave request approved successfully or Leave request forwarded to the next level or No further approver found, leave request remains pending.",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Approver not found.",
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error occurred while approving leave request.",
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     * @param Request $request
     */
    public function approve(Request $request)
    {
        try {
            $leaveRequest = Leave::findOrFail($request->leave_id);
            $approver = Employee::with('level')->where('user_id', $request->approver_id)->first();

            if (!$approver) {
                return sendResponse("Approver not found.", [], [], 404);
            }

            // If the approver is Level 1, approve immediately
            if ($approver->level->level == 1) {
                $leaveRequest->update([
                    'status' => 'approved',
                    'approved_by' => $approver->user_id,
                    'approved_at' => operation_now(),

                ]);
                return sendResponse("Leave request approved successfully.", new LeaveRequestResource($leaveRequest));
            }

            // Otherwise, move approval to the direct manager
            if ($approver->direct_manager_id) {
                $leaveRequest->update([
                    'current_approver_id' => $approver->direct_manager_id, // Assign parent as next approver
                    'status' => 'pending', // Keep pending until final approval
                ]);
                return sendResponse("Leave request forwarded to the next level.", new LeaveRequestResource($leaveRequest));
            }

            return sendResponse("No further approver found, leave request remains pending.", new LeaveRequestResource($leaveRequest));

        } catch (QueryException $e) {
            return sendResponse("Error occurred while approving leave request.", [], [$e->getMessage()], 422);
        }
    }
    /**
     * @OA\Post(
     *     path="/leave_requests/reject",
     *     summary="Reject a leave request",
     *     description="Reject an existing leave request.",
     *     tags={"HRM"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", description="ID of the leave request to reject"),
     *             @OA\Property(property="approver_id", type="integer", description="ID of the approver"),
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Leave request rejected successfully.",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="You are not authorized to reject this request.",
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error occurred while rejecting leave request.",
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     * @param Request $request
     */
    public function reject(Request $request)
    {
        try {
            $leaveRequest = Leave::findOrFail($request->id);
            $approver = User::findOrFail($request->approver_id);
            if ($leaveRequest->current_approver_id !== $approver->id) {
                return sendResponse("You are not authorized to reject this request.", [], [], 403);
            }
            $leaveRequest->update([
                'status' => 'rejected',
                'approved_by' => $approver->id,
                'approved_at' => operation_now(),
                'current_approver_id' => null, // No more approvals
            ]);
            return sendResponse("Leave request rejected successfully.", new LeaveRequestResource($leaveRequest));
        } catch (QueryException $e) {
            return sendResponse("Error occurred while rejecting leave request.", [], [$e->getMessage()], 422);
        }
    }
    /**
     * @OA\Get(
     *     path="/leave_requests/all",
     *     summary="Get all leave requests",
     *     description="Retrieve all leave requests.",
     *     tags={"HRM"},
     *     @OA\Response(
     *         response=200,
     *         description="Leave Requests",
     *         @OA\JsonContent()
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function all()
    {
        return sendResponse("Leave Requests", LeaveRequestResource::collection(Leave::byOwner()->get()));
    }
}