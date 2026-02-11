<?php

namespace App\Http\Controllers\Api\v1;
use App\Http\Controllers\Controller;


use App\Models\ScheduledAction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Jobs\PerformScheduledAction;

/**
 * @OA\Tag(name="Other", description="Scheduled Actions Controller")
 * @OA\Server(url="/api/v1", description="API Server")
 */
class ScheduledActionController extends Controller
{
    /**
     * @OA\Get(
     *     path="/scheduled-actions",
     *     summary="Get all scheduled actions",
     *     description="Retrieve a list of scheduled actions.",
     *     tags={"Other"},
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of items per page",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Scheduled actions retrieved successfully"
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function index(Request $request)
    {
        $perPage = $request->query('per_page', 15);
        $actions = ScheduledAction::orderBy('action_name', 'asc')
                                 ->paginate($perPage);

        $actions->getCollection()->transform(function ($action) {
            return [
                'id'               => $action->id,
                'action_name'      => $action->action_name,
                'action_type'      => $action->action_type,
                'schedule_display' => $action->schedule_display,
                'action_display'   => $action->action_display,
                'last_run_at'      => $action->last_run_at,
                'status'           => $action->status,
                'created_at'       => $action->created_at,
                'updated_at'       => $action->updated_at
            ];
        });

        return sendResponse('Scheduled actions retrieved successfully', $actions);
    }

    /**
     * @OA\Post(
     *     path="/scheduled-actions",
     *     summary="Create a new scheduled action",
     *     description="Create a new scheduled action.",
     *     tags={"Other"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 @OA\Property(property="action_name", type="string", maxLength=255, example="My Action"),
     *                 @OA\Property(property="schedule_display", type="string", example="Every minute"),
     *                 @OA\Property(property="cron_expression", type="string", example="* * * * *"),
     *                 @OA\Property(property="action_type", type="string", example="email"),
     *                 @OA\Property(property="action_display", type="string", example="Send Email"),
     *                 @OA\Property(property="action_payload", type="string", format="json", nullable=true, example="{}"),
     *                 @OA\Property(property="status", type="string", enum={"active", "inactive"}, example="active")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Scheduled action created successfully"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation errors"
     *     ),
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function store(Request $request)
    {
        $rules = [
            'action_name'      => 'required|string|max:255',
            'schedule_display' => 'required|string',
            'cron_expression'  => 'required|string',
            'action_type'      => 'required',
            'action_display'   => 'required|string',
            'action_payload'   => 'nullable|json',
            'status'           => 'required|in:active,inactive'
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }

        $action = ScheduledAction::create([
            'action_name'      => $request->input('action_name'),
            'schedule_display' => $request->input('schedule_display'),
            'cron_expression'  => $request->input('cron_expression'),
            'action_type'      => $request->input('action_type'),
            'action_display'   => $request->input('action_display'),
            'action_payload'   => $request->has('action_payload')
                                  ? json_decode($request->input('action_payload'), true)
                                  : null,
            'status'           => $request->input('status'),
        ]);

        return sendResponse('Scheduled action created successfully', [
            'id'               => $action->id,
            'action_name'      => $action->action_name,
            'action_type'      => $action->action_type,
            'schedule_display' => $action->schedule_display,
            'action_display'   => $action->action_display,
            'last_run_at'      => $action->last_run_at,
            'status'           => $action->status,
            'created_at'       => $action->created_at,
            'updated_at'       => $action->updated_at
        ], true, [], 201);
    }

    /**
     * @OA\Get(
     *     path="/scheduled-actions/{id}",
     *     summary="Get a single scheduled action",
     *     description="Retrieve a single scheduled action by ID.",
     *     tags={"Other"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of the scheduled action",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Scheduled action retrieved successfully"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Scheduled action not found"
     *     ),
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function show(ScheduledAction $scheduledAction)
    {
        return response()->json($scheduledAction);
    }

    /**
     * @OA\Put(
     *     path="/scheduled-actions",
     *     summary="Update a scheduled action",
     *     description="Update a scheduled action.",
     *     tags={"Other"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="action_name", type="string", maxLength=255, example="Updated Action"),
     *                 @OA\Property(property="schedule_display", type="string", example="Every hour"),
     *                 @OA\Property(property="cron_expression", type="string", example="0 * * * *"),
     *                 @OA\Property(property="action_type", type="string", example="email"),
     *                 @OA\Property(property="action_display", type="string", example="Send Email"),
     *                 @OA\Property(property="status", type="string", enum={"active", "inactive"}, example="active")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Scheduled action updated successfully"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Scheduled action not found"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation errors"
     *     ),
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function update(Request $request)
    {
        $id = $request->input('id');
        $scheduledAction = ScheduledAction::find($id);

        if (!$scheduledAction) {
            return response()->json([
                'error' => 'Scheduled action not found'
            ], 404);
        }

        $rules = [
            'action_name'      => 'required|string|max:255',
            'schedule_display' => 'required|string',
            'cron_expression'  => 'required|string',
            'action_type'      => 'required',
            'action_display'   => 'required|string',
            'status'           => 'required|in:active,inactive'
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }

        // التحقق من صحة cron expression
        if (!$this->isValidCron($request->cron_expression)) {
            return response()->json([
                'errors' => ['cron_expression' => ['Invalid cron expression format']]
            ], 422);
        }

        $updateData = $request->only([
            'action_name',
            'schedule_display',
            'cron_expression',
            'action_type',
            'action_display',
            'status'
        ]);

        $scheduledAction->update($updateData);

        return response()->json([
            'success' => true,
            'message' => 'Scheduled action updated successfully',
            'data' => $scheduledAction
        ], 200);
    }

    private function isValidCron($expression)
    {
        $parts = preg_split('/\\s+/', trim($expression));
        return count($parts) === 5;
    }

    /**
     * @OA\Post(
     *     path="/scheduled-actions/delete",
     *     summary="Delete a scheduled action",
     *     description="Delete a scheduled action.",
     *     tags={"Other"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 @OA\Property(property="id", type="integer", example=1)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=204,
     *         description="Scheduled action deleted successfully"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Scheduled action not found"
     *     ),
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function destroy(Request $request)
    {
        $id = $request->input('id');
        $scheduledAction = ScheduledAction::find($id);
        if (!$scheduledAction) {
            return response()->json([
                'error' => 'Scheduled action not found'
            ], 404);
        }
        $scheduledAction->delete();
        return sendResponse('Scheduled Action Deleted Successfully', null, 204);
    }

    /**
     * @OA\Post(
     *     path="/scheduled-actions/{id}/run",
     *     summary="Run a scheduled action",
     *     description="Run a scheduled action.",
     *     tags={"Other"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of the scheduled action",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Scheduled action queued for execution"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Cannot run an inactive scheduled action."
     *     ),
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function run(ScheduledAction $scheduledAction)
    {
        if ($scheduledAction->status !== 'active') {
            return response()->json([
                'error' => 'Cannot run an inactive scheduled action.'
            ], 422);
        }

        dispatch(new PerformScheduledAction($scheduledAction));

        return sendResponse("Scheduled Action #{$scheduledAction->id} has been queued for execution.", [], true, [], 200);
    }
}
