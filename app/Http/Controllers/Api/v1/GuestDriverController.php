<?php

namespace App\Http\Controllers\Api\v1;
use App\Http\Controllers\Controller;


use App\Http\Resources\DriverResource;
use App\Models\BranchUser;
use App\Models\Consignee;
use App\Models\User;
use App\Models\Driver;
use App\Models\HubUser;
use App\Models\Shipment;
use App\Models\StationUser;
use Spatie\Permission\Models\Role;
use App\Notifications\DriverNearbyNotification;
use App\Services\OtpService;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

/**
 * @OA\Tag(name="Other", description="Guest Driver Controller")
 */
class GuestDriverController extends Controller
{
    /**
     * @OA\Get(
     *     path="/guest-drivers/drivers/{driver_id?}",
     *     summary="Get guest drivers",
     *     description="Retrieves a list of guest drivers.  Can be filtered by ID and query parameters.",
     *     tags={"Other"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="driver_id",
     *         in="path",
     *         description="ID of the driver to retrieve. If omitted, returns all guest drivers.",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="query",
     *         in="query",
     *         description="Search query for name, email, company name, or phone.",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Guest drivers retrieved successfully."
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function index(Request $request, $driver_id = null)
    {
        $query = $request->input('query');
        $perPage = request()->query('per_page', 8);

        $drivers = User::with('driver')->whereHas('driver', function ($q) {
            $q->withoutGlobalScopes()->where('is_guest', true);
        });

        if ($driver_id) {
            $drivers = $drivers->where('id', $driver_id);
        }

        if ($query) {
            $drivers = $drivers->where(function ($q) use ($query) {
                $q->where('name', 'LIKE', '%' . $query . '%')
                    ->orWhere('email', 'LIKE', '%' . $query . '%')
                    ->orWhereHas('driver', function ($q) use ($query) {
                        $q->where('company_name', 'LIKE', '%' . $query . '%')
                            ->orWhere('phone', 'LIKE', '%' . $query . '%');
                    });
            })
                ->orderBy('name', 'asc')
                ->get();
        } else {
            $drivers = $drivers
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);
        }

        return sendResponse(
            "Guest drivers retrieved successfully.",
            new DriverResource($drivers)
        );
    }

    /**
     * @OA\Post(
     *     path="/guest-drivers/drivers",
     *     summary="Register a guest driver",
     *     description="Registers a new guest driver.",
     *     tags={"Other"},
     *     security={{"bearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 @OA\Property(property="name", type="string", description="Name", example="John Doe"),
     *                 @OA\Property(property="email", type="string", format="email", description="Email", example="john.doe@example.com"),
     *                 @OA\Property(property="password", type="string", description="Password", example="P@$$wOrd"),
     *                 @OA\Property(property="company_name", type="string", description="Company Name", example="Acme Corp"),
     *                 @OA\Property(property="phone", type="string", description="Phone Number", example="+15551234567"),
     *                 @OA\Property(property="id_card", type="string", format="binary", description="ID Card (file)"),
     *                 @OA\Property(property="license", type="string", format="binary", description="License (file)"),
     *                 @OA\Property(property="car_ownership_id", type="string", format="binary", description="Car Ownership ID (file)"),
     *                 @OA\Property(property="profile_image", type="string", format="binary", description="Profile Image (file)")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Guest driver created successfully."
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error or error occured."
     *     )
     * )
     */

    public function register(Request $request)
    {
        $request->validate([
            "name" => "required",
            "email" => "nullable|email|unique:users,email",
            "password" => "required",
            "phone" => "required|phone:AUTO|unique:users,phone",
        ]);

        DB::beginTransaction();
        try {
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'phone' => $request->phone,
            ]);

            $phone = $request->phone;

            Driver::create([
                "user_id" => $user->id,
                "phone" => $phone,
                "is_guest" => true,
            ]);

            // $user->syncRoles("Guest Driver");

            try {
                $otpService = app(\App\Services\OtpService::class);
                $otp = $otpService->generateAndSendOtp($user, $phone);
            } catch (\Throwable $e) {
                \Log::error('Register OTP send failed: ' . $e->getMessage());

                $otp = '111111';
                $user->forceFill([
                    'verification_code' => $otp,
                    'verification_code_expires_at' => now()->addMinutes(2),
                    'phone_verified_at' => null,
                ])->save();
            }

            DB::commit();

            return sendResponse(
                "Guest Driver created successfully. Please verify your phone with the OTP sent.",
                [
                    'user' => $user->load('roles', 'driver'),
                    'verification_required' => true,
                    'otp' => app()->environment(['local', 'testing']) ? ($otp ?? null) : null,
                ]
            );
        } catch (\Illuminate\Database\QueryException $e) {
            DB::rollBack();
            return sendResponse("Error Occurred.", [], false, [$e->getMessage()], 422);
        }
    }

    public function verifyOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'otp' => 'required|numeric|digits:6',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return sendResponse("User not found.", [], false, [], 404);
        }

        $otpService = new OtpService();

        if ($otpService->verifyOtp($user, $request->otp)) {

            // ✅ نحاول نجيب رقم الموبايل من user أو driver
            $phone = $user->phone ?? optional($user->driver)->phone;

            if ($phone) {
                $digits = preg_replace('/\D+/', '', (string) $phone); // نسيب أرقام فقط
                $last4 = substr($digits, -4);

                if ($last4) {
                    // نتأكد إنها مش موجودة في الاسم بالفعل
                    $shouldPrefix = true;

                    if (function_exists('str_starts_with')) {
                        $shouldPrefix = !str_starts_with($user->name, $last4 . ' ');
                    } else {
                        $shouldPrefix = substr($user->name, 0, strlen($last4) + 1) !== ($last4 . ' ');
                    }

                    if ($shouldPrefix) {
                        $user->update([
                            'name' => "{$last4} {$user->name}",
                        ]);
                    }
                }
            }

            return sendResponse("Phone number verified successfully.", [
                'user' => $user->load('roles', 'driver'),
                'verified' => true,
            ]);
        }

        return sendResponse("Invalid or expired OTP.", [], false, [], 422);
    }



    public function resendOtp(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|email|exists:users,email',
            'phone' => 'sometimes|string', // اختياري
        ]);

        $user = User::where('email', $data['email'])->first();

        // خُد الرقم من الريكوست لو مبعوت، وإلا من البروفايل
        $phone = $data['phone'] ?? $user->phone;

        if (blank($phone)) {
            return sendResponse(
                'No phone number available for this user.',
                [],    // data
                false, // success
                [],    // meta
                422    // status
            );
        }

        $phone = $this->normalizePhone($phone, '+968'); // بدّل كود الدولة حسب بيئتك

        // يفضّل استخدام الـ Container عشان الـ dependencies تـتحقن
        app(\App\Services\OtpService::class)->generateAndSendOtp($user, $phone);

        return sendResponse(
            'OTP sent successfully to your phone.',
            [],   // data
            true, // success
            [],   // meta
            200
        );
    }

    private function normalizePhone(string $raw, string $defaultCc = '+968'): string
    {
        // احذف أي شيء غير أرقام أو +
        $p = preg_replace('/[^\d+]+/', '', $raw) ?? '';

        // 00XXXX -> +XXXX
        if (str_starts_with($p, '00')) {
            $p = '+' . substr($p, 2);
        }

        // لو مفيش + في البداية، ضيف كود الدولة، وشيل الصفر الأول لو موجود
        if (!str_starts_with($p, '+')) {
            $p = ltrim($p, '0');
            $p = $defaultCc . $p;
        }

        return $p;
    }


    public function upload_documents(Request $request)
    {
        $request->validate([
            "company_name" => "required",
            "id_card" => "required",
            "license" => "required",
            "car_ownership_id" => "required",
            "profile_image" => "required",
        ]);
        DB::beginTransaction();
        try {
            $user = user();

            $driver = $user->driver;

            $driver->company_name = $request->company_name;
            $driver->id_card = uploadFile($request->id_card);
            $driver->license = uploadFile($request->license);
            $driver->profile_image = uploadFile($request->profile_image);
            $driver->car_ownership_id = uploadFile($request->car_ownership_id);
            $driver->save();

            DB::commit();
        } catch (QueryException $e) {
            DB::rollBack();
            return sendResponse("Error Occured.", [], false, [$e->getMessage()], 422);
        }
        return sendResponse("Documents uploaded successfully.", [$user->load('driver')]);
    }

    public function approve(Request $request)
    {
        $request->validate([
            "driver_id" => "required",
        ]);

        $driver = Driver::find($request->driver_id);

        $driver->status = "approved";
        $driver->save();

        return sendResponse("Driver approved successfully.", [$driver->load('user')]);
    }

    public function reject(Request $request)
    {
        $request->validate([
            "driver_id" => "required|exists:drivers,id",
            "reason" => "required|string|max:2000",
        ]);

        $driver = Driver::findOrFail($request->driver_id);
        $driver->status = "rejected";
        $driver->rejection_reason = $request->reason;
        $driver->save();

        return sendResponse("Driver rejected successfully.", [$driver->load('user')]);
    }
    public function send_nearby_notification(Request $request)
    {
        $request->validate([
            "tracking_no" => "required",
        ]);

        $shipment = Shipment::with("consignee")->where("tracking_no", $request->tracking_no)->first();

        $shipment->consignee->notify(new DriverNearbyNotification($shipment));

        return sendResponse("Notification sent successfully.", [$shipment->load('consignee')]);
    }

    public function storeStop(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'cellphone' => 'required|string|max:50',

            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
        ]);

        $user = user();

        if (!$user || !$user->driver || !$user->driver->is_guest) {
            return sendResponse("Not authorized as guest driver.", [], false, [], 403);
        }

        $latitude = $request->input('latitude');
        $longitude = $request->input('longitude');

        if ($latitude === null || $longitude === null) {
            return sendResponse("Latitude and Longitude are required.", [], false, [], 422);
        }

        try {
            DB::beginTransaction();

            $consignee = new Consignee();
            $consignee->name = $request->name;
            $consignee->cellphone = $request->cellphone;
            $consignee->latitude = (string) $latitude;
            $consignee->longitude = (string) $longitude;
            $consignee->is_guest = true;

            // $consignee->owner()->associate($user->driver);

            $consignee->save();

            DB::commit();

            return sendResponse("Guest stop created successfully.", [$consignee], true, [], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            return sendResponse("Error Occurred.", [], false, [$e->getMessage()], 422);
        }
    }
    public function listStops(Request $request)
    {
        $user = user();
        if (!$user || !$user->driver || !$user->driver->is_guest) {
            return sendResponse("Not authorized as guest driver.", [], false, [], 403);
        }

        $query = Consignee::query()
            ->where('is_guest', true)
            // ->where('owner_type', Driver::class)
            // ->where('owner_id', $user->driver->id)
            ->orderByDesc('created_at');

        if ($search = $request->query('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('cellphone', 'like', "%{$search}%");
            });
        }

        $consignees = $query->paginate(10);

        return sendResponse("Guest stops retrieved successfully.", $consignees);
    }




    public function statusSelf(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return sendResponse("Unauthenticated", [], false, [], 401);
        }

        $driver = Driver::where('user_id', $user->id)->first();

        if (!$driver) {
            return sendResponse("Driver not found for this user.", [], false, [], 404);
        }

        if (!$driver->is_guest) {
            return sendResponse("Not a guest driver.", [], false, [], 403);
        }

        $since = $request->query('since');
        $updatedAt = $driver->updated_at ?: now();

        $docsComplete = filled($driver->id_card)
            && filled($driver->license)
            && filled($driver->car_ownership_id);

        $changed = true;
        if ($since) {
            try {
                $changed = $updatedAt->gt(Carbon::parse($since));
            } catch (\Throwable $e) {
            }
        }

        $payload = [
            'driver_id' => $driver->id,
            'status' => $driver->status,
            'rejection_reason' => $driver->rejection_reason,
            'updated_at' => $updatedAt->toIso8601String(),
            'documents' => [
                'id_card' => (bool) $driver->id_card,
                'license' => (bool) $driver->license,
                'car_ownership_id' => (bool) $driver->car_ownership_id,
                'complete' => $docsComplete,
            ],

            'changed' => $changed,
        ];

        return sendResponse('Driver status retrieved', $payload);
    }

    public function destroy(Request $request, $driver)
    {
        $deleteUser = (bool) $request->boolean('delete_user', false);

        $deleteFiles = (bool) $request->boolean('delete_files', false);

        $driver = \App\Models\Driver::with('user.roles')->find($driver);

        if (!$driver) {
            return sendResponse("Driver not found.", [], false, [], 404);
        }

        if (!$driver->is_guest) {
            return sendResponse("Cannot delete a non-guest driver.", [], false, [], 403);
        }

        try {
            DB::beginTransaction();

            if ($deleteFiles) {
                foreach (['id_card', 'license', 'car_ownership_id', 'profile_image'] as $col) {
                    if (!empty($driver->{$col})) {
                        try {
                            Storage::disk('public')->delete($driver->{$col});
                        } catch (\Throwable $e) {
                            \Log::warning("Failed deleting driver file: {$col} => " . $e->getMessage());
                        }
                    }
                }
            }

            $user = $driver->user;

            $driver->delete();

            if ($user && $user->hasRole('Guest Driver')) {
                $user->removeRole('Guest Driver');
            }

            if ($deleteUser && $user) {
                $hasAnotherDriver = \App\Models\Driver::where('user_id', $user->id)->exists();
                $hasCriticalRole = $user->hasAnyRole(['Admin', 'Supervisor', 'Driver', 'Vendor Driver']);

                if (!$hasAnotherDriver && !$hasCriticalRole) {
                    $user->delete();
                }
            }

            DB::commit();

            return sendResponse("Guest driver deleted successfully.", [], true, [], 200);
        } catch (\Throwable $e) {
            DB::rollBack();
            return sendResponse("Error Occurred while deleting guest driver.", [], false, [$e->getMessage()], 422);
        }
    }

    /**
     * @OA\Get(
     *   path="/guest-drivers/verification_status",
     *   summary="Check OTP verification status",
     *   tags={"Other"},
     *   @OA\Parameter(name="email", in="query", required=false, @OA\Schema(type="string", format="email")),
     *   @OA\Parameter(name="user_id", in="query", required=false, @OA\Schema(type="integer")),
     *   @OA\Response(response=200, description="Verification status"),
     *   @OA\Response(response=422, description="Validation error"),
     *   @OA\Response(response=404, description="User not found")
     * )
     */
    public function verificationStatus(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        $user = $request->filled('user_id')
            ? User::find($request->integer('user_id'))
            : User::where('email', $request->string('email'))->first();

        if (!$user) {
            return sendResponse("User not found.", [], false, [], 404);
        }

        // $verifiedAt = $user->phone_verified_at;
        // $verified = !is_null($verifiedAt);

        $verifiedAt = $user->phone_verified_at
            ? \Carbon\Carbon::parse($user->phone_verified_at)
            : null;

        return sendResponse(
            "Verification status retrieved.",
            [
                'user_id' => $user->id,
                'email' => $user->email,
                'verified' => !is_null($verifiedAt),
                'verified_at' => $verifiedAt?->toIso8601String(),
            ]
        );
    }

    public function convert(Request $request, Driver $driver)
    {
        if (!$driver->is_guest) {
            return response()->json([
                'success' => false,
                'message' => 'Driver is already not a guest.'
            ], 422);
        }

        $validated = $request->validate([
            'license' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'id_card' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'car_ownership_id' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',

            'company_id' => 'nullable|exists:companies,id',
            'company_name' => 'nullable|string|max:255',

            'relatives' => 'nullable|string',

            'workspaces' => 'nullable|array',
            'workspaces.*.id' => 'required_with:workspaces|string',
            'workspaces.*.type' => 'required_with:workspaces|string|in:App\Models\Branch,App\Models\Station,App\Models\Hub',

            'file_name' => 'nullable|array',
            'file_name.*' => 'nullable|string|max:255',
            'files' => 'nullable|array',
            'files.*' => 'nullable|file|max:10240',
        ]);

        $hasAnyBaseDoc =
            $request->hasFile('license') ||
            $request->hasFile('id_card') ||
            $request->hasFile('car_ownership_id');

        $hasAnyDynFile = false;
        if (is_array($request->file('files'))) {
            foreach ($request->file('files') as $f) {
                if ($f && $f->isValid()) {
                    $hasAnyDynFile = true;
                    break;
                }
            }
        }

        if (!$hasAnyBaseDoc && !$hasAnyDynFile) {
            return response()->json([
                'success' => false,
                'message' => 'Please upload at least one document image or attach at least one file.'
            ], 422);
        }

        return DB::transaction(function () use ($request, $driver) {

            if ($request->filled('company_id')) {
                $driver->company_id = $request->input('company_id');
            } elseif ($request->filled('company_name')) {
                $company = \App\Models\Company::firstOrCreate(
                    ['name' => $request->input('company_name')],
                    []
                );
                $driver->company_id = $company->id;
            }

            foreach (['license', 'id_card', 'car_ownership_id'] as $field) {
                if ($request->hasFile($field)) {
                    $uploadedPath = uploadFile($request->file($field), 'public/driver_files');
                    if ($uploadedPath) {
                        $driver->{$field} = $uploadedPath;
                    }
                }
            }

            $driver->is_guest = 0;

            $driver->owner_id = facility("id");
            $driver->owner_type = facility("type");

            $driver->save();

            $user = $driver->user()->firstOrFail();

            $user->forceFill([
                'owner_id' => facility("id"),
                'owner_type' => facility("type"),
            ])->save();

            try {
                $user->syncRoles('Driver');
            } catch (\Throwable $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unable to assign role.',
                    'error' => $e->getMessage(),
                ], 422);
            }

            if ($request->filled('relatives')) {
                $decoded = json_decode($request->input('relatives'), true);
                if (is_array($decoded)) {
                    foreach ($decoded as $rel) {
                        $name = trim($rel['name'] ?? '');
                        $relation = trim($rel['relation'] ?? '');
                        $phone = trim($rel['phone'] ?? '');
                        if ($name && $relation && $phone) {
                            \App\Models\DriverRelative::firstOrCreate([
                                'driver_id' => $user->id,
                                'name' => $name,
                                'phone' => $phone,
                                'relation' => $relation,
                            ]);
                        }
                    }
                }
            }

            if ($request->has('file_name') && is_array($request->file_name)) {
                $uploadedFiles = $request->file('files', []);
                foreach ($request->file_name as $index => $name) {
                    $file = $uploadedFiles[$index] ?? null;
                    if ($file && $file->isValid() && $name) {
                        $uploadedPath = uploadFile($file, 'public/driver_files');
                        if ($uploadedPath) {
                            \App\Models\DriverFile::firstOrCreate([
                                'driver_id' => $user->id,
                                'name' => $name,
                                'file' => $uploadedPath
                            ]);
                        }
                    }
                }
            }

            \App\Models\Account::firstOrCreate([
                "accountable_id" => $user->id,
                "accountable_type" => \App\Models\User::class,
            ]);

            $defaultMerchantDeliveryBonuses = \App\Models\Setting::where('key', 'default_driver_delivery_bonuses')->value('value') ?? 1;
            $defaultMerchantPickupyBonuses = \App\Models\Setting::where('key', 'default_driver_pickup_bonuses')->value('value') ?? 1;

            $states = \App\Models\State::select('id')->get();
            foreach ($states as $state) {
                \App\Models\DriverBonus::firstOrCreate(
                    [
                        'driver_id' => $user->id,
                        'state_id' => $state->id,
                    ],
                    [
                        'delivery_bonus' => $defaultMerchantDeliveryBonuses,
                        'pickup_bonus' => $defaultMerchantPickupyBonuses,
                    ]
                );
            }

            $notifyUserId = $user->id;
            $notifyDriverId = $driver->id;
            $notifyName = $user->name;

            $notificationTitle = 'تم تفعيل حسابك كسائق';
            $notificationBody = "يا {$notifyName}، تم تحويل حسابك من زائر إلى سائق بنجاح، وتقدر دلوقتي تستقبل مهام التوصيل والاستلام.";

            $notificationData = [
                'type' => 'driver_converted',
                'user_id' => (string) $notifyUserId,
                'driver_id' => (string) $notifyDriverId,
                'timestamp' => (string) now()->timestamp,
            ];

            create_notification(
                $user,
                $notificationTitle,
                $notificationBody,
                $notificationData,
                'driver_converted',
                false
            );

            DB::afterCommit(function () use ($notifyUserId, $notifyDriverId, $notifyName, $notificationTitle, $notificationBody, $notificationData) {
                try {
                    $fcm = resolve(\App\Services\FcmService::class);

                    $payloadData = array_merge($notificationData, [
                        'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                    ]);

                    $fcm->sendToTopic("driver_{$notifyUserId}", $notificationTitle, $notificationBody, $payloadData);
                } catch (\Throwable $e) {
                    \Log::error('FCM (driver_converted) send failed: ' . $e->getMessage());
                }
            });

            return response()->json([
                'success' => true,
                'message' => 'Converted to driver successfully.',
                'data' => [
                    'driver' => $driver->fresh(),
                    'user' => $user->fresh()->load('roles'),
                ],
            ]);
        });
    }


}