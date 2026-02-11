<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Requests\StoreDriverWaybillRequest;
use App\Http\Requests\UpdateDriverWaybillRequest;
use App\Models\DriverWaybill;
use App\Models\DriverWaybillBatch;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
class DriverWaybillController extends Controller
{
    public function index()
    {
        $perPage = request('per_page', 8);
        $q = User::byOwner()->whereHas('driverWaybills'); // أضف relation في User لو حابب

        if (request('query')) {
            $term = strtolower(request('query'));
            $items = $q->whereRaw('LOWER(name) LIKE ?', ["%{$term}%"])
                ->with('driver')
                ->withCount('driverWaybills')
                ->orderByDesc('id')
                ->get();

            return sendResponse("Driver Waybills retrieved successfully.", $items, []);
        }

        $items = $q->with('driver')->withCount('driverWaybills')
            ->orderByDesc('id')->paginate($perPage);

        return sendResponse("Driver Waybills retrieved successfully.", $items, []);
    }

    public function show(Request $request)
    {
        $perPage = (int) $request->input('per_page', 8);
        $user = Auth::user();

        $q = DriverWaybill::query()->with('driver', 'batch');

        // Admins يشوفوا الكل، غير كدا يقيد بالـ driver نفسه (لو عندك رول Driver)
        if (!($user->hasRole('Super Admin') || $user->hasRole('Admin'))) {
            $q->where('driver_id', $user->id);
        } elseif ($request->filled('driver_id')) {
            $q->where('driver_id', (int) $request->driver_id);
        }

        if ($request->filled('query')) {
            $term = strtolower($request->input('query'));
            $items = $q->whereRaw('LOWER(tracking_no) LIKE ?', ["%{$term}%"])
                ->orderBy('used')
                ->orderByDesc('id')
                ->get();

            return sendResponse("Driver Waybills retrieved successfully.", $items, []);
        }

        if ($request->filled('batch_id')) {
            $q->where('batch_id', (int) $request->batch_id);
        }

        $items = $q->orderBy('used')->orderByDesc('id')->paginate($perPage);
        return sendResponse("Driver Waybills retrieved successfully.", $items, []);
    }

    public function batches(Request $request)
    {
        $perPage = (int) $request->input('per_page', 8);
        $user = Auth::user();

        $q = DriverWaybillBatch::query()
            ->with('driver')
            ->withCount([
                'waybills',
                'waybills as used_count' => fn($qq) => $qq->where('used', true),
            ]);

        if (!($user->hasRole('Super Admin') || $user->hasRole('Admin'))) {
            $q->where('driver_id', $user->id);
        } elseif ($request->filled('driver_id')) {
            $q->where('driver_id', (int) $request->driver_id);
        }

        $items = $q->orderByDesc('id')->paginate($perPage);
        $items->getCollection()->transform(function ($item) {
            $item->unused_count = max(0, ($item->waybills_count - $item->used_count));
            return $item;
        });

        return sendResponse('Driver Waybill Batches retrieved successfully.', $items, []);
    }

    public function showBatches(Request $request, $id)
    {
        $perPage = (int) $request->input('per_page', 8);
        $user = Auth::user();
        $driverId = (int) $request->input('driver_id');

        $q = DriverWaybill::query()
            ->where('batch_id', $id)
            ->orderBy('used')
            ->with('batch.driver');

        // if (!($user->hasRole('Super Admin') || $user->hasRole('Admin'))) {
        //     $q->whereHas('batch', fn($qq) => $qq->where('driver_id', $user->id));
        // } elseif ($driverId) {
        //     $q->whereHas('batch', fn($qq) => $qq->where('driver_id', $driverId));
        // }
        $q->whereHas('batch', fn($qq) => $qq->where('driver_id', $driverId));

        $items = $q->orderByDesc('id')->paginate($perPage);
        return sendResponse('Driver Waybills retrieved successfully.', $items, []);
    }

    public function store(StoreDriverWaybillRequest $request)
    {
        try {
            $data = $request->validated();

            return DB::transaction(function () use ($data) {
                $batch = DriverWaybillBatch::create([
                    'driver_id' => $data['driver_id'],
                    'created_by' => Auth::id(),
                    'quantity' => (int) $data['quantity'],
                ]);

                $waybill = null;
                for ($i = 0; $i < $data['quantity']; $i++) {
                    $waybill = DriverWaybill::create([
                        "driver_id" => $data['driver_id'],
                        "batch_id" => $batch->id,
                        "tracking_no" => generate_driver_tracking_no(), // أو generate_driver_tracking_no()
                    ]);
                }

                $batch->update(['quantity' => $batch->waybills()->count()]);
                activityLog('deriver waybill created',"driver waybill created for driver with username : {$batch?->user?->username} with batch count : {$batch->quantity}");
                return response()->json([
                    "message" => "Driver Waybills created successfully.",
                    "batch_id" => $batch->id,
                ]);
            });
        } catch (QueryException $e) {
            return sendResponse("Error occurred while creating Driver Waybills.", [], [$e->getMessage()], 422);
        }
    }

    public function update(UpdateDriverWaybillRequest $request)
    {
        try {
            $waybill = DriverWaybill::findOrFail($request->id);
            $waybill->update($request->validated());
            return sendResponse("Driver Waybill updated successfully.", $waybill);
        } catch (QueryException $e) {
            return sendResponse("Error occurred while updating Driver Waybill.", [], [$e->getMessage()], 422);
        }
    }

    public function delete(Request $request)
    {
        try {
            DriverWaybill::where('id', $request->id)->delete();
            return sendResponse("Driver Waybill deleted successfully.", []);
        } catch (QueryException $e) {
            return sendResponse("Error occurred.", [], [$e->getMessage()], 422);
        }
    }

    public function printMultipleWaybills(Request $request)
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['exists:driver_waybills,id'],
            'forcePrintMode' => ['nullable', 'in:none,local-waybill,international-waybill'],
        ]);

        $ids = $data['ids'];
        $waybills = DriverWaybill::with([
            'driver',
            'shipment.consignee.city',
            'shipment.consignee.governorate',
            'shipment.consignee.state',
            'shipment.consignee.place',
            'shipment.deliveryAddress.governorate',
            'shipment.deliveryAddress.state',
            'shipment.deliveryAddress.place',
            'shipment.shipper.country',
            'shipment.shipper.state',
            'shipment.shipment_information.zone',
            'shipment.shipment_information',
            'shipment.shipment_items',
            'shipment.shipment_amounts',
            'shipment.merchant',
            'shipment.driver.driver'
        ])
            ->whereIn('id', $ids)
            ->get()
            ->sortBy(fn($o) => array_search($o->tracking_no, $ids))
            ->values();

        $forcePrintMode = $data['forcePrintMode'] ?? 'none';
        $html = view('printWaybills', [
            'waybills' => $waybills,
            'forcePrintMode' => $forcePrintMode,
        ])->render(); // نفس الفيو
        return response()->json(['html' => $html]);
    }
}
