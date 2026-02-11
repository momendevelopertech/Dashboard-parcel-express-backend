<?php

namespace App\Http\Controllers\Api\v1;
use App\Http\Controllers\Controller;


use App\Http\Resources\QualityCheckResource;
use App\Models\DriverNotification;
use App\Models\DriverWarning;
use App\Models\Shipment;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * @OA\Tag(name="Fleet & Driver Management", description="API endpoints for managing fleet and driver related data")
 * @OA\Controller(description="Quality Check Controller")
 */
class QualityCheckController extends Controller
{
    /**
     * @OA\Post(
     *     path="/quality_check",
     *     summary="Get shipments for quality check",
     *     tags={"Fleet & Driver Management"},
     *     @OA\Parameter(
     *         name="trackingNo",
     *         in="query",
     *         description="Shipment tracking number",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="driver",
     *         in="query",
     *         description="Driver ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="delivery_exception",
     *         in="query",
     *         description="Delivery exception type",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Shipments retrieved successfully",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad Request"
     *     ),
     *     security={{"bearerAuth":{}}}
     * )
     */
    /**
     * @OA\Get(
     *     path="/api/quality_check",
     *     summary="Get quality check shipments with filters",
     *     tags={"Quality Check"},
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of items per page",
     *         required=false,
     *         @OA\Schema(type="integer", default=8)
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number",
     *         required=false,
     *         @OA\Schema(type="integer", default=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Shipments retrieved successfully",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad Request"
     *     ),
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function index(Request $request)
    {
        $isActuallyFilled = function ($val) {
            return !in_array($val, [null, '', 'undefined'], true);
        };
        $shipments = Shipment::whereHas('runsheet_shipment')
            ->with('shipmentHistories', 'core_status', 'core_exception', 'current_assignment.driver', 'shipment_delivery');

        $tracking_no = $request->input('trackingNo');
        if ($isActuallyFilled($tracking_no)) {
            $shipments->where('tracking_no', $tracking_no);
        }
        $driver_id = $request->input('driver');
        if ($isActuallyFilled($driver_id)) {
            $shipments->whereHas('driverAssignments', function ($q) use ($driver_id) {
                $q->where('driver_id', $driver_id);
            });
        }
        $delivery_exception = $request->input('delivery_exception');
        if ($isActuallyFilled($delivery_exception)) {
            $shipments->whereHas('shipmentHistories', function ($q) use ($delivery_exception) {
                $q->where('type', $delivery_exception);
            });
        }
          $from = $request->query('from');        // YYYY-MM-DD
        $to = $request->query('to');            // YYYY-MM-DD
        $fromTime = $request->query('from_time'); // HH:mm
        $toTime = $request->query('to_time');     // HH:mm

        if ($isActuallyFilled($from) && $isActuallyFilled($to)) {
            try {
                $fromAt = Carbon::createFromFormat('Y-m-d H:i', ($fromTime ? "$from $fromTime" : "$from 00:00"))->startOfDay();
                $toAt = Carbon::createFromFormat('Y-m-d H:i', ($toTime ? "$to $toTime" : "$to 23:59"))->endOfDay();

                // الفلترة الافتراضية على shipments.created_at
                $shipments->whereBetween('created_at', [$fromAt, $toAt]);

                // إن أردت بدلاً من ذلك الفلترة على وقت آخر (مثلاً وقت الاستثناء):
                // $shipments->whereHas('shipmentHistories', function($q) use ($fromAt, $toAt) {
                //     $q->whereBetween('time', [$fromAt, $toAt]);
                // });
            } catch (\Exception $e) {
                // تجاهل/أو أعد خطأ تنسيق – هنا هنكمّل بدون فلترة زمنية
            }
        }
        $perPage = $request->query('per_page', 8);
        // First get all drivers with their warning counts
        $driverWarnings = DriverWarning::select('driver_id', \DB::raw('COUNT(*) as total_warnings'))
            ->groupBy('driver_id')
            ->pluck('total_warnings', 'driver_id');

        $shipments = $shipments->withCount(['shipment_fine', 'driver_notifications'])
            ->with(['current_assignment.driver'])
            ->orderBy('id', 'desc')
            ->paginate($perPage);

        // ضيف دي قبل الـtransform الحالي أو بعدها (الاتنين شغال):
        $shipments->getCollection()->each->append(['warehouse']);

        // انت عندك transform للwarnings_count — خليه زي ما هو
        $shipments->getCollection()->transform(function ($shipment) use ($driverWarnings) {
            $shipment->warnings_count = 0;
            if ($shipment->current_assignment && $shipment->current_assignment->driver) {
                $driverId = $shipment->current_assignment->driver->id;
                $shipment->warnings_count = $driverWarnings->get($driverId, 0);
            }
            return $shipment;
        });

      


   

        // Add total warnings count for each driver
        $shipments->getCollection()->transform(function ($shipment) use ($driverWarnings) {
            $shipment->warnings_count = 0;
            if ($shipment->current_assignment && $shipment->current_assignment->driver) {
                $driverId = $shipment->current_assignment->driver->id;
                $shipment->warnings_count = $driverWarnings->get($driverId, 0);
            }
            return $shipment;
        });

        return sendResponse("Shipments retrieved successfully.", new QualityCheckResource(['shipments' => $shipments]), []);
    }

    /**
     * @OA\Post(
     *     path="/quality_check/send_warning",
     *     summary="Send warning to driver",
     *     tags={"Fleet & Driver Management"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="shipment_tracking_no", type="string", description="Shipment tracking number", example="12345"),
     *             @OA\Property(property="driver_id", type="integer", description="Driver ID", example="1"),
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Warning sent successfully",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal Server Error",
     *         @OA\JsonContent()
     *     ),
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function send_warning(Request $request)
    {
        DB::beginTransaction();
        try {
            $data = $request->validate([
                "shipment_tracking_no" => "required",
                "driver_id" => "required"
            ]);
            $data['title'] = "Warning";
            $data['content'] = "Please take care. don't do any kind of fraud otherwise you will be fined";
            $data['type'] = "warning";
            DriverNotification::create($data);
            DriverWarning::create($data);
            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            return sendResponse("Error.", [], false, [$e->getMessage()], 500);
        }
        return sendResponse("Warning sent.", ["Warning sent."]);
    }
}
