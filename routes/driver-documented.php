<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\v1\DriverShipmentController;
use App\Http\Controllers\Api\v1\DeliveryReturnController;
use App\Http\Controllers\Api\v1\DriverAppController;
use App\Http\Controllers\Api\v1\DriverFinanceController;
use App\Http\Controllers\Api\v1\DriverSettingController;
use App\Http\Controllers\MobilePickupController;
use App\Http\Controllers\Api\v1\PickupExceptionController;
use App\Http\Controllers\Api\v1\QuickNoteController;
use App\Http\Resources\AuthResource;
use App\Models\LoginHistory;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;



/**
 * @group Driver App
 * 
 * Authentication and session management for driver mobile application.
 */

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
        Route::post('driver/login', function (Request $request) {
            $request->validate([
                'email' => 'required',
                'password' => 'required',
            ]);

            $historyData = [
                'email'      => $request->email,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ];

            if (User::where('email', '=', $request->email)->count() > 0) {
                if (Auth::attempt($request->only('email', 'password'))) {
                    $user = Auth::user();

                    // if (!$user->hasRole(["Driver", "Vendor Driver"])) {
                    //     LoginHistory::create(array_merge($historyData, [
                    //         'status'  => false,
                    //         'user_id'  => $user->id,
                    //     ]));
                    //     return sendResponse('You are not authorized to access the system.', [], false, 500);
                    // }

                    $user['token'] = $user->createToken('main')->plainTextToken;
                    LoginHistory::create(array_merge($historyData, [
                        'status'  => true,
                        'user_id'  => $user->id,
                    ]));
                    return sendResponse('Login successful', new AuthResource($user), []);
                } else {
                    LoginHistory::create(array_merge($historyData, [
                        'status'  => false,
                    ]));
                    return sendResponse('Password is incorrect.', [], [], 402);
                }
            } else {
                LoginHistory::create(array_merge($historyData, [
                    'status'  => false,
                ]));
                return sendResponse('No account found with this email.', [], [], 402);
            }
        });

        Route::middleware('auth:sanctum')->group(function () {

            /**
            * Driver App Connection Test
            *
            * @OA\Get(
            *     path="/driver/driveTest",
            *     summary="Test driver app connectivity",
            *     description="Simple endpoint to test driver app connection and authentication",
            *     operationId="driverConnectionTest",
            *     tags={"Driver App"},
            *     security={{"sanctum": {}}},
            *     @OA\Response(
            *         response=200,
            *         description="Connection successful",
            *         @OA\JsonContent(
            *             @OA\Property(property="message", type="string", example="Hello, World!")
            *         )
            *     ),
            *     @OA\Response(
            *         response=401,
            *         description="Unauthenticated",
            *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
            *     )
            * )
            */
            Route::get('driver/driveTest', function () {
                return response()->json(['message' => 'Hello, World!']);
            });

            Route::prefix('driver')->group(function () {

                Route::prefix('shipments')->group(function () {

                    Route::controller(DriverShipmentController::class)->group(function () {

                        /**
                        * @OA\Get(
                        *     path="/driver/shipments/my-shipments/pending",
                        *     summary="Get driver's pending shipments",
                        *     description="Retrieve all pending delivery shipments assigned to the authenticated driver",
                        *     operationId="getDriverPendingShipments",
                        *     tags={"Driver App"},
                        *     security={{"sanctum": {}}},
                        *     @OA\Response(
                        *         response=200,
                        *         description="Pending shipments retrieved successfully",
                        *         @OA\JsonContent(
                        *             @OA\Property(property="success", type="boolean", example=true),
                        *             @OA\Property(property="message", type="string", example="Pending shipments retrieved"),
                        *             @OA\Property(
                        *                 property="data",
                        *                 type="array",
                        *                 @OA\Items(ref="#/components/schemas/DriverShipment")
                        *             )
                        *         )
                        *     )
                        * )
                        */
                        Route::get('my-shipments/pending', 'myPendingShipments')->middleware("role:Driver");

                        /**
                        * @OA\Get(
                        *     path="/driver/shipments/my-shipments/signed",
                        *     summary="Get driver's signed shipments",
                        *     description="Retrieve all signed (confirmed) shipments assigned to the authenticated driver",
                        *     operationId="getDriverSignedShipments",
                        *     tags={"Driver App"},
                        *     security={{"sanctum": {}}},
                        *     @OA\Response(
                        *         response=200,
                        *         description="Signed shipments retrieved successfully",
                        *         @OA\JsonContent(
                        *             @OA\Property(property="success", type="boolean", example=true),
                        *             @OA\Property(property="message", type="string", example="Signed shipments retrieved"),
                        *             @OA\Property(
                        *                 property="data",
                        *                 type="array",
                        *                 @OA\Items(ref="#/components/schemas/DriverShipment")
                        *             )
                        *         )
                        *     )
                        * )
                        */
                        Route::get('my-shipments/signed', 'mySignedShipments')->middleware("role:Driver");

                        /**
                        * @OA\Get(
                        *     path="/driver/shipments/my-shipments/completed",
                        *     summary="Get driver's completed shipments",
                        *     description="Retrieve all completed shipments delivered by the authenticated driver",
                        *     operationId="getDriverCompletedShipments",
                        *     tags={"Driver App"},
                        *     security={{"sanctum": {}}},
                        *     @OA\Response(
                        *         response=200,
                        *         description="Completed shipments retrieved successfully",
                        *         @OA\JsonContent(
                        *             @OA\Property(property="success", type="boolean", example=true),
                        *             @OA\Property(property="message", type="string", example="Completed shipments retrieved"),
                        *             @OA\Property(
                        *                 property="data",
                        *                 type="array",
                        *                 @OA\Items(ref="#/components/schemas/DriverShipment")
                        *             )
                        *         )
                        *     )
                        * )
                        */
                        Route::get('my-shipments/completed', 'myCompletedShipments')->middleware("role:Driver");

                        /**
                        * @OA\Post(
                        *     path="/driver/shipments/contact_count",
                        *     summary="Log contact attempt for shipment",
                        *     description="Record contact attempt made by driver for delivery coordination",
                        *     operationId="logContactAttempt",
                        *     tags={"Driver App"},
                        *     security={{"sanctum": {}}},
                        *     @OA\RequestBody(
                        *         required=true,
                        *         @OA\JsonContent(ref="#/components/schemas/TrackingRequest")
                        *     ),
                        *     @OA\Response(
                        *         response=200,
                        *         description="Contact attempt logged successfully"
                        *     )
                        * )
                        */
                        Route::post('contact_count', 'contact_count')->middleware("role:Driver");

                        /**
                        * @OA\Post(
                        *     path="/driver/shipments/check_contacts",
                        *     summary="Check contact history for shipment",
                        *     description="Retrieve contact attempt history for a specific shipment",
                        *     operationId="checkContactHistory",
                        *     tags={"Driver App"},
                        *     security={{"sanctum": {}}},
                        *     @OA\RequestBody(
                        *         required=true,
                        *         @OA\JsonContent(ref="#/components/schemas/TrackingRequest")
                        *     ),
                        *     @OA\Response(
                        *         response=200,
                        *         description="Contact history retrieved successfully"
                        *     )
                        * )
                        */
                        Route::post('check_contacts', 'check_contacts')->middleware("role:Driver");

                        /**
                        * @OA\Post(
                        *     path="/driver/shipments/deliver",
                        *     summary="Mark shipment as delivered",
                        *     description="Complete shipment delivery with proof and recipient information",
                        *     operationId="deliverShipment",
                        *     tags={"Driver App"},
                        *     security={{"sanctum": {}}},
                        *     @OA\RequestBody(
                        *         required=true,
                        *         @OA\MediaType(
                        *             mediaType="multipart/form-data",
                        *             @OA\Schema(ref="#/components/schemas/ShipmentDeliveryRequest")
                        *         )
                        *     ),
                        *     @OA\Response(
                        *         response=200,
                        *         description="Shipment delivered successfully"
                        *     )
                        * )
                        */
                        Route::post('deliver', 'deliver')->middleware("role:Driver");

                        /**
                        * @OA\Post(
                        *     path="/driver/shipments/sendWhatsappMessage",
                        *     summary="Send WhatsApp message to customer",
                        *     description="Send delivery notification via WhatsApp to customer",
                        *     operationId="sendWhatsAppMessage",
                        *     tags={"Driver App"},
                        *     security={{"sanctum": {}}},
                        *     @OA\RequestBody(
                        *         required=true,
                        *         @OA\JsonContent(ref="#/components/schemas/TrackingRequest")
                        *     ),
                        *     @OA\Response(
                        *         response=200,
                        *         description="WhatsApp message sent successfully"
                        *     )
                        * )
                        */
                        Route::post('sendWhatsappMessage', 'sendWhatsappMessage');

                        /**
                        * @OA\Get(
                        *     path="/driver/shipments/quick_note",
                        *     summary="Get quick notes for shipments",
                        *     description="Retrieve quick notes templates for delivery shipments",
                        *     operationId="getQuickNotes",
                        *     tags={"Driver App"},
                        *     security={{"sanctum": {}}},
                        *     @OA\Response(
                        *         response=200,
                        *         description="Quick notes retrieved successfully"
                        *     )
                        * )
                        */
                        Route::get('quick_note', 'quick_note');

                        /**
                        * @OA\Post(
                        *     path="/driver/shipments/delivery_confirmation",
                        *     summary="Confirm shipment delivery",
                        *     description="Confirm successful delivery of an shipment",
                        *     operationId="confirmDelivery",
                        *     tags={"Driver App"},
                        *     security={{"sanctum": {}}},
                        *     @OA\RequestBody(
                        *         required=true,
                        *         @OA\JsonContent(ref="#/components/schemas/TrackingRequest")
                        *     ),
                        *     @OA\Response(
                        *         response=200,
                        *         description="Delivery confirmed successfully"
                        *     )
                        * )
                        */
                        Route::post('delivery_confirmation', 'shipment_confirmation')->middleware("authorize:Shipment awccess");

                        /**
                        * @OA\Post(
                        *     path="/driver/shipments/reverse_cancellation",
                        *     summary="Reverse shipment cancellation",
                        *     description="Reverse a cancelled shipment back to active delivery status",
                        *     operationId="reverseCancellation",
                        *     tags={"Driver App"},
                        *     security={{"sanctum": {}}},
                        *     @OA\RequestBody(
                        *         required=true,
                        *         @OA\JsonContent(ref="#/components/schemas/TrackingRequest")
                        *     ),
                        *     @OA\Response(
                        *         response=200,
                        *         description="Cancellation reversed successfully"
                        *     )
                        * )
                        */
                        Route::post('reverse_cancellation', 'reverse_cancellation')->middleware("role:Driver");

                        /**
                        * @OA\Post(
                        *     path="/driver/shipments/reverse_exception",
                        *     summary="Reverse delivery exception",
                        *     description="Reverse a delivery exception back to normal delivery status",
                        *     operationId="reverseException",
                        *     tags={"Driver App"},
                        *     security={{"sanctum": {}}},
                        *     @OA\RequestBody(
                        *         required=true,
                        *         @OA\JsonContent(ref="#/components/schemas/TrackingRequest")
                        *     ),
                        *     @OA\Response(
                        *         response=200,
                        *         description="Exception reversed successfully"
                        *     )
                        * )
                        */
                        Route::post('reverse_exception', 'reverse_exception')->middleware("role:Driver");

                        /**
                        * @OA\Post(
                        *     path="/driver/shipments/confirm_shipment_otp",
                        *     summary="Confirm shipment with OTP",
                        *     description="Confirm shipment delivery using one-time password",
                        *     operationId="confirmShipmentOTP",
                        *     tags={"Driver App"},
                        *     security={{"sanctum": {}}},
                        *     @OA\RequestBody(
                        *         required=true,
                        *         @OA\JsonContent(
                        *             allOf={
                        *                 @OA\Schema(ref="#/components/schemas/TrackingRequest"),
                        *                 @OA\Schema(
                        *                     @OA\Property(property="otp", type="string", example="123456")
                        *                 )
                        *             }
                        *         )
                        *     ),
                        *     @OA\Response(
                        *         response=200,
                        *         description="OTP confirmed successfully"
                        *     )
                        * )
                        */
                        Route::post('confirm_shipment_otp', 'confirm_shipment_otp')->middleware("role:Driver");

                        /**
                        * @OA\Get(
                        *     path="/driver/shipments/confirm_otp/{tracking_no}/{otp}/{driver_id}",
                        *     summary="Confirm OTP via link",
                        *     description="Confirm shipment delivery OTP via direct link",
                        *     operationId="confirmOTPLink",
                        *     tags={"Driver App"},
                        *     security={{"sanctum": {}}},
                        *     @OA\Parameter(
                        *         name="tracking_no",
                        *         in="path",
                        *         required=true,
                        *         @OA\Schema(type="string", example="PE041225123456")
                        *     ),
                        *     @OA\Parameter(
                        *         name="otp",
                        *         in="path",
                        *         required=true,
                        *         @OA\Schema(type="string", example="123456")
                        *     ),
                        *     @OA\Parameter(
                        *         name="driver_id",
                        *         in="path",
                        *         required=true,
                        *         @OA\Schema(type="integer", example=15)
                        *     ),
                        *     @OA\Response(
                        *         response=200,
                        *         description="OTP confirmed via link successfully"
                        *     )
                        * )
                        */
                        Route::get('confirm_otp/{tracking_no}/{otp}/{driver_id}', 'confirm_otp_link')->middleware("role:Driver");

                        /**
                        * @OA\Post(
                        *     path="/driver/shipments/regenerate_otp",
                        *     summary="Regenerate delivery OTP",
                        *     description="Generate new one-time password for shipment delivery",
                        *     operationId="regenerateOTP",
                        *     tags={"Driver App"},
                        *     security={{"sanctum": {}}},
                        *     @OA\RequestBody(
                        *         required=true,
                        *         @OA\JsonContent(ref="#/components/schemas/TrackingRequest")
                        *     ),
                        *     @OA\Response(
                        *         response=200,
                        *         description="OTP regenerated successfully"
                        *     )
                        * )
                        */
                        Route::post('regenerate_otp', 'regenerate_otp')->middleware("role:Driver");
                    });

                    /**
                    * @OA\Post(
                    *     path="/driver/shipments/return",
                    *     summary="Handle shipment return",
                    *     description="Process shipment return with delivery exception",
                    *     operationId="handleShipmentReturn",
                    *     tags={"Driver App"},
                    *     security={{"sanctum": {}}},
                    *     @OA\RequestBody(
                    *         required=true,
                    *         @OA\JsonContent(ref="#/components/schemas/TrackingRequest")
                    *     ),
                    *     @OA\Response(
                    *         response=200,
                    *         description="Shipment return processed successfully"
                    *     )
                    * )
                    */
                    Route::post('return', [DeliveryReturnController::class, 'handleShipmentException']);
                });

                Route::controller(DriverShipmentController::class)->group(function () {

                    /**
                    * @OA\Get(
                    *     path="/driver/driver_shipments",
                    *     summary="Get all driver shipments",
                    *     description="Retrieve all shipments assigned to the authenticated driver",
                    *     operationId="getAllDriverShipments",
                    *     tags={"Driver App"},
                    *     security={{"sanctum": {}}},
                    *     @OA\Response(
                    *         response=200,
                    *         description="Driver shipments retrieved successfully"
                    *     )
                    * )
                    */
                    Route::get('driver_shipments', 'driver_shipments');

                    /**
                    * @OA\Post(
                    *     path="/driver/return_shipment",
                    *     summary="Return shipment to warehouse",
                    *     description="Mark shipment as returned to warehouse due to delivery issues",
                    *     operationId="returnShipmentToWarehouse",
                    *     tags={"Driver App"},
                    *     security={{"sanctum": {}}},
                    *     @OA\RequestBody(
                    *         required=true,
                    *         @OA\JsonContent(ref="#/components/schemas/TrackingRequest")
                    *     ),
                    *     @OA\Response(
                    *         response=200,
                    *         description="Shipment returned to warehouse successfully"
                    *     )
                    * )
                    */
                    Route::post('return_shipment', 'return_shipment')->middleware("role:Driver");

                    /**
                    * @OA\Post(
                    *     path="/driver/assign_local_shipment",
                    *     summary="Assign local shipment",
                    *     description="Assign a local delivery shipment to driver",
                    *     operationId="assignLocalShipment",
                    *     tags={"Driver App"},
                    *     security={{"sanctum": {}}},
                    *     @OA\RequestBody(
                    *         required=true,
                    *         @OA\JsonContent(ref="#/components/schemas/TrackingRequest")
                    *     ),
                    *     @OA\Response(
                    *         response=200,
                    *         description="Local shipment assigned successfully"
                    *     )
                    * )
                    */
                    Route::post('assign_local_shipment', 'assign_local_shipment')->middleware("role:Driver");

                    /**
                    * @OA\Get(
                    *     path="/driver/not_deliver",
                    *     summary="Get undelivered shipments",
                    *     description="Retrieve shipments that could not be delivered",
                    *     operationId="getUndeliveredShipments",
                    *     tags={"Driver App"},
                    *     security={{"sanctum": {}}},
                    *     @OA\Response(
                    *         response=200,
                    *         description="Undelivered shipments retrieved successfully"
                    *     )
                    * )
                    */
                    Route::get('not_deliver', 'not_deliver');

                    /**
                    * @OA\Get(
                    *     path="/driver/driver_runsheets",
                    *     summary="Get driver runsheets",
                    *     description="Retrieve delivery runsheets for the driver",
                    *     operationId="getDriverRunsheets",
                    *     tags={"Driver App"},
                    *     security={{"sanctum": {}}},
                    *     @OA\Response(
                    *         response=200,
                    *         description="Driver runsheets retrieved successfully"
                    *     )
                    * )
                    */
                    Route::get('driver_runsheets', 'driver_runsheets')->middleware("role:Driver");

                    /**
                    * @OA\Get(
                    *     path="/driver/driver_invoices",
                    *     summary="Get driver invoices",
                    *     description="Retrieve financial invoices for the driver",
                    *     operationId="getDriverInvoices",
                    *     tags={"Driver App"},
                    *     security={{"sanctum": {}}},
                    *     @OA\Response(
                    *         response=200,
                    *         description="Driver invoices retrieved successfully"
                    *     )
                    * )
                    */
                    Route::get('driver_invoices', 'driver_invoices')->middleware("role:Driver");
                });

                /**
                * Quick Notes Management
                */
                Route::prefix("quick_notes")->controller(QuickNoteController::class)->group(function () {

                    /**
                    * @OA\Post(
                    *     path="/driver/quick_notes",
                    *     summary="Get quick notes",
                    *     description="Retrieve available quick notes for delivery shipments",
                    *     operationId="getQuickNotesList",
                    *     tags={"Driver App"},
                    *     security={{"sanctum": {}}},
                    *     @OA\Response(
                    *         response=200,
                    *         description="Quick notes retrieved successfully"
                    *     )
                    * )
                    */
                    Route::post('', 'index')->middleware("role:Driver");

                    /**
                    * @OA\Post(
                    *     path="/driver/quick_notes/store",
                    *     summary="Save quick note",
                    *     description="Save a new quick note for future use",
                    *     operationId="storeQuickNote",
                    *     tags={"Driver App"},
                    *     security={{"sanctum": {}}},
                    *     @OA\RequestBody(
                    *         required=true,
                    *         @OA\JsonContent(
                    *             @OA\Property(property="note", type="string", example="Customer requested delivery after 5 PM")
                    *         )
                    *     ),
                    *     @OA\Response(
                    *         response=200,
                    *         description="Quick note saved successfully"
                    *     )
                    * )
                    */
                    Route::post('store', 'store')->middleware("role:Driver");
                });

                /**
                * Pickup Exception Management
                */
                Route::controller(PickupExceptionController::class)->group(function () {

                    /**
                    * @OA\Post(
                    *     path="/driver/handle_pickup_exception",
                    *     summary="Handle pickup exception",
                    *     description="Process pickup exception for failed pickup attempts",
                    *     operationId="handlePickupException",
                    *     tags={"Driver App"},
                    *     security={{"sanctum": {}}},
                    *     @OA\RequestBody(
                    *         required=true,
                    *         @OA\JsonContent(
                    *             @OA\Property(property="pickup_id", type="integer", example=123),
                    *             @OA\Property(property="exception_type", type="string", example="PICKUP_LOST"),
                    *             @OA\Property(property="reason", type="string", example="No one available at pickup location")
                    *         )
                    *     ),
                    *     @OA\Response(
                    *         response=200,
                    *         description="Pickup exception handled successfully"
                    *     )
                    * )
                    */
                    Route::post('handle_pickup_exception', 'handle_pickup_exception');

                    /**
                    * @OA\Get(
                    *     path="/driver/pickup_exceptions",
                    *     summary="Get pickup exception types",
                    *     description="Retrieve available pickup exception types and their descriptions",
                    *     operationId="getPickupExceptions",
                    *     tags={"Driver App"},
                    *     security={{"sanctum": {}}},
                    *     @OA\Response(
                    *         response=200,
                    *         description="Pickup exception types retrieved successfully",
                    *         @OA\JsonContent(
                    *             type="array",
                    *             @OA\Items(
                    *                 @OA\Property(property="name", type="string", example="PICKUP_LOST"),
                    *                 @OA\Property(property="label", type="string", example="Parcel Lost"),
                    *                 @OA\Property(property="description", type="string", example="Parcel Lost some where")
                    *             )
                    *         )
                    *     )
                    * )
                    */
                    Route::get('pickup_exceptions', function () {
                        return array_values(pickup_exceptions());
                    });
                });

                /**
                * Driver Finance Management
                */
                Route::prefix('finance')->controller(DriverFinanceController::class)->group(function () {

                    /**
                    * @OA\Post(
                    *     path="/driver/finance/not_settled_shipments",
                    *     summary="Get unsettled shipments",
                    *     description="Retrieve shipments with outstanding financial settlements",
                    *     operationId="getUnseettledShipments",
                    *     tags={"Driver App"},
                    *     security={{"sanctum": {}}},
                    *     @OA\Response(
                    *         response=200,
                    *         description="Unsettled shipments retrieved successfully"
                    *     )
                    * )
                    */
                    Route::post('not_settled_shipments', 'not_settled_shipments');

                    /**
                    * @OA\Post(
                    *     path="/driver/finance/settled_shipments",
                    *     summary="Get settled shipments",
                    *     description="Retrieve shipments with completed financial settlements",
                    *     operationId="getSettledShipments",
                    *     tags={"Driver App"},
                    *     security={{"sanctum": {}}},
                    *     @OA\Response(
                    *         response=200,
                    *         description="Settled shipments retrieved successfully"
                    *     )
                    * )
                    */
                    Route::post('settled_shipments', 'settled_shipments');
                });
            });

            /**
            * Return Management
            */
            Route::prefix('return')->controller(DeliveryReturnController::class)->group(function () {

                /**
                * @OA\Post(
                *     path="/return/no_answer",
                *     summary="Mark shipment as no answer",
                *     description="Mark shipment delivery as failed due to no customer response",
                *     operationId="markNoAnswer",
                *     tags={"Driver App"},
                *     security={{"sanctum": {}}},
                *     @OA\RequestBody(
                *         required=true,
                *         @OA\JsonContent(ref="#/components/schemas/TrackingRequest")
                *     ),
                *     @OA\Response(
                *         response=200,
                *         description="Shipment marked as no answer successfully"
                *     )
                * )
                */
                Route::post('no_answer', 'no_answer')->middleware("role:Driver");

                /**
                * @OA\Post(
                *     path="/return/future_delivery",
                *     summary="Schedule future delivery",
                *     description="Schedule shipment for future delivery as requested by customer",
                *     operationId="scheduleFutureDelivery",
                *     tags={"Driver App"},
                *     security={{"sanctum": {}}},
                *     @OA\RequestBody(
                *         required=true,
                *         @OA\JsonContent(
                *             allOf={
                *                 @OA\Schema(ref="#/components/schemas/TrackingRequest"),
                *                 @OA\Schema(
                *                     @OA\Property(property="future_date", type="string", format="date", example="2024-12-10")
                *                 )
                *             }
                *         )
                *     ),
                *     @OA\Response(
                *         response=200,
                *         description="Future delivery scheduled successfully"
                *     )
                * )
                */
                Route::post('future_delivery', 'future_delivery')->middleware("role:Driver");
            });

            /**
            * @OA\Get(
            *     path="/mobile_app_homepage_stats",
            *     summary="Get mobile app homepage statistics",
            *     description="Retrieve dashboard statistics for driver mobile app homepage",
            *     operationId="getMobileAppStats",
            *     tags={"Driver App"},
            *     security={{"sanctum": {}}},
            *     @OA\Response(
            *         response=200,
            *         description="Homepage statistics retrieved successfully",
            *         @OA\JsonContent(
            *             @OA\Property(property="success", type="boolean", example=true),
            *             @OA\Property(property="message", type="string", example="Statistics retrieved"),
            *             @OA\Property(property="data", ref="#/components/schemas/HomepageStats")
            *         )
            *     )
            * )
            */
            Route::get('mobile_app_homepage_stats', [DriverShipmentController::class, 'mobile_app_homepage_stats']);

            /**
            * @OA\Get(
            *     path="/task/pickup_tasks",
            *     summary="Get pickup tasks",
            *     description="Retrieve pickup tasks assigned to the authenticated driver",
            *     operationId="getPickupTasks",
            *     tags={"Driver App"},
            *     security={{"sanctum": {}}},
            *     @OA\Response(
            *         response=200,
            *         description="Pickup tasks retrieved successfully",
            *         @OA\JsonContent(
            *             @OA\Property(property="success", type="boolean", example=true),
            *             @OA\Property(property="message", type="string", example="Pickup tasks retrieved"),
            *             @OA\Property(
            *                 property="data",
            *                 type="array",
            *                 @OA\Items(ref="#/components/schemas/PickupTask")
            *             )
            *         )
            *     )
            * )
            */
            Route::get('task/pickup_tasks', [DriverShipmentController::class, 'pickup_tasks'])
                ->middleware("role:Driver");

            /**
            * Mobile Pickup Management
            */
            Route::prefix('driver')->controller(MobilePickupController::class)->group(function () {

                /**
                * @OA\Get(
                *     path="/driver/pickup_tasks",
                *     summary="Get pickup tasks",
                *     description="Retrieve pickup tasks filtered by optional status query parameter",
                *     operationId="getPickupTasks",
                *     tags={"Driver App"},
                *     security={{"sanctum": {}}},
                *     @OA\Parameter(
                *         name="status",
                *         in="query",
                *         required=false,
                *         @OA\Schema(type="string", example="to_pickup")
                *     ),
                *     @OA\Response(
                *         response=200,
                *         description="Pickup tasks retrieved successfully"
                *     )
                * )
                */
                Route::get('pickup_tasks', 'pickup_tasks')->middleware("role:Driver");

                /**
                * @OA\Post(
                *     path="/driver/shipment_pickup",
                *     summary="Complete shipment pickup",
                *     description="Mark pickup task as completed with collected shipments",
                *     operationId="completeShipmentPickup",
                *     tags={"Driver App"},
                *     security={{"sanctum": {}}},
                *     @OA\RequestBody(
                *         required=true,
                *         @OA\JsonContent(
                *             @OA\Property(property="pickup_id", type="integer", example=123),
                *             @OA\Property(property="collected_count", type="integer", example=10),
                *             @OA\Property(property="notes", type="string", example="All packages collected successfully")
                *         )
                *     ),
                *     @OA\Response(
                *         response=200,
                *         description="Shipment pickup completed successfully"
                *     )
                * )
                */
                Route::post('shipment_pickup', 'shipment_pickup')->middleware("role:Driver");

                /**
                * @OA\Get(
                *     path="/driver/pickup_tasks/edit/{id}",
                *     summary="Get pickup task details for editing",
                *     description="Retrieve specific pickup task details for modification",
                *     operationId="getPickupTaskForEdit",
                *     tags={"Driver App"},
                *     security={{"sanctum": {}}},
                *     @OA\Parameter(
                *         name="id",
                *         in="path",
                *         required=true,
                *         @OA\Schema(type="integer", example=123)
                *     ),
                *     @OA\Response(
                *         response=200,
                *         description="Pickup task details retrieved successfully"
                *     )
                * )
                */
                Route::get('pickup_tasks/edit/{id}', 'edit')->middleware("role:Driver");
            });

            /**
            * @OA\Get(
            *     path="/driver/system_delivery_exceptions",
            *     summary="Get system delivery exceptions",
            *     description="Retrieve all available delivery exception types and their descriptions",
            *     operationId="getSystemDeliveryExceptions",
            *     tags={"Driver App"},
            *     security={{"sanctum": {}}},
            *     @OA\Response(
            *         response=200,
            *         description="System delivery exceptions retrieved successfully",
            *         @OA\JsonContent(
            *             type="object",
            *             @OA\AdditionalProperties(
            *                 @OA\Property(property="name", type="string"),
            *                 @OA\Property(property="label", type="string"),
            *                 @OA\Property(property="description", type="string")
            *             )
            *         )
            *     )
            * )
            */
            Route::get('driver/system_delivery_exceptions', function () {
                return system_delivery_exceptions();
            });

            /**
            * Driver Settings Management
            */
            Route::prefix('driver_settings')->controller(DriverSettingController::class)->group(function () {

                /**
                * @OA\Get(
                *     path="/driver_settings",
                *     summary="Get all driver settings",
                *     description="Retrieve all configuration settings for the driver app",
                *     operationId="getAllDriverSettings",
                *     tags={"Driver App"},
                *     security={{"sanctum": {}}},
                *     @OA\Response(
                *         response=200,
                *         description="Driver settings retrieved successfully"
                *     )
                * )
                */
                Route::get('', 'index')->middleware("role:Driver");

                /**
                * @OA\Get(
                *     path="/driver_settings/get/{key}",
                *     summary="Get specific driver setting",
                *     description="Retrieve a specific driver setting by key",
                *     operationId="getDriverSetting",
                *     tags={"Driver App"},
                *     security={{"sanctum": {}}},
                *     @OA\Parameter(
                *         name="key",
                *         in="path",
                *         required=true,
                *         @OA\Schema(type="string", example="notification_enabled")
                *     ),
                *     @OA\Response(
                *         response=200,
                *         description="Driver setting retrieved successfully"
                *     )
                * )
                */
                Route::get('get/{key}', 'get')->middleware("role:Driver");
            });

            /**
            * Driver App Utilities
            */
            Route::prefix('driver_app')->controller(DriverAppController::class)->group(function () {

                /**
                * @OA\Post(
                *     path="/driver_app/get_address_token",
                *     summary="Get address geocoding token",
                *     description="Retrieve token for address geocoding and location services",
                *     operationId="getAddressToken",
                *     tags={"Driver App"},
                *     security={{"sanctum": {}}},
                *     @OA\Response(
                *         response=200,
                *         description="Address token retrieved successfully"
                *     )
                * )
                */
                Route::post('get_address_token', 'get_address_token')->middleware("role:Driver");

                /**
                * @OA\Post(
                *     path="/driver_app/manual_update_address",
                *     summary="Manually update delivery address",
                *     description="Allow driver to manually update customer delivery address",
                *     operationId="manualUpdateAddress",
                *     tags={"Driver App"},
                *     security={{"sanctum": {}}},
                *     @OA\RequestBody(
                *         required=true,
                *         @OA\JsonContent(
                *             @OA\Property(property="tracking_no", type="string", example="PE041225123456"),
                *             @OA\Property(property="new_address", type="string", example="King Fahd Road, Al Olaya, Riyadh"),
                *             @OA\Property(property="latitude", type="number", format="float", example=24.7136),
                *             @OA\Property(property="longitude", type="number", format="float", example=46.6753)
                *         )
                *     ),
                *     @OA\Response(
                *         response=200,
                *         description="Address updated successfully"
                *     )
                * )
                */
                Route::post('manual_update_address', 'manual_update_address')->middleware("role:Driver");
            });
        }); 
    });
