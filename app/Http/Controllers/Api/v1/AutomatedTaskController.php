<?php

namespace App\Http\Controllers\Api\v1;
use App\Http\Controllers\Controller;


use App\Models\AutomatedTask;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * @OA\Tag(name="Other", description="Automated Task Management")
 * @OA\Controller(description="Manage automated tasks.")
 */
class AutomatedTaskController extends Controller
{
    /**
     * @OA\Get(
     *     path="/automated_tasks",
     *     summary="Retrieve a list of automated tasks.",
     *     tags={"Other"},
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of items per page",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Automated tasks retrieved successfully"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function index(Request $request)
    {
        $perPage = $request->query('per_page', 15);
        $tasks = AutomatedTask::select([
            'id',
            'task_name',
            'trigger_type',
            'trigger_display',
            'trigger_data',
            'action_type',
            'action_display',
            'action_data',
            'status',
            'created_at',
            'updated_at'
        ])->orderBy('created_at', 'desc')->paginate($perPage);

        $tasks->getCollection()->transform(function ($task) {
            $triggerData = is_string($task->trigger_data) ? $task->trigger_data : json_encode($task->trigger_data);
            $actionData = is_string($task->action_data) ? $task->action_data : json_encode($task->action_data);

            return [
                'id'              => $task->id,
                'task_name'       => $task->task_name,
                'trigger_type'    => $task->trigger_type,
                'trigger_display' => $task->trigger_display,
                'trigger_data'    => $triggerData,
                'action_type'     => $task->action_type,
                'action_display'  => $task->action_display,
                'action_data'     => $actionData,
                'status'          => $task->status,
                'created_at'      => $task->created_at ? $task->created_at->toDateTimeString() : null,
                'updated_at'      => $task->updated_at ? $task->updated_at->toDateTimeString() : null,
            ];
        });

        return sendResponse('Automated tasks retrieved successfully', $tasks);
    }

    /**
     * @OA\Post(
     *     path="/automated_tasks/store",
     *     summary="Create a new automated task.",
     *     tags={"Other"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="task_name", type="string", description="Task name", example="My Task"),
     *             @OA\Property(property="trigger_type", type="string", description="Trigger type (event or time)", example="event"),
     *             @OA\Property(property="trigger_display", type="string", description="Trigger display", example="Event Trigger"),
     *             @OA\Property(property="trigger_data", type="string", format="json", description="Trigger data", example="Sample trigger data"),
     *             @OA\Property(property="action_type", type="string", description="Action type (send_email, send_sms, status_update)", example="send_email"),
     *             @OA\Property(property="action_display", type="string", description="Action display", example="Send Email"),
     *             @OA\Property(property="action_data", type="string", format="json", description="Action data", example="Sample action data"),
     *             @OA\Property(property="status", type="string", description="Status (active or inactive)", example="active")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Task created successfully"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function store(Request $request)
    {
        $rules = [
            'task_name'       => 'required|string|max:255',
            'trigger_type'    => 'required|in:event,time',
            'trigger_display' => 'required|string',
            'trigger_data'    => 'required|json',
            'action_type'     => 'required|in:send_email,send_sms,status_update',
            'action_display'  => 'required|string',
            'action_data'     => 'required|json',
            'status'          => 'required|in:active,inactive'
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }

        $task = AutomatedTask::create([
            'task_name'       => $request->input('task_name'),
            'trigger_type'    => $request->input('trigger_type'),
            'trigger_display' => $request->input('trigger_display'),
            'trigger_data'    => json_decode($request->input('trigger_data'), true),
            'action_type'     => $request->input('action_type'),
            'action_display'  => $request->input('action_display'),
            'action_data'     => json_decode($request->input('action_data'), true),
            'status'          => $request->input('status'),
        ]);

        $responseData = $task->toArray();
        $responseData['trigger_data'] = json_encode($task->trigger_data);
        $responseData['action_data'] = json_encode($task->action_data);
        $responseData['created_at'] = $task->created_at ? $task->created_at->toDateTimeString() : null;
        $responseData['updated_at'] = $task->updated_at ? $task->updated_at->toDateTimeString() : null;

        return sendResponse('Task created successfully', $responseData, 201);
    }

    /**
     * @OA\Put(
     *     path="/automated_tasks/update",
     *     summary="Update an existing automated task.",
     *     tags={"Other"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", description="ID of the task to update"),
     *             @OA\Property(property="task_name", type="string", description="Task name"),
     *             @OA\Property(property="trigger_type", type="string", description="Trigger type (event or time)"),
     *             @OA\Property(property="trigger_display", type="string", description="Trigger display"),
     *             @OA\Property(property="trigger_data", type="string", format="json", description="Trigger data"),
     *             @OA\Property(property="action_type", type="string", description="Action type (send_email, send_sms, status_update)"),
     *             @OA\Property(property="action_display", type="string", description="Action display"),
     *             @OA\Property(property="action_data", type="string", format="json", description="Action data"),
     *             @OA\Property(property="status", type="string", description="Status (active or inactive)")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Task updated successfully"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function update(Request $request)
    {
        $rules = [
            'task_name'       => 'sometimes|string|max:255',
            'trigger_type'    => 'sometimes|in:event,time',
            'trigger_display' => 'sometimes|string',
            'trigger_data'    => 'sometimes|json',
            'action_type'     => 'sometimes|in:send_email,send_sms,status_update',
            'action_display'  => 'sometimes|string',
            'action_data'     => 'sometimes|json',
            'status'          => 'sometimes|in:active,inactive'
        ];

        $validator = Validator::make($request->all(), $rules);
        $task = AutomatedTask::find($request->input('id'));
        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }

        $updateData = $request->only([
            'task_name',
            'trigger_type',
            'trigger_display',
            'action_type',
            'action_display',
            'status'
        ]);

        if ($request->has('trigger_data')) {
            $updateData['trigger_data'] = json_decode($request->input('trigger_data'), true);
        }
        if ($request->has('action_data')) {
            $updateData['action_data'] = json_decode($request->input('action_data'), true);
        }

        $task->update($updateData);

        $responseData = $task->toArray();
        $responseData['trigger_data'] = json_encode($task->trigger_data);
        $responseData['action_data'] = json_encode($task->action_data);
        $responseData['updated_at'] = $task->updated_at ? $task->updated_at->toDateTimeString() : null;
        $responseData['created_at'] = $task->created_at ? $task->created_at->toDateTimeString() : null;

        return sendResponse('Task updated successfully', $responseData, 200);
    }

    /**
     * @OA\Post(
     *     path="/automated_tasks/delete",
     *     summary="Delete an automated task.",
     *     tags={"Other"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", description="ID of the task to delete")
     *         )
     *     ),
     *     @OA\Response(
     *         response=204,
     *         description="Task deleted successfully"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function destroy(Request $request)
    {
        AutomatedTask::destroy($request->input('id'));
        return sendResponse('Task deleted successfully', null, 204);
    }

    /**
     * @OA\Post(
     *     path="/automated_tasks/{automatedTask}/run",
     *     summary="Run an automated task.",
     *     tags={"Other"},
     *     @OA\Parameter(
     *         name="automatedTask",
     *         in="path",
     *         description="ID of the task to run",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Task has been queued for execution"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Cannot run an inactive task"
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function run(AutomatedTask $task)
    {
        if ($task->status !== 'active') {
            return sendResponse('Cannot run an inactive task', ['status' => 'Cannot run an inactive task.'], 422, false);
        }

        return sendResponse('Task has been queued for execution', []);
    }
}
