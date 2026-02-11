<?php

namespace App\Http\Controllers\Api\v1;
use App\Http\Controllers\Controller;


use App\Exports\GeneralExport;
use App\Models\User;
use App\Models\State;
use App\Models\Driver;
use Illuminate\Http\Request;
use App\Http\Resources\UserResource;
use Illuminate\Support\Facades\Auth;
use App\Http\Resources\DriverResource;
use Carbon\Carbon;
use App\Models\DriverShipmentAssignment;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use App\Models\Setting;
use App\Models\Shipment;
use Google\Service\Drive;

/**
 * @OA\Tag(name="Fleet & Driver Management", description="APIs related to fleet and driver management.")
 * @OA\Server(url="http://localhost:8000/api")
 */
class DriverController extends Controller
{
    /**
     * @OA\Get(
     *     path="/drivers",
     *     summary="Get a list of drivers",
     *     description="Retrieve a list of drivers with pagination and filtering options.",
     *     tags={"Fleet & Driver Management"},
     *     @OA\Parameter(
     *         name="query",
     *         in="query",
     *         description="Search query for driver name",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Users retrieved successfully",
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    /**
     * @OA\Get(
     *     path="/api/drivers",
     *     summary="Get list of drivers with pagination",
     *     tags={"Drivers"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="query",
     *         in="query",
     *         description="Search query for driver name",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of items per page",
     *         required=false,
     *         @OA\Schema(type="integer", default=8)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Drivers retrieved successfully"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     )
     * )
     */
    public function index()
    {
        $perPage = request()->query('per_page', 8);
        $from = request()->input('from');
        $to = request()->input('to');
        $driver_id = request()->input('driver_id');
        $facility_id = facility('id');
        $facility_type = facility('type');
        $baseQuery = User::query()
            ->where('owner_id', $facility_id)
            ->where('owner_type', $facility_type)
            ->whereHas('driver', function ($q) {
                $q->where('is_guest', false);
            })
            ->when($driver_id, function ($q) use ($driver_id) {
                $q->where('id', $driver_id);
            })
            // DATE FILTER
            ->when($from, function ($q) use ($from) {
                $q->whereDate('created_at', '>=', Carbon::parse($from)->startOfDay());
            })
            ->when($to, function ($q) use ($to) {
                $q->whereDate('created_at', '<=', Carbon::parse($to)->endOfDay());
            })
            ->with([
                'roles',
                'driver.owner',
                'driver.company',
                'driver.settings',
                'driver_relatives',
                'driver_files'
            ])
            ->orderBy('id', 'desc');


        if (request()->has('query')) {
            $searchQuery = request()->input('query');
            $baseQuery->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($searchQuery) . '%']);
        } else {
            $baseQuery->where('id', '!=', Auth::id());
        }

        $users = $baseQuery->paginate($perPage);

        if ($users->isEmpty()) {
            return sendResponse("No drivers found.", [], false, ['No drivers found']);
        }

        return sendResponse("Drivers retrieved successfully.", new UserResource($users));
    }

    public function deleted_drivers_index()
    {
        $perPage = request()->query('per_page', 8);
        $from = request()->input('from');
        $to = request()->input('to');
        $driver_id = request()->input('driver_id');
        $facility_id = facility('id');
        $facility_type = facility('type');

        $baseQuery = User::onlyTrashed()
            ->where('owner_id', $facility_id)
            ->where('owner_type', $facility_type)
            ->when($driver_id, function ($q) use ($driver_id) {
                $q->where('id', $driver_id);
            })
            // Filter by deleted date
            ->when($from, function ($q) use ($from) {
                $q->whereDate('deleted_at', '>=', Carbon::parse($from)->startOfDay());
            })
            ->when($to, function ($q) use ($to) {
                $q->whereDate('deleted_at', '<=', Carbon::parse($to)->endOfDay());
            })
            ->with([
                'roles',
                'driver.owner',
                'driver.company',
                'driver.settings',
                'driver_relatives',
                'driver_files',
                'deletedBy' // ✅ eager-load who deleted this user
            ])
            ->orderBy('deleted_at', 'desc');

        if (request()->has('query')) {
            $searchQuery = request()->input('query');
            $baseQuery->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($searchQuery) . '%']);
        }

        $users = $baseQuery->paginate($perPage);

        if ($users->isEmpty()) {
            return sendResponse("No drivers found.", [], false, ['No drivers found']);
        }

        return sendResponse("Deleted Drivers retrieved successfully.", new UserResource($users));
    }


    public function restoreDriver(Request $request)
    {
        $id = $request->id;

        $user = User::onlyTrashed()
            ->where('id', $id)
            ->with([
                'driver' => function ($q) {
                    $q->withTrashed();
                }
            ])
            ->first();

        if (!$user) {
            return sendResponse(
                'Driver not found.',
                [],
                false,
                ['Driver not found']
            );
        }

        if (!$user->trashed()) {
            return sendResponse('User already active.', [], false);
        }

        // Restore user
        $user->restore();

        // ✅ Restore related driver ONLY if it exists and is soft-deleted
        if ($user->driver && method_exists($user->driver, 'restore')) {
            $user->driver->restore();
        }

        return sendResponse(
            'Driver restored successfully.',
            new UserResource($user->fresh()),
            true
        );
    }



    /**
     * @OA\Get(
     *     path="/drivers/all",
     *     summary="Get all drivers",
     *     description="Retrieve all drivers.",
     *     tags={"Fleet & Driver Management"},
     *     @OA\Response(
     *         response=200,
     *         description="Drivers retrieved successfully",
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function all()
    {
        $facility_id = facility()->id;
        $facility_type = facility()->type;
        $drivers = Driver::with('user:id,name')->where('owner_id', $facility_id)
            ->where('owner_type', $facility_type)->get();
        if (!$drivers) {
            return sendResponse("No drivers found.", [], false);
        }
        return sendResponse("Drivers", new DriverResource($drivers));
    }

    /**
     * @OA\Get(
     *     path="/drivers/getSingle",
     *     summary="Get a single driver",
     *     description="Retrieve a single driver by ID.",
     *     tags={"Fleet & Driver Management"},
     *     @OA\Parameter(
     *         name="driver_id",
     *         in="query",
     *         description="ID of the driver",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Driver retrieved successfully",
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="User not found or is not a driver",
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function getSingle(Request $request)
    {
        $request->validate([
            'driver_id' => 'required',
        ]);

        $driverId = $request->input('driver_id');

        $user = User::with('driver')->find($driverId);

        if (!$user || !$user->driver) {
            return sendResponse("User not found or is not a driver.", [], 404);
        }

        $user['states'] = State::where('country_id', 165)->get();

        return sendResponse("Driver retrieved successfully.", $user, []);
    }

    /**
     * @OA\Post(
     *     path="/drivers/change_status/{driver_id}",
     *     summary="Change driver status",
     *     description="Change the status of a driver.",
     *     tags={"Fleet & Driver Management"},
     *     @OA\Parameter(
     *         name="driver_id",
     *         in="path",
     *         description="ID of the driver",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="status",
     *                 type="string",
     *                 description="New status of the driver"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Status updated successfully",
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error occurred while updating driver status",
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function change_status($driver_id, Request $request)
    {
        $request->validate([
            'status' => 'required'
        ]);

        try {
            $user = User::findOrFail($driver_id);
            $user->driver->status = $request->status;
            $user->driver->save();
            $user->save();

            return sendResponse(
                "Status updated successfully.",
                new UserResource($user)
            );
        } catch (QueryException $e) {
            return sendResponse("Error occurred while updating payment proof requirement.", [], [$e->getMessage()], 422);
        }
    }

    /**
     * @OA\Post(
     *     path="/drivers/change_edit_proof/{driver_id}",
     *     summary="Change driver edit proof ability",
     *     description="Change the ability of a driver to edit their proof.",
     *     tags={"Fleet & Driver Management"},
     *     @OA\Parameter(
     *         name="driver_id",
     *         in="path",
     *         description="ID of the driver",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="status",
     *                 type="boolean",
     *                 description="New status of edit proof ability"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Ability updated successfully",
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error occurred while updating edit proof requirement",
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function change_edit_proof($driver_id, Request $request)
    {
        $request->validate([
            'status' => 'required'
        ]);

        try {
            $user = User::findOrFail($driver_id);
            $user->driver->settings->edit_proof = $request->status;
            $user->driver->settings->save();
            $user->driver->save();
            $user->save();

            return sendResponse(
                "Ability updated successfully.",
                new UserResource($user)
            );
        } catch (QueryException $e) {
            return sendResponse("Error occurred while updating payment proof requirement.", [], [$e->getMessage()], 422);
        }
    }

    /**
     * @OA\Post(
     *     path="/drivers/export",
     *     summary="Export driver data",
     *     description="Export driver data in CSV or PDF format.",
     *     tags={"Fleet & Driver Management"},
     *     @OA\Parameter(
     *         name="format",
     *         in="query",
     *         description="Format of the export (csv or pdf)",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="columns",
     *         in="query",
     *         description="Comma-separated list of columns to export",
     *         @OA\Schema(type="string")
     *     ),
     *      @OA\Parameter(
     *         name="from_date",
     *         in="query",
     *         description="Start date for filtering (YYYY-MM-DD)",
     *         @OA\Schema(type="string", format="date")
     *     ),
     *      @OA\Parameter(
     *         name="to_date",
     *         in="query",
     *         description="End date for filtering (YYYY-MM-DD)",
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Driver data exported successfully",
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Invalid format specified",
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function export(Request $request)
    {
        $format = $request->input('format');
        if (!in_array($format, ['csv', 'pdf'])) {
            return sendResponse('Invalid format specified', [], false, null, 422);
        }

        // Get columns to export
        $availableColumns = [
            'id',
            'name',
            'email',
            'driver.phone',
            'driver.company.name',
            'created_at',
            'updated_at'
        ];
        $selectedColumns = $request->input('columns', $availableColumns);

        // Convert string input to array
        if (is_string($selectedColumns)) {
            $selectedColumns = explode(',', $selectedColumns);
        }

        // Ensure only valid columns are selected
        $columns = array_intersect($availableColumns, $selectedColumns);

        // Handle relationships
        $relationships = [];
        foreach ($availableColumns as $column) {
            if (strpos($column, '.') !== false) {
                $parts = explode('.', $column);
                array_pop($parts);
                if (!empty($parts)) {
                    $relationships[] = implode('.', $parts);
                }
            }
        }

        $users = User::query();
        $query = $users->whereHas('driver')->with(array_unique($relationships));

        // $query = Role::query();
        if (!empty($relationships)) {
            $query->with($relationships);
        }

        // Date filtering
        if ($request->has(['from_date', 'to_date'])) {
            $query->whereBetween('created_at', [
                Carbon::parse($request->from_date)->startOfDay(),
                Carbon::parse($request->to_date)->endOfDay()
            ]);
        }

        $role = $query->get();

        if ($format === 'pdf') {
            $html = view('exports.general_export', [
                'name' => "Drivers",
                'rows' => $role,
                'columns' => $columns,
                'date' => now()->format('Y-m-d H:i:s')
            ])->render();
            return response()->json(['html' => $html]);
        }

        $name = 'role.' . $format;
        return Excel::download(new GeneralExport($role, $columns), $name);
    }
    /**
     * @OA\Get(
     *     path="/drivers/performance",
     *     summary="Get driver performance metrics",
     *     description="Retrieve driver performance metrics with filtering options.",
     *     tags={"Fleet & Driver Management"},
     *     @OA\Parameter(
     *         name="from_date",
     *         in="query",
     *         description="Start date for the performance period (YYYY-MM-DD)",
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="to_date",
     *         in="query",
     *         description="End date for the performance period (YYYY-MM-DD)",
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="driver_name",
     *         in="query",
     *         description="Filter by driver name",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Driver performance retrieved successfully",
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error retrieving driver performance",
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function performance(Request $request)
    {
        try {
            $fromDate = Carbon::parse($request->input('from_date', now()->subMonths(3)->startOfMonth()));
            $toDate = Carbon::parse($request->input('to_date', now()->endOfMonth()));
            $driverName = $request->input('driver_name');

            $query = User::whereHas('driver')
                ->with([
                    'driver',
                    'driverShipmentAssignments' => function ($q) use ($fromDate, $toDate) {
                        $q->whereBetween('assigned_at', [$fromDate, $toDate]);
                    }
                ]);

            if ($driverName) {
                $query->where('name', 'LIKE', "%{$driverName}%");
            }

            $drivers = $query->get()->map(function ($driver) {
                $totalDeliveries = $driver->driverShipmentAssignments->count();
                $onTimeDeliveries = $driver->driverShipmentAssignments->filter(function ($assignment) {
                    return $assignment->delivered_at &&
                        $assignment->delivered_at <= $assignment->expected_delivery_date;
                })->count();

                $delayedDeliveries = $totalDeliveries - $onTimeDeliveries;
                $onTimeRate = $totalDeliveries > 0 ? round(($onTimeDeliveries / $totalDeliveries) * 100, 2) : 0;

                return [
                    'id' => $driver->id,
                    'name' => $driver->name,
                    'total_deliveries' => $totalDeliveries,
                    'on_time_deliveries' => $onTimeDeliveries,
                    'delayed_deliveries' => $delayedDeliveries,
                    'on_time_rate' => $onTimeRate,
                    'customer_rating' => $driver->driver->rating ?? 0,
                ];
            });

            // Performance Summary
            $summary = [
                'total_drivers' => $drivers->count(),
                'average_on_time_rate' => round($drivers->avg('on_time_rate'), 2),
                'total_deliveries' => $drivers->sum('total_deliveries'),
            ];

            // Performance Trend
            $performanceTrend = $this->getPerformanceTrend($fromDate, $toDate);

            return sendResponse("Driver Performance Retrieved", [
                'drivers' => $drivers,
                'summary' => $summary,
                'trend' => $performanceTrend
            ]);
        } catch (\Exception $e) {
            Log::error('Driver Performance Error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error retrieving driver performance',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function getPerformanceTrend($fromDate, $toDate)
    {
        $currentDate = $fromDate->copy();
        $trend = [];

        while ($currentDate <= $toDate) {
            $monthlyDeliveries = DriverShipmentAssignment::whereBetween('assigned_at', [
                $currentDate->copy()->startOfMonth(),
                $currentDate->copy()->endOfMonth()
            ])->count();

            $trend[] = [
                'title' => $currentDate->format('M Y'),
                'value' => $monthlyDeliveries
            ];

            $currentDate->addMonth();
        }

        return $trend;
    }

    /**
     * @OA\Post(
     *     path="/drivers/{id}",
     *     summary="Update driver",
     *     description="Update driver details",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Driver ID"
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"name", "email", "phone"},
     *                 @OA\Property(property="name", type="string"),
     *                 @OA\Property(property="email", type="string"),
     *                 @OA\Property(property="phone", type="string"),
     *                 @OA\Property(property="company_id", type="integer"),
     *                 @OA\Property(property="license", type="string", format="binary"),
     *                 @OA\Property(property="id_card", type="string", format="binary"),
     *                 @OA\Property(property="car_ownership_id", type="string", format="binary"),
     *                 @OA\Property(property="password", type="string")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Driver updated successfully"
     *     )
     * )
     */
    public function update(Request $request, $id)
    {
        $user = User::with('driver')->findOrFail($id);
        $facilityId = $request->facility_id ?? facility("id");
        $facilityType = $request->facility_type ?? facility("type");

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'username' => 'nullable|string|max:255',
            'email' => 'nullable|email|unique:users,email,' . $id,
            'phone' => 'required|string',
            'company_id' => 'nullable|exists:companies,id',
            'password' => 'nullable|string|min:8',
            'license' => 'nullable|file',
            'id_card' => 'nullable|file',
            'is_guest' => 'nullable|boolean',
            'car_ownership_id' => 'nullable|file'
        ]);

        // Update user data
        $user->name = $validated['name'];
        $user->username = $validated['username'];
        $user->email = $validated['email'] ?? null;
        $user->owner_id = $facilityId;
        $user->owner_type = $facilityType;
        if ($request->has('password')) {
            $user->password = Hash::make($validated['password']);
        }
        $user->save();

        // Update driver data
        if ($user->driver) {
            $driver = $user->driver;
            // Split phone number into country code and national number
            if ($validated['phone']) {
                $phoneSplit = splitPhoneNumber($validated['phone']);
                $driver->country_code = $phoneSplit['country_code'];
                $driver->phone = $phoneSplit['national_number'];
            }
            $driver->company_id = $validated['company_id'] ?? null;
            $driver->is_guest = $validated['is_guest'] ?? null;

            if ($request->hasFile('license')) {
                $driver->license = uploadFile($request->license, 'public/driver_files');
            }
            if ($request->hasFile('id_card')) {
                $driver->id_card = uploadFile($request->id_card, 'public/driver_files');
            }
            if ($request->hasFile('car_ownership_id')) {
                $driver->car_ownership_id = uploadFile($request->car_ownership_id, 'public/driver_files');
            }

            $driver->save();
        }
        activityLog('driver update', "deriver with username : {$user->username} updated by");

        return sendResponse("Driver updated successfully.", new UserResource($user));
    }

    public function delete()
    {
        try {
            $driver = User::findOrFail(request()->input('id'));

            // Track who deleted the user
            $driver->deleted_by = auth()->id();
            $driver->saveQuietly();

            // Soft delete related driver first (if exists)
            if ($driver->driver) {
                $driver->driver->delete();
            }

            // Soft delete the user
            $driver->delete();

            activityLog(
                'driver delete',
                "Driver with username : {$driver->username} soft deleted by user ID " . auth()->id()
            );

            return sendResponse("Driver deleted successfully.", [], [], 200);

        } catch (\Exception $e) {
            return sendResponse(
                "Error deleting driver.",
                [],
                false,
                [$e->getMessage()],
                400
            );
        }
    }

    public function updateDefaultDriverBonuse(Request $request)
    {
        if ($request->has('delivery_value') && $request->has('pickup_value')) {
            $deliverySetting = Setting::where('key', 'default_driver_delivery_bonuses')->first();
            $pickupSetting = Setting::where('key', 'default_driver_pickup_bonuses')->first();

            if (!$deliverySetting || !$pickupSetting) {
                return sendResponse("No Settings Found.", null, [], 404);
            }
            $deliverySetting->value = $request->delivery_value;
            $pickupSetting->value = $request->pickup_value;
            $deliverySetting->save();
            $pickupSetting->save();
            return sendResponse("Driver bonuses updated successfully.", [
                'delivery' => $deliverySetting,
                'pickup' => $pickupSetting
            ]);
        }
        $validator = Validator::make($request->all(), [
            'value' => 'required',
            'type' => 'required|in:delivery,pickup'
        ]);
        if ($validator->fails()) {
            return sendResponse(
                'Validation error',
                [],
                false,
                $validator->errors(),
                422
            );
        }

        $key = $request->type === 'delivery' ? 'default_driver_delivery_bonuses' : 'default_driver_pickup_bonuses';
        $setting = Setting::where('key', $key)->first();
        if (!$setting) {
            return sendResponse("No Setting Found.", null, [], 404);
        }
        $setting->value = $request->value;
        $setting->save();
        return sendResponse("Default driver commission updated successfully.", $setting);
    }

    public function getDefaultDriverBonuse(Request $request)
    {
        if ($request->query('type')) {
            $type = $request->query('type');
            $key = $type === 'delivery' ? 'default_driver_delivery_bonuses' : 'default_driver_pickup_bonuses';
            $setting = Setting::where('key', $key)->first();
            if (!$setting) {
                return sendResponse("No Setting Found.", null, [], 404);
            }
            return sendResponse("Default driver commission retrieved successfully.", $setting);
        }
        $deliverySetting = Setting::where('key', 'default_driver_delivery_bonuses')->first();
        $pickupSetting = Setting::where('key', 'default_driver_pickup_bonuses')->first();
        if (!$deliverySetting || !$pickupSetting) {
            return sendResponse("No Settings Found.", null, [], 404);
        }
        return sendResponse("Driver bonuses retrieved successfully.", [
            'delivery' => $deliverySetting,
            'pickup' => $pickupSetting
        ]);
    }

    public function dailySummary(Request $request)
    {
        $request->validate([
            'driver_id' => 'nullable|integer|exists:users,id',
            'date' => 'nullable|date_format:Y-m-d',
        ]);

        $driverId = $request->input('driver_id', auth()->id());

        $user = User::with('driver')->find($driverId);
        if (!$user || !$user->driver) {
            return sendResponse("User not found or is not a driver.", [], 404);
        }

        $date = Carbon::parse($request->input('date', now()->toDateString()));
        $from = $date->copy()->startOfDay();
        $to = $date->copy()->endOfDay();

        $base = DriverShipmentAssignment::query()
            ->where('driver_shipment_assignments.driver_id', $driverId)
            ->whereBetween('driver_shipment_assignments.assigned_at', [$from, $to]);

        $totalAssigned = (clone $base)->count();

        $delivered = (clone $base)
            ->whereBetween('driver_shipment_assignments.delivered_at', [$from, $to])
            ->count();

        // helper لاستبعاد أي أوردر تم تسليمه اليوم من باقي البوكتس
        $notDeliveredToday = function ($q) use ($from, $to) {
            $q->where(function ($qq) use ($from, $to) {
                $qq->whereNull('driver_shipment_assignments.delivered_at')
                    ->orWhereNotBetween('driver_shipment_assignments.delivered_at', [$from, $to]);
            });
        };

        $exceptions = (clone $base)
            ->where($notDeliveredToday)
            ->whereHas('shipment', function ($q) {
                $q->where('in_exception', 1);
            })
            ->count();

        $confirmedOnly = (clone $base)
            ->whereNotNull('driver_shipment_assignments.confirmed_at')
            ->where($notDeliveredToday)
            ->whereDoesntHave('shipment', function ($q) {
                $q->where('in_exception', 1);
            })
            ->count();

        $pendingConfirmation = (clone $base)
            ->whereNull('driver_shipment_assignments.confirmed_at')
            ->where($notDeliveredToday)
            ->whereHas('shipment', function ($q) {
                $q->where('in_exception', 0);
            })
            ->count();

        return sendResponse("Driver daily summary", [
            'driver_id' => $driverId,
            'date' => $date->toDateString(),
            'total_assigned' => $totalAssigned,
            'pending_confirmation' => $pendingConfirmation,
            'confirmed' => $confirmedOnly,
            'delivered' => $delivered,
            'exception' => $exceptions,
        ]);
    }


    public function stats(Request $request)
    {
        $driverId = $request->user()->id;

        $today = Carbon::today();
        $yesterday = Carbon::yesterday();
        $monthStart = operation_now()->startOfMonth();

        $deliveredCount = Shipment::where('driver_id', $driverId)
            ->where('status', 'DELIVERED')
            ->count();

        $createdCount = Shipment::where('created_by', $driverId)->count();

        $totalAssigned = Shipment::where('driver_id', $driverId)->count();

        $deliveryRate = $totalAssigned > 0
            ? round(($deliveredCount / $totalAssigned) * 100, 2)
            : 0;

        $earnedToday = Shipment::where('driver_id', $driverId)
            ->where('status', 'DELIVERED')
            ->whereHas('shipmentHistories', function ($q) use ($today) {
                $q->where('name', 'DELIVERED')
                    ->whereDate('time', $today);
            })
            ->with('shipment_finance')
            ->get()
            ->sum(function ($shipment) {
                return $shipment->driver_commission;
            });

        $earnedYesterday = Shipment::where('driver_id', $driverId)
            ->where('status', 'DELIVERED')
            ->whereHas('shipmentHistories', function ($q) use ($yesterday) {
                $q->where('name', 'DELIVERED')
                    ->whereDate('time', $yesterday);
            })
            ->with('shipment_finance')
            ->get()
            ->sum(function ($shipment) {
                return $shipment->driver_commission;
            });

        $earnedThisMonth = Shipment::where('driver_id', $driverId)
            ->where('status', 'DELIVERED')
            ->whereHas('shipmentHistories', function ($q) use ($monthStart) {
                $q->where('name', 'DELIVERED')
                    ->whereDate('time', '>=', $monthStart);
            })
            ->with('shipment_finance')
            ->get()
            ->sum(function ($shipment) {
                return $shipment->driver_commission;
            });

        $pickupCount = Shipment::where('driver_id', $driverId)
            ->where('status', 'PICKED_UP')
            ->count();

        $successfulDelivery = $deliveredCount;

        $exceptionsCount = Shipment::where('driver_id', $driverId)
            ->where('in_exception', true)
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'delivered_orders' => $deliveredCount,
                'created_orders' => $createdCount,
                'delivery_rate' => $deliveryRate . '%',

                'earned_today' => $earnedToday,
                'earned_yesterday' => $earnedYesterday,
                'earned_this_month' => $earnedThisMonth,

                'pickup_count' => $pickupCount,
                'successful_delivery' => $successfulDelivery,
                'exceptions' => $exceptionsCount,
            ],
        ]);
    }

}
