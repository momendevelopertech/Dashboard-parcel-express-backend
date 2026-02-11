<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Services\ReversePickup\ReversePickupTaskService;
use App\Models\ReversePickupTask;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ReversePickupTaskController extends Controller
{
    public function __construct(
        private ReversePickupTaskService $taskService
    ) {}

    /**
     * List all reverse pickup tasks (admin)
     * GET /api/v1/admin/reverse-pickup/tasks
     */
    public function index(Request $request): JsonResponse
    {
        $query = ReversePickupTask::with(['merchant', 'driver', 'shipments.reverseShipment'])
            ->orderBy('created_at', 'desc');

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        // Filter by driver
        if ($request->has('driver_id')) {
            $query->where('driver_id', $request->input('driver_id'));
        }

        // Filter by merchant
        if ($request->has('merchant_id')) {
            $query->where('merchant_id', $request->input('merchant_id'));
        }

        $tasks = $query->paginate(15);

        return $this->sendResponse($tasks, 'Reverse pickup tasks retrieved successfully');
    }

    /**
     * Create a new reverse pickup task and assign to driver (Admin assigns after request created)
     * POST /api/v1/admin/reverse-pickup/tasks
     */

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'reverse_pickup_request_id' => 'required|exists:reverse_pickup_requests,id',
            'driver_id' => 'required|exists:users,id',
            'assignment_mode' => 'required|in:bulk,per_customer,manual',
            'reverse_shipment_ids' => 'required_if:assignment_mode,manual|array|min:1',
            'reverse_shipment_ids.*' => 'required|integer|exists:reverse_shipments,id',
            'note' => 'nullable|string|max:1000',
        ]);

        try {
            $service = app(\App\Services\ReversePickup\ReverseShipmentService::class);

            return DB::transaction(function () use ($validated, $service,$request) {

                /**
                 * 1️⃣ Assign reverse pickup task
                 * Return leg creation happens at hub intake.
                 */
                if ($validated['assignment_mode'] === 'bulk') {

                    $task = $service->assignToDriver(
                        $validated['reverse_pickup_request_id'],
                        $validated['driver_id']
                    );

                } elseif ($validated['assignment_mode'] === 'manual') {

                    $task = $service->assignSpecificShipmentsToDriver(
                        $validated['reverse_pickup_request_id'],
                        $validated['driver_id'],
                        $validated['reverse_shipment_ids']
                    );

                } else {
                    $groupBy = $validated['group_by'] ?? 'customer_phone';

                    $task = $service->assignToDriverPerCustomer(
                        $validated['reverse_pickup_request_id'],
                        $validated['driver_id'],
                        $groupBy
                    );
                }

                return $this->sendResponse(
                    $task,
                    'Reverse pickup task created successfully',
                    201
                );
            });

        } catch (\Throwable $e) {
            return $this->sendError(
                'Failed to create reverse pickup task: ' . $e->getMessage(),
                [],
                500
            );
        }
    }


    /**
     * Show single reverse pickup task
     * GET /api/v1/admin/reverse-pickup/tasks/{id}
     */
    public function show(int $id): JsonResponse
    {
        $task = ReversePickupTask::with([
            'merchant',
            'driver',
            'shipments.reverseShipment.transactions',
            'reversePickupRequest',
            'assignedBy'
        ])->findOrFail($id);

        return $this->sendResponse($task, 'Reverse pickup task retrieved successfully');
    }

    /**
     * Update reverse pickup task (reassign driver, etc.)
     * PUT /api/v1/admin/reverse-pickup/tasks/{id}
     */
    public function update(int $id, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'driver_id' => 'sometimes|required|exists:users,id',
            'status' => 'sometimes|required|in:pending,to_pickup,pickup_completed,cancelled',
            'note' => 'nullable|string|max:1000',
            'scheduled_at' => 'nullable|date',
        ]);

        try {
            $task = ReversePickupTask::findOrFail($id);
            $task->update($validated);

            // If driver reassigned, update all shipments
            if (isset($validated['driver_id']) && $validated['driver_id'] !== $task->driver_id) {
                $task->shipments()->update([
                    'driver_id' => $validated['driver_id'],
                ]);
            }

            return $this->sendResponse(
                $task->load(['merchant', 'driver', 'shipments']),
                'Reverse pickup task updated successfully'
            );
        } catch (\Exception $e) {
            return $this->sendError('Failed to update reverse pickup task: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Send response helper
     */
    protected function sendResponse($data, string $message, int $code = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $code);
    }

    /**
     * Send error helper
     */
    protected function sendError(string $message, array $errors = [], int $code = 404): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ], $code);
    }
}
