<?php

namespace App\Http\Controllers\Api\v1;


use App\Enums\ShipmentStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\Shipment;
use App\Models\CrmTask;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Http\Resources\CrmTaskResource;
use Illuminate\Database\QueryException;
use App\Http\Requests\StoreCrmTaskRequest;
use App\Http\Requests\UpdateCrmTaskRequest;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * @OA\Tag(name="CRM", description="CRM functionalities")
 * @OA\Server(url="/api")
 */
class CrmTaskController extends Controller
{
    /**
     * @OA\Get(
     *     path="/crm_tasks",
     *     summary="Get all CRM tasks",
     *     description="Retrieves a list of CRM tasks. Allows querying by tracking number.",
     *     tags={"CRM"},
     *     @OA\Parameter(
     *         name="query",
     *         in="query",
     *         description="Query string to search by tracking number",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="CRM Task retrieved successfully.",
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    // helper عام
    function upsertOpenCrmTaskForShipment(Shipment $shipment, array $data = [])
    {
        // تعريف الحالات المفتوحة
        $openStatuses = ['created', 'to_call', 'hold'];

        $task = \App\Models\CrmTask::where('shipment_id', $shipment->id)
            ->whereIn('status', $openStatuses)
            ->latest('id')
            ->first();

        if ($task) {
            // حدّث العنوان/الملاحظات بدل ما تضيف صف جديد
            $task->fill([
                'title' => $data['title'] ?? $task->title,
                'note' => $data['note'] ?? $task->note,
                'updated_by' => auth()->id(),
            ])->save();
            return $task;
        }

        // مفيش مهمة مفتوحة → أنشئ واحدة
        return \App\Models\CrmTask::create([
            'shipment_id' => $shipment->id,
            'title' => $data['title'] ?? 'Needs Attention',
            'note' => $data['note'] ?? null,
            'status' => 'created',
            'owner_id' => $shipment->owner_id,
            'owner_type' => $shipment->owner_type,
            'updated_by' => auth()->id(),
        ]);
    }

    public function index()
    {
        $perPage = (int) request()->query('per_page', 8);

        $enableDate = request()->boolean('date_filter', false);

        $allowedDateFields = ['created_at', 'updated_at'];
        $dateField = request()->query('date_field', 'created_at');
        if (!in_array($dateField, $allowedDateFields, true)) {
            $dateField = 'created_at';
        }

        $from = request()->query('from');
        $to = request()->query('to');
        $fromTime = request()->query('from_time', '00:00');
        $toTime = request()->query('to_time', '23:59');

        $tz = 'Africa/Cairo';
        $start = $end = null;

        if ($enableDate) {
            if ($from && !$to)
                $to = $from;
            if ($to && !$from)
                $from = $to;

            try {
                if ($from) {
                    $start = Carbon::parse("{$from} {$fromTime}:00", $tz)->timezone('UTC');
                }
                if ($to) {
                    $end = Carbon::parse("{$to} {$toTime}:59", $tz)->timezone('UTC');
                }
                if ($start && $end && $start->gt($end)) {
                    [$start, $end] = [$end, $start];
                }
            } catch (\Throwable $e) {
                $start = $end = null;
            }
        }

        $crmTasks = CrmTask::query()
            ->with([
                'shipment.merchant',
                'shipment.shipper',
                'shipment.consignee',
                'shipment.assigned_to_shelf.shelf.owner',
                'shipment.core_status',
                'shipment.shipmentHistories',
                'shipment.destinationOwner',
                'shipment.finalOwner',
                'shipment.currentOwner',
                'shipment.fromOwner',
            ]);

        if (request()->filled('query')) {
            $query = request()->input('query');
            $crmTasks->whereHas('shipment', function ($q) use ($query) {
                $q->where('tracking_no', 'like', "%{$query}%");
            });
        }

        if (request()->filled('status')) {
            $crmTasks->where('status', request()->input('status'));
        }

        if (request()->filled('workspace_key')) {
            $workspaceKey = request()->input('workspace_key');
            $workspaceType = request()->input('workspace_type'); // optional

            $crmTasks->whereHas('shipment', function ($q) use ($workspaceKey, $workspaceType) {
                $q->where('destination_owner_id', $workspaceKey);

                // لو عايز تتأكد من الـ type كمان
                if ($workspaceType) {
                    $q->where('destination_owner_type', $workspaceType);
                }
            });
        }

        if ($enableDate) {
            $crmTasks->when($start && $end, function ($qb) use ($dateField, $start, $end) {
                $table = $qb->getModel()->getTable();
                $qb->where(function ($group) use ($table, $dateField, $start, $end) {
                    $group
                        ->whereBetween("{$table}.{$dateField}", [$start, $end])
                        ->orWhereHas('shipment.core_exception', function ($e) use ($start, $end) {
                            $e->whereBetween(DB::raw('COALESCE(time, updated_at)'), [$start, $end]);
                        })
                        ->orWhereHas('shipment.shipmentHistories', function ($h) use ($start, $end) {
                            $h->whereBetween(DB::raw('COALESCE(time, updated_at)'), [$start, $end]);
                        });
                });
            })
                ->when($start && !$end, function ($qb) use ($dateField, $start) {
                    $table = $qb->getModel()->getTable();
                    $qb->where(function ($group) use ($table, $dateField, $start) {
                        $group
                            ->where("{$table}.{$dateField}", '>=', $start)
                            ->orWhereHas('shipment.core_exception', function ($e) use ($start) {
                                $e->where(DB::raw('COALESCE(time, updated_at)'), '>=', $start);
                            })
                            ->orWhereHas('shipment.shipmentHistories', function ($h) use ($start) {
                                $h->where(DB::raw('COALESCE(time, updated_at)'), '>=', $start);
                            });
                    });
                })
                ->when(!$start && $end, function ($qb) use ($dateField, $end) {
                    $table = $qb->getModel()->getTable();
                    $qb->where(function ($group) use ($table, $dateField, $end) {
                        $group
                            ->where("{$table}.{$dateField}", '<=', $end)
                            ->orWhereHas('shipment.core_exception', function ($e) use ($end) {
                                $e->where(DB::raw('COALESCE(time, updated_at)'), '<=', $end);
                            })
                            ->orWhereHas('shipment.shipmentHistories', function ($h) use ($end) {
                                $h->where(DB::raw('COALESCE(time, updated_at)'), '<=', $end);
                            });
                    });
                });
        }

        $crmTasks = $crmTasks
            ->orderByDesc($crmTasks->getModel()->getTable() . '.id')
            ->paginate($perPage)
            ->appends(request()->query());

        return sendResponse("CRM Task retrieved successfully.", $crmTasks, true, []);
    }

    /**
     * @OA\Get(
     *     path="/crm_tasks/get-by-status",
     *     summary="Get CRM tasks by status",
     *     description="Retrieves CRM tasks filtered by status. Returns total count and counts for each allowed status.",
     *     tags={"CRM"},
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Status of the CRM tasks (created, to_call, hold, closed)",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="CRM tasks retrieved successfully.",
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Invalid status provided.",
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function getTasksByStatus(Request $request)
    {
        $allowedStatuses = ['created', 'to_call', 'hold', 'closed'];
        $status = $request->query('status', 'created');
        if (!in_array($status, $allowedStatuses)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid status provided.',
            ], 422);
        }
        $crmTasks = CrmTask::byOwner()
            ->with(['shipment.merchant', 'shipment.shipper', 'shipment.consignee'])
            ->where('status', $status)
            ->orderBy('id', 'desc')
            ->get();
        $count = $crmTasks->count();
        $counts = CrmTask::byOwner()
            ->select('status', DB::raw('count(*) as count'))
            ->whereIn('status', $allowedStatuses)
            ->groupBy('status')
            ->get();
        return sendResponse(
            "CRM tasks with status '{$status}' retrieved successfully.",
            [
                'tasks' => CrmTaskResource::collection($crmTasks),
                'count' => $count,
                'counts' => $counts,
            ],
            true,
            []
        );
    }

    public function store(StoreCrmTaskRequest $request)
    {
        try {
            $crmTask = CrmTask::create($request->validated());
            return sendResponse("CRM Task created successfully.", new CrmTaskResource($crmTask));
        } catch (QueryException $e) {
            return sendResponse("Error occurred while creating scenario.", [], [$e->getMessage()], 422);
        }
    }
    public function update(UpdateCrmTaskRequest $request)
    {
        try {
            $crmTask = CrmTask::findOrFail($request->id);
            $crmTask->update($request->validated());
            return sendResponse("CRM Task updated successfully.", new CrmTaskResource($crmTask));
        } catch (QueryException $e) {
            return sendResponse("Error occurred while updating scenario.", [], [$e->getMessage()], 422);
        }
    }
    public function delete(Request $request)
    {
        try {
            CrmTask::where('id', $request->id)->delete();
        } catch (QueryException $e) {
            return sendResponse("Error Occured.", [], [$e->getMessage()], 422);
        }
        return sendResponse("CRM Task deleted successfully.", []);
    }

    /**
     * @OA\Post(
     *     path="/crm_tasks/change_status",
     *     summary="Change status of CRM tasks",
     *     description="Changes the status of multiple CRM tasks.",
     *     tags={"CRM"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="task_ids", type="array", @OA\Items(type="integer"), description="Array of task IDs"),
     *             @OA\Property(property="status", type="string", description="New status of the tasks"),
     *             @OA\Property(property="note", type="string", description="Note for the status change (optional)"),
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Tasks updated successfully.",
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error occurred while updating tasks.",
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function change_status(Request $request)
    {

        $request->validate([
            'task_ids' => 'required|array',
            'task_ids.*' => 'integer|exists:crm_tasks,id',
            'status' => 'required|string',
            'note' => 'nullable|string',
        ]);

        try {

            CrmTask::whereIn('id', $request->task_ids)->update([
                'status' => $request->status,
                'updated_by' => Auth::id(),
            ]);

            return sendResponse("Tasks updated successfully.", []);
        } catch (QueryException $e) {

            return sendResponse("Error occurred while updating tasks.", [], [$e->getMessage()]);
        }
    }

    /**
     * @OA\Post(
     *     path="/crm_tasks/change_shipment_status",
     *     summary="Change shipment status",
     *     description="Changes the shipment status and updates related CRM tasks.",
     *     tags={"CRM"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="shipment_id", type="integer", description="ID of the shipment"),
     *             @OA\Property(property="status", type="string", description="New status of the shipment"),
     *             @OA\Property(property="note", type="string", description="Note for the status change (optional)"),
     *             @OA\Property(property="date", type="string", format="date", description="Reschedule date (optional)"),
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Complaint status updated successfully.",
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error Occurred.",
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */

    public function change_shipment_status(Request $request)
    {
        try {
            DB::beginTransaction();

            $shipment_id = $request->shipment_id;
            $note = (string) $request->note;
            $status = ShipmentStatusEnum::normalize((string) $request->status); // ex: RESCHEDULE, RTO, DELIVERED...
            $reschedule_date = trim((string) $request->date); // YYYY-MM-DD أو datetime

            $shipment = Shipment::where("id", $shipment_id)->with('shipment_delivery', 'consignee')->first();
            if (!$shipment) {
                return sendResponse("Shipment not found.", [], []);
            }

            // لو مش Reschedule نمشي بالمسار القديم:
            if ($status !== 'RESCHEDULE') {
                $shipmentStatus = updateShipmentStatus($shipment_id, $status);

                if ($shipmentStatus) {
                    // دي كانت بتصفر in_exception وتقفل الـ CRM Task
                    Shipment::where("id", $shipment_id)->update([
                        'in_exception' => false
                    ]);

                    CrmTask::where('shipment_id', $shipment_id)->update([
                        'status' => 'closed',
                        'note' => $note,
                        'updated_by' => Auth::id(),
                    ]);
                }

                $description = "Status has been changed by CRM to {$status} with message: ";
                if (!empty($note)) {
                    $description .= "[$note]";
                }

                // History
                shipmentHistory([
                    "shipment_id" => $shipment_id,
                    "status" => $status,
                    "description" => $description,
                ]);

                DB::commit();
                return sendResponse("Complaint status updated successfully.", []);
            }

            // ====== حالة RESCHEDULE ======
            if (empty($reschedule_date)) {
                DB::rollBack();
                return sendResponse("Reschedule date is required for RESCHEDULE.", [], [], 422);
            }

            // حدّد التايمزون (لو عندك تايمزون في الconsignee استعمله)
            $customerTz = $shipment->consignee->timezone ?? 'Africa/Cairo';

            // Parse التاريخ: نخزنه كـ بداية اليوم في تايمزون العميل
            $futureLocal = Carbon::parse($reschedule_date, $customerTz)->startOfDay();
            $todayLocal = Carbon::now($customerTz)->startOfDay();

            if ($futureLocal->lte($todayLocal)) {
                DB::rollBack();
                return sendResponse("Reschedule date must be in the future.", [], [], 422);
            }

            // فرّق الأيام
            $daysDiff = $todayLocal->diffInDays($futureLocal);

            // حدّد subtype المطلوب
            // اليوم نفسه (0) => DELIVER_LATER_TODAY (اختياري)
            // غداً (1)      => TOMORROW
            // أكتر من يوم   => FUTURE_DELIVERY
            $subtype = null;
            if ($daysDiff === 1) {
                $subtype = 'TOMORROW';
            } elseif ($daysDiff > 1) {
                $subtype = 'FUTURE_DELIVERY';
            } else {
                // نفس اليوم (لو سمحت بالسيناريو ده)
                $subtype = 'DELIVER_LATER_TODAY';
            }

            // خزّن التاريخ UTC في shipment_delivery
            $futureUtc = $futureLocal->clone()->utc();

            if ($shipment->shipment_delivery) {
                // امسح أي later-today سابق
                $shipment->shipment_delivery->deliver_later_until = null;
                $shipment->shipment_delivery->deliver_later_reason = null;

                // خزّن تاريخ التسليم المستقبلي
                $shipment->shipment_delivery->future_delivery_date = $futureUtc;
                $shipment->shipment_delivery->save();
            }

            // فعّل in_exception لأننا عملنا Reschedule (استثناء تشغيلي)
            $shipment->in_exception = true;
            $shipment->save();

            // سجل الهستوري كاستثناء تشغيلي
            $futureStrLocal = $futureLocal->format('Y-m-d');
            $historyDescription = "Marked as DELIVERY_EXCEPTION [{$subtype}] (Rescheduled day: {$futureStrLocal} {$customerTz})";
            if (!empty($note)) {
                $historyDescription .= " - Note: {$note}";
            }

            shipmentHistory([
                "shipment_id" => $shipment->id,
                "status" => ShipmentStatusEnum::DELIVERY_EXCEPTION,
                "type" => $subtype, // TOMORROW / FUTURE_DELIVERY / DELIVER_LATER_TODAY
                "description" => $historyDescription,
                "data" => json_encode([
                    "reschedule_date_local" => $futureStrLocal,
                    "reschedule_date_utc" => $futureUtc->toDateTimeString(),
                    "timezone" => $customerTz,
                    "note" => $note,
                ]),
            ]);

            // لو حابب تحدث core_exception نفسه (اختياري)
            // مثال: أحدث/أنشئ الـ core_exception باسم subtype وخلي time= futureUtc
            try {
                $core = $shipment->core_exception; // eager loaded؟ لو لأ: $shipment->load('core_exception');
                if ($core) {
                    $core->type = $subtype;
                    $core->time = $futureUtc; // أو خليه null ودع التاريخ في الhistory فقط
                    $core->description = $historyDescription;
                    $core->save();
                }
            } catch (\Throwable $e) {
                \Log::warning('Failed updating core_exception on reschedule', [
                    'shipment_id' => $shipment->id,
                    'error' => $e->getMessage(),
                ]);
            }

            // اختياري: ما تقفلش الـ CRM Task في حالة reschedule (سيبه مفتوح للمتابعة)
            CrmTask::where('shipment_id', $shipment->id)->where('status', '!=', 'closed')->update([
                'status' => 'hold', // أو "to_call" حسب تدفقك
                'note' => $note,
                'updated_by' => Auth::id(),
            ]);

            \App\Models\DriverRunsheetShipment::where("shipment_tracking_no", $shipment->tracking_no)
                ->update(["status" => "not_delivered"]);
            \App\Models\DriverShipmentAssignment::where('shipment_id', $shipment->id)
                ->update(['delivered_at' => null, "confirmed_at" => null]);

            DB::commit();
            return sendResponse("Shipment rescheduled successfully.", []);
        } catch (\Throwable $e) {
            DB::rollBack();
            return sendResponse("Error Occurred.", [], [$e->getMessage()], 500);
        }
    }
}
