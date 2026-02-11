<?php

use App\Http\Controllers\Api\v1\DriverPickupTaskController;
use App\Http\Controllers\Api\v1\MerchantPickupTaskController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\v1\DriverShipmentController;
use App\Http\Controllers\Api\v1\DeliveryReturnController;
use App\Http\Controllers\Api\v1\GoogleAuthController;
use App\Http\Controllers\Api\v1\MobileAppleController;
use App\Http\Controllers\Api\v1\DriverAppController;
use App\Http\Controllers\Api\v1\DriverFinanceController;
use App\Http\Controllers\Api\v1\DriverSettingController;
use App\Http\Controllers\Api\v1\DriverStatusController;
use App\Http\Controllers\Api\v1\GuestDriverController;
use App\Http\Controllers\Api\v1\GuestDriverShipmentController;
use App\Http\Controllers\Api\v1\InstantDeliveryController;
use App\Http\Controllers\Api\v1\MobilePickupController;
use App\Http\Controllers\Api\v1\PickupExceptionController;
use App\Http\Controllers\Api\v1\QuickNoteController;
use App\Http\Resources\AuthResource;
use App\Models\DeviceToken;
use App\Models\LoginHistory;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

/**
 * Driver Mobile App Login
 *
 * Authenticates drivers for mobile app access. Only users with "Driver" or "Vendor Driver"
 * roles are allowed to login through this endpoint.
 *
 * @OA\Post(
 *     path="/driver/login",
 *     summary="Driver mobile app authentication",
 *     description="
 * Authenticates drivers for mobile application access with role-based validation.
 *
 * **Features:**
 * - Role validation (Driver, Vendor Driver only)
 * - Login history tracking
 * - Personal access token generation
 * - Mobile-optimized response
 *
 * **Security:**
 * - Restricted to driver roles only
 * - IP and user agent logging
 * - Failed attempt tracking
 * ",
 *     operationId="driverLogin",
 *     tags={"Driver App"},
 *     @OA\RequestBody(
 *         required=true,
 *         description="Driver login credentials",
 *         @OA\JsonContent(ref="#/components/schemas/DriverLoginRequest")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Login successful",
 *         @OA\JsonContent(
 *             @OA\Property(property="success", type="boolean", example=true),
 *             @OA\Property(property="message", type="string", example="Login successful"),
 *             @OA\Property(property="data", ref="#/components/schemas/DriverUser"),
 *             @OA\Property(property="errors", type="array", @OA\Items())
 *         )
 *     ),
 *     @OA\Response(
 *         response=402,
 *         description="Authentication failed",
 *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
 *     ),
 *     @OA\Response(
 *         response=500,
 *         description="Unauthorized role",
 *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
 *     )
 * )
 */

Route::prefix('v1')
    ->group(function () {

        Route::post('auth/google/mobile', [GoogleAuthController::class, 'loginOrRegister']);
        Route::post('auth/apple/mobile', [MobileAppleController::class, 'loginOrRegister']);
        
        Route::post('driver/login', function (Request $request) {
            if (!$request->filled('login') && $request->filled('email')) {
                $request->merge(['login' => $request->email]);
            }
        
            $request->validate([
                'login' => 'required|string',
                'password' => 'required|string',
                'fcm_token' => 'nullable|string',
                'platform' => 'nullable|string',
            ]);
        
            $login = trim($request->input('login'));
            $historyData = [
                'email' => $login,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ];
        
            $isEmail = filter_var($login, FILTER_VALIDATE_EMAIL);
            $isPhone = preg_match('/^\+?\d{7,15}$/', $login);
        
            $user = null;
            if ($isEmail) {
                $user = \App\Models\User::where('email', $login)->first();
            } elseif ($isPhone) {
                $digits = ltrim($login, '+');
                $phoneSplit = splitPhoneNumber($login);
                $user = \App\Models\User::where('country_code', $phoneSplit['country_code'] ?? null)
                    ->where('phone', $phoneSplit['national_number'] ?? null)
                    ->first();
                if (!$user) {
                    $user = \App\Models\User::whereRaw("CONCAT(country_code, phone) = ?", [$digits])
                        ->orWhereRaw("CONCAT('+', country_code, phone) = ?", ['+' . $digits])
                        ->orWhere('phone', $digits)
                        ->first();
                }
            } else {
                $user = User::where('username', $login)->first();
            }
        
            if (!$user) {
                \App\Models\LoginHistory::create($historyData + ['status' => false]);
                return sendResponse('No account found for this identifier.', [], [], 402);
            }
        
            if (!Hash::check($request->password, $user->password)) {
                \App\Models\LoginHistory::create($historyData + ['status' => false, 'user_id' => $user->id]);
                return sendResponse('Password is incorrect.', [], [], 402);
            }
        
            Auth::login($user);
        
            if ($user->hasRole(["Vendor Driver", "Guest Driver"])) {
                if (empty($user->phone_verified_at) || !is_null($user->verification_code)) {
                    \App\Models\LoginHistory::create($historyData + ['status' => false, 'user_id' => $user->id]);
                    Auth::logout();
                    return sendResponse(
                        'Phone not verified. Please verify your phone via OTP before logging in.',
                        ['verification_required' => true],
                        false,
                        [],
                        403
                    );
                }
            }
        
            // if (!$user->hasRole(["Driver", "Vendor Driver", "Guest Driver"])) {
            //     \App\Models\LoginHistory::create($historyData + ['status' => false, 'user_id' => $user->id]);
            //     Auth::logout();
            //     return sendResponse('You are not authorized to access the system.', [], false, 500);
            // }
        $isGuest = $user->driver()
                        ->withoutGlobalScope(\App\Models\Scopes\ExcludeGuestDriversScope::class)
                        ->withTrashed()
                        ->first()?->is_guest ?? false;
            $user['token'] = $user->createToken('main')->plainTextToken;
            
        
            if ($request->filled('fcm_token')) {
                // Check if token exists for another user
                $existingToken = \App\Models\DeviceToken::where('token', $request->fcm_token)->first();
                if ($existingToken && $existingToken->user_id !== $user->id) {
                    Log::info("FCM Token transfer: from user {$existingToken->user_id} to user {$user->id}", [
                        'token' => substr($request->fcm_token, 0, 20) . '...',
                        'old_user_id' => $existingToken->user_id,
                        'new_user_id' => $user->id,
                    ]);
                }
        
                \App\Models\DeviceToken::updateOrCreate(
                    ['token' => $request->fcm_token],
                    ['user_id' => $user->id, 'platform' => $request->input('platform')]
                );
        
                try {
                    $fcm = resolve(\App\Services\FcmService::class);
                    $fcm->subscribeTokenToDriverTopic($user->id, $request->fcm_token);
                } catch (\Throwable $e) {
                    Log::warning('FCM subscribe failed: ' . $e->getMessage());
                }
            }
        
            \App\Models\LoginHistory::create($historyData + ['status' => true, 'user_id' => $user->id]);
                        $userArray = $user->toArray();
                        $userArray['is_guest'] = $user?->driver?->is_guest ?? false;
            // return sendResponse('Login successful', (new \App\Http\Resources\AuthResource($user))
            //     ->additional(['is_guest' => $isGuest ?? false]), [],extras:["is_guest" => $isGuest]);
             return response()->json([
                'message' => 'Login successful',
                'success' => true,
                'data' => new \App\Http\Resources\AuthResource($user),
                'is_guest' => $isGuest,
            ]);
        });
        
        // FCM Token Management Routes
        Route::middleware(['auth:sanctum'])->group(function () {
            Route::post('driver/register-fcm-token', [\App\Http\Controllers\Api\v1\DeviceTokenController::class, 'register']);
            Route::post('driver/remove-fcm-token', [\App\Http\Controllers\Api\v1\DeviceTokenController::class, 'remove']);
        });
        
        Route::middleware(['auth:sanctum', 'driver-not-on-hold'])->group(function () {
        
            /**
             * Driver API Test Endpoint
             *
             * @OA\Get(
             *     path="/driver/driveTest",
             *     summary="Test driver API connection",
             *     description="Simple test endpoint to verify driver API connectivity",
             *     operationId="driverApiTest",
             *     tags={"Driver App"},
             *     security={{"sanctum": {}}},
             *     @OA\Response(
             *         response=200,
             *         description="API test successful",
             *         @OA\JsonContent(
             *             @OA\Property(property="message", type="string", example="Hello, World!")
             *         )
             *     )
             * )
             */
            Route::get('driver/driveTest', function () {
                return response()->json(['message' => 'Hello, World!']);
            });
        
            Route::prefix('driver')->group(function () {
        
                Route::prefix('shipments')->group(function () {
        
                    Route::controller(DriverShipmentController::class)->middleware("role:Driver,Guest Driver,Vendor Driver")->group(function () {
                        Route::get('my-shipments/pending', 'myPendingShipments');
                        Route::get('my-shipments/signed', 'mySignedShipments');
                        Route::get('my-shipments/completed', 'myCompletedShipments');
                        Route::post('create', 'createShipment');
                        Route::post('contact_count', 'contact_count');
                        Route::post('check_contacts', 'check_contacts');
                        Route::post('deliver', 'deliver');
                        Route::post('sendWhatsappMessage', 'sendWhatsappMessage');
                        Route::post('delivery_confirmation', 'shipment_confirmation')->middleware("authorize:Shipment access");
                        Route::post('reverse_cancellation', 'reverse_cancellation');
                        Route::post('reverse_exception', 'reverse_exception');
                        Route::post('confirm_shipment_otp', 'confirm_shipment_otp');
                        Route::get('confirm_otp/{tracking_no}/{otp}/{driver_id}', 'confirm_otp_link');
                        Route::post('regenerate_otp', 'regenerate_otp');
                        Route::get('quick_note', 'quick_note');
                    });
        
                    Route::post('return', [DeliveryReturnController::class, 'handleShipmentException']);
                });
                Route::post('/pickup-tasks', [MerchantPickupTaskController::class, 'createByDriver']);
                Route::post('pickup-tasks/{task}/end', [DriverPickupTaskController::class, 'endTask']);
                Route::post('check_waybill', [DriverShipmentController::class, 'check_waybill']);
        
        
                Route::controller(DriverShipmentController::class)->group(function () {
                    Route::get('driver_shipments', 'driver_shipments');
                    Route::post('return_shipment', 'return_shipment')->middleware("role:Driver");
                    Route::post('assign_local_shipment', 'assign_local_shipment')->middleware("role:Driver");
                    Route::get('not_deliver', 'not_deliver');
                    Route::get('driver_runsheets', 'driver_runsheets')->middleware("role:Driver");
                    Route::get('driver_runsheets_new', 'driver_runsheets_new')->middleware("role:Driver");
                    Route::get('driver_invoices', 'driver_invoices')->middleware("role:Driver");
                });
        
                Route::prefix("quick_notes")->controller(QuickNoteController::class)->group(function () {
                    Route::post('', 'index')->middleware("role:Driver");
                    Route::post('store', 'store')->middleware("role:Driver");
                });
        
                Route::controller(PickupExceptionController::class)->group(function () {
                    Route::post('handle_pickup_exception', 'handle_pickup_exception');
                    /**
                     * Get Pickup Exceptions List
                     *
                     * @OA\Get(
                     *     path="/driver/pickup_exceptions",
                     *     summary="Get available pickup exceptions",
                     *     description="Retrieve list of available pickup exception types",
                     *     operationId="getPickupExceptions",
                     *     tags={"Driver App"},
                     *     security={{"sanctum": {}}},
                     *     @OA\Response(
                     *         response=200,
                     *         description="Pickup exceptions list",
                     *         @OA\JsonContent(
                     *             type="array",
                     *             @OA\Items(type="string", example="NO_ANSWER")
                     *         )
                     *     )
                     * )
                     */
                    Route::get('pickup_exceptions', function () {
                        return array_values(pickup_exceptions());
                    });
                });
        
        
                // DRIVER FINANCE
                Route::prefix('finance')->controller(DriverFinanceController::class)->group(function () {
                    Route::post('not_settled_shipments', 'not_settled_shipments');
                    Route::post('settled_shipments', 'settled_shipments');
                });
        
                Route::post('update_driver_location', [DriverStatusController::class, 'update_driver_location'])->middleware("role:Driver,Guest Driver,Vendor Driver");
            });
        
            Route::prefix('return')->controller(DeliveryReturnController::class)->group(function () {
                Route::post('no_answer', 'no_answer')->middleware("role:Driver");
                Route::post('future_delivery', 'future_delivery')->middleware("role:Driver");
            });
        
            Route::get('mobile_app_homepage_stats', [DriverShipmentController::class, 'mobile_app_homepage_stats']);
            Route::get('task/pickup_tasks', [DriverShipmentController::class, 'pickup_tasks'])
                ->middleware("role:Driver");
        
        
            Route::prefix('driver')->controller(MobilePickupController::class)->group(function () {
                Route::get('pickup_tasks', 'pickup_tasks')->middleware("role:Driver");
                Route::post('shipment_pickup', 'shipment_pickup')->middleware("role:Driver");
                Route::post('shipment_unpickup', 'shipment_unpickup')->middleware("role:Driver");
                Route::post('pickup_missed_list', 'pickup_missed_list')->middleware("role:Driver");
                Route::get('pickup_tasks/edit/{id}', 'edit')->middleware("role:Driver");
                Route::post('check-tracking-no', 'checkTrackingNo')->middleware("role:Driver");
            });
        
        
            /**
             * Get System Delivery Exceptions
             *
             * @OA\Get(
             *     path="/driver/system_delivery_exceptions",
             *     summary="Get system delivery exceptions",
             *     description="Retrieve list of available delivery exception types",
             *     operationId="getSystemDeliveryExceptions",
             *     tags={"Driver App"},
             *     security={{"sanctum": {}}},
             *     @OA\Response(
             *         response=200,
             *         description="Delivery exceptions list",
             *         @OA\JsonContent(
             *             type="array",
             *             @OA\Items(type="string", example="NO_ANSWER")
             *         )
             *     )
             * )
             */
            Route::get('driver/system_delivery_exceptions', function () {
                return system_delivery_exceptions();
            });
        
            Route::prefix('driver_settings')->controller(DriverSettingController::class)->group(function () {
                Route::get('', 'index')->middleware("role:Driver");
                Route::get('get/{key}', 'get')->middleware("role:Driver");
            });
        
            Route::prefix('driver_app')->controller(DriverAppController::class)->group(function () {
                Route::post('get_address_token', 'get_address_token')->middleware("role:Driver");
                Route::post('manual_update_address', 'manual_update_address')->middleware("role:Driver");
            });
        
            // Instant Delivery - Driver Shipment Management
            Route::prefix('driver/instant_deliveries')->controller(InstantDeliveryController::class)->middleware('role:Driver,Guest Driver,Vendor Driver')->group(function () {
                Route::get('', 'getAcceptedShipments');
                Route::get('offers', 'getOffers');
                Route::post('offers/accept', 'acceptOffer');
                Route::post('offers/reject', 'rejectOffer');
                Route::get('accepted-shipments', 'getAcceptedShipments');
                Route::post('pickup', 'pickupShipment');
                Route::post('deliver', 'deliverShipment');
                Route::post('exception', 'reportException');
            });
        });
        
        Route::prefix('guest_drivers')->group(function () {
            Route::post('register', [GuestDriverController::class, 'register']);
            Route::post('verify-otp', [GuestDriverController::class, 'verifyOtp']);
            Route::post('resend-otp', [GuestDriverController::class, 'resendOtp']);
            Route::controller(GuestDriverController::class)->middleware(["auth:sanctum"])->group(function () {
                Route::post('upload_documents', 'upload_documents');
                Route::post('send_nearby_notification', 'send_nearby_notification');
            });
            Route::get('verification_status', [GuestDriverController::class, 'verificationStatus']);
            Route::prefix('shipments')->controller(GuestDriverShipmentController::class)->middleware("auth:sanctum")->group(function () {
                Route::get('', 'index');
                Route::post('store', 'store');
            });
        });
    });
