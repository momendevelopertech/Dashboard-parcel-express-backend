<?php

namespace App\Http\Controllers\Api\v1;
use App\Http\Controllers\Controller;


use App\Exports\ReturnExport;
use App\Models\Shipment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * @OA\Tag(name="OMS", description="Shipment Management System")
 * @OA\Server(url="/api")
 */
class ReturnController extends Controller
{

    /**
     * @OA\Get(
     *     path="/returns",
     *     summary="Get a list of return requests",
     *     description="Retrieves a paginated list of return requests with filtering and sorting options.",
     *     tags={"OMS"},
     *     @OA\Parameter(
     *         name="query",
     *         in="query",
     *         description="Search query for tracking number or consignee name",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="from_date",
     *         in="query",
     *         description="Filter returns from this date",
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="to_date",
     *         in="query",
     *         description="Filter returns to this date",
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Return requests retrieved successfully",
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *     ),
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function index(Request $request)
    {
        $query = $request->query('query');
        $fromDate = $request->query('from_date');
        $toDate = $request->query('to_date');

        $returnsQuery = Shipment::where('in_exception', true);
        if ($query) {
            $returnsQuery->where(function ($q) use ($query) {
                $q->where('tracking_no', 'like', "%{$query}%")
                    ->orWhereHas('consignee', function ($subq) use ($query) {
                        $subq->where('name', 'like', "%{$query}%");
                    });
            });
        }
        if ($fromDate && $toDate) {
            $returnsQuery->whereBetween('created_at', [
                Carbon::parse($fromDate)->startOfDay(),
                Carbon::parse($toDate)->endOfDay()
            ]);
        } elseif ($fromDate) {
            $returnsQuery->where('created_at', '>=', Carbon::parse($fromDate)->startOfDay());
        } elseif ($toDate) {
            $returnsQuery->where('created_at', '<=', Carbon::parse($toDate)->endOfDay());
        }
        $returnsQuery->with([
            'shipper:id,name,country_id,state_id,contact,zip_code,address',
            'shipper.country:id,name',
            'shipper.state:id,en_name,ar_name',

            'merchant:id,name',
            'merchant.merchant.country:id,name',
            'merchant.merchant.governorate:id,en_name,ar_name',
            'merchant.merchant.state:id,en_name,ar_name',
            'merchant.merchant.place:id,en_name,ar_name',

            'consignee:id,name,country_id,governorate_id,state_id,place_id,streetAddress,cellphone,alternatePhone',
            'consignee.country:id,name',
            'consignee.governorate:id,en_name,ar_name',
            'consignee.state:id,en_name,ar_name',
            'consignee.place:id,en_name,ar_name',

            'shipmentHistories' => function ($query) {
                $query->orderBy('created_at', 'desc');
            },
        ])->orderByDesc('created_at');

        $returns = $returnsQuery->paginate(8);

        if ($returns->isEmpty()) {
            return sendResponse("No Record.", [], false, ['no record']);
        }
        $transformedReturns = $returns->through(function ($shipment) {
            $latestHistory = $shipment->shipmentHistories->first();
            return [
                'id' => $shipment->id,
                'return_id' => $shipment->id,
                'shipment_tracking_no' => $shipment->tracking_no,
                'customer_name' => $shipment->consignee->name ?? 'Unknown',
                'reason' => $latestHistory ? $latestHistory->description : 'Return requested',
                'status' => $shipment->status,
                'created_at' => $latestHistory ? $latestHistory->created_at : $shipment->created_at,
                'updated_at' => $shipment->updated_at,
                'shipment' => $shipment,
            ];
        });

        return sendResponse("Return requests retrieved successfully.", [
            'returns' => $transformedReturns,
        ]);
    }


    /**
     * @OA\Get(
     *     path="/returns/show/{id}",
     *     summary="Get return request details",
     *     description="Retrieves details for a specific return request.",
     *     tags={"OMS"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of the return request",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Return request details",
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Return request not found or not marked as a return",
     *     ),
     *      @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *     ),
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function show($id)
    {
        $shipment = Shipment::with([
            'shipper:id,name,country_id,state_id,contact,zip_code,address',
            'shipper.country:id,name',
            'shipper.state:id,en_name,ar_name',

            'merchant:id,name',
            'merchant.merchant.country:id,name',
            'merchant.merchant.governorate:id,en_name,ar_name',
            'merchant.merchant.state:id,en_name,ar_name',
            'merchant.merchant.place:id,en_name,ar_name',

            'consignee:id,name,country_id,governorate_id,state_id,place_id,streetAddress,cellphone,alternatePhone',
            'consignee.country:id,name',
            'consignee.governorate:id,en_name,ar_name',
            'consignee.state:id,en_name,ar_name',
            'consignee.place:id,en_name,ar_name',

            'shipmentHistories' => function ($query) {
                $query->where('type', 'RETURN')->orderBy('created_at', 'desc');
            }
        ])->findOrFail($id);

        if (!$shipment->in_exception) {
            return sendResponse("This shipment is not marked as a return.", [], false, ['not a return'], 404);
        }

        // Transform the data to match the expected format in the frontend
        $latestHistory = $shipment->shipmentHistories->first();
        $returnData = [
            'id' => $shipment->id,
            'return_id' => 'RET-' . $shipment->id,
            'shipment_tracking_no' => $shipment->tracking_no,
            'customer_name' => $shipment->consignee->name ?? 'Unknown',
            'reason' => $latestHistory ? $latestHistory->description : 'Return requested',
            'notes' => $latestHistory ? $latestHistory->notes : null,
            'status' => $shipment->status,
            'created_at' => $latestHistory ? $latestHistory->created_at : $shipment->created_at,
            'updated_at' => $shipment->updated_at,
            'shipment' => $shipment,
        ];

        return sendResponse("Return request details.", $returnData);
    }

    /**
     * @OA\Post(
     *     path="/returns/update",
     *     summary="Update return request status and details",
     *     description="Updates the status and adds notes to a return request.",
     *     tags={"OMS"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", description="Shipment ID"),
     *             @OA\Property(property="status", type="string", description="New status (Pending, In Progress, Completed)"),
     *             @OA\Property(property="note", type="string", description="Additional notes (optional)", maxLength=1000),
     *         ),
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Return request updated successfully",
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Return request not found or not marked as a return",
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation Error or Database Error",
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *     ),
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function update(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:shipments,id',
            'status' => 'required|string',
            'note' => 'nullable|string|max:1000',
        ]);

        DB::beginTransaction();
        try {
            $shipment = Shipment::findOrFail($request->id);
            if (!$shipment->in_exception) {
                return sendResponse("This shipment is not marked as a return.", [], false, ['not a return'], 404);
            }
            $statusInfo = status($request->status);
            if (isset($statusInfo[$request->status])) {
                $newShipmentStatus = $statusInfo[$request->status];
            } else {
                $newShipmentStatus = strtoupper(str_replace(' ', '_', $request->status));
            }
            $description = !empty($statusInfo['description'])
                ? $statusInfo['description']
                : "Return status updated to {$request->status}";
            $exceptionStatus = exception_status($newShipmentStatus);
            if ($exceptionStatus === null) {
                $shipment->in_exception = false;
            } else {
                $shipment->in_exception = true;
            }
            $shipment->status = $newShipmentStatus;
            $shipment->save();
            $historyData = [
                "shipment_id" => $shipment->id,
                "status" => "RETURN_UPDATE",
                "description" => $request->input('note', null) ? $request->input('note', null) : $description,
                "type" => $newShipmentStatus,
            ];
            shipmentHistory($historyData);
            DB::commit();
            $shipment = Shipment::with([
                'shipmentHistories' => function ($query) use ($newShipmentStatus) {
                    $query->where('type', $newShipmentStatus)->orderBy('created_at', 'desc');
                }
            ])->find($shipment->id);
            $latestHistory = $shipment->shipmentHistories->first();
            $returnData = [
                'id' => $shipment->id,
                'return_id' => 'RET-' . $shipment->id,
                'shipment_tracking_no' => $shipment->tracking_no,
                'customer_name' => $shipment->consignee->name ?? 'Unknown',
                'reason' => $latestHistory ? $latestHistory->description : 'Return requested',
                'notes' => $latestHistory ? $latestHistory->notes : null,
                'status' => $shipment->status,
                'created_at' => $latestHistory ? $latestHistory->created_at : $shipment->created_at,
                'updated_at' => $shipment->updated_at,
                'shipment' => $shipment,
            ];
            return sendResponse("Return request updated successfully.", $returnData);
        } catch (QueryException $e) {
            DB::rollBack();
            return sendResponse("Error Occurred.", [], false, [$e->getMessage()], 422);
        }
    }

    /**
     * @OA\Post(
     *     path="/returns/export",
     *     summary="Export return requests data",
     *     description="Exports return requests data in CSV or PDF format with optional filtering and column selection.",
     *     tags={"OMS"},
     *     @OA\Parameter(
     *         name="format",
     *         in="query",
     *         description="Export format (csv, pdf)",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="columns",
     *         in="query",
     *         description="Columns to include in export (comma-separated)",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="from_date",
     *         in="query",
     *         description="Filter returns from this date",
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="to_date",
     *         in="query",
     *         description="Filter returns to this date",
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filter returns by status (Pending, In Progress, Completed, All)",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Return requests exported successfully",
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Invalid format specified",
     *     ),
     *      @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *     ),
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function export(Request $request)
    {
        $format = $request->input('format', 'csv');
        if (!in_array($format, ['csv', 'pdf'])) {
            return sendResponse('Invalid format specified', [], false, null, 422);
        }
        $availableColumns = [
            'id',
            'return_id',
            'shipment_tracking_no',
            'customer_name',
            'reason',
            'status',
            'created_at',
            'updated_at'
        ];
        $selectedColumns = $request->input('columns', $availableColumns);
        if (is_string($selectedColumns)) {
            $selectedColumns = explode(',', $selectedColumns);
        }
        $columns = array_intersect($availableColumns, $selectedColumns);
        $returnsQuery = Shipment::where('in_exception', true);
        if ($request->has(['from_date', 'to_date'])) {
            $returnsQuery->whereBetween('created_at', [
                Carbon::parse($request->from_date)->startOfDay(),
                Carbon::parse($request->to_date)->endOfDay()
            ]);
        }
        if ($request->has('status') && $request->status !== 'All') {
            switch ($request->status) {
                case 'Completed':
                    $returnsQuery->where('status', 'RETURNED');
                    break;
                case 'In Progress':
                    $returnsQuery->whereIn('status', ['DELIVERY_EXCEPTION', 'RTO']);
                    break;
                case 'Pending':
                    $returnsQuery->whereNotIn('status', ['RETURNED', 'DELIVERY_EXCEPTION', 'RTO']);
                    break;
            }
        }
        $returnsQuery->with([
            'consignee:id,name',
            'shipmentHistories' => function ($query) {
                $query->where('type', 'RETURN')->orderBy('created_at', 'desc');
            }
        ]);

        $shipments = $returnsQuery->get();
        $returns = $shipments->map(function ($shipment) {
            $latestHistory = $shipment->shipmentHistories->first();
            return [
                'id' => $shipment->id,
                'return_id' => 'RET-' . $shipment->id,
                'shipment_tracking_no' => $shipment->tracking_no,
                'customer_name' => $shipment->consignee->name ?? 'Unknown',
                'reason' => $latestHistory ? $latestHistory->description : 'Return requested',
                'status' => $shipment->status,
                'created_at' => $latestHistory ? $latestHistory->created_at : $shipment->created_at,
                'updated_at' => $shipment->updated_at
            ];
        });
        if ($format === 'pdf') {
            $formattedReturns = $returns->map(function ($item) {
                if (isset($item['created_at']) && $item['created_at'] instanceof Carbon) {
                    $item['created_at'] = $item['created_at']->format('Y-m-d H:i:s');
                }
                if (isset($item['updated_at']) && $item['updated_at'] instanceof Carbon) {
                    $item['updated_at'] = $item['updated_at']->format('Y-m-d H:i:s');
                }
                return $item;
            })->toArray();
            $pdf = Pdf::loadView('exports.returns', [
                'returns' => $formattedReturns,
                'columns' => $columns,
                'date' => now()->format('Y-m-d H:i:s')
            ]);
            return $pdf->download('returns_' . now()->format('Y-m-d') . '.pdf');
        }
        $name = 'returns.' . $format;
        return Excel::download(new ReturnExport($returns, $columns), $name);
    }
    public function exportTemplate()
    {
        // Use the same template as shipments if it exists, otherwise return a generic response
        $templatePath = public_path('templates/returns_export_template.xlsx');
        $shipmentTemplatePath = public_path('templates/shipment_import_template.xlsx');

        if (file_exists($templatePath)) {
            return response()->download($templatePath);
        } elseif (file_exists($shipmentTemplatePath)) {
            return response()->download($shipmentTemplatePath, 'returns_export_template.xlsx');
        } else {
            return sendResponse("Template file not found.", [], false, ['template not found'], 404);
        }
    }
}
