<?php

use App\Http\Controllers\Api\v1\MerchantWalletController;
use App\Http\Controllers\Api\v1\MerchantAddressBookController;
use App\Http\Controllers\Api\v1\MerchantBranchController;
use App\Http\Controllers\Api\v1\MerchantCommissionController;
use App\Http\Controllers\Api\v1\MerchantController;
use App\Http\Controllers\Api\v1\MerchantDashboardController;   
use App\Http\Controllers\Api\v1\MerchantInvoiceController;
use App\Http\Controllers\Api\v1\MerchantNotificationController;
use App\Http\Controllers\Api\v1\MerchantShipmentController;
use App\Http\Controllers\Api\v1\MerchantShipmentLiveTrackingController;
use App\Http\Controllers\Api\v1\MerchantPaymentController;
use App\Http\Controllers\Api\v1\MerchantSettingController;
use App\Http\Controllers\Api\v1\MerchantTicketController;
use Illuminate\Support\Facades\Route;
use App\Http\Resources\AuthResource;
use App\Models\LoginHistory;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;







/**
 * Merchant Portal Login
 *
 * Authenticates merchants for portal access. Only users with "Merchant"
 * role are allowed to login through this endpoint.
 *
 * @OA\Post(
 *     path="/merchant/login",
 *     summary="Merchant portal authentication",
 *     description="
 * Authenticates merchants for portal access with role-based validation.
 *
 * **Features:**
 * - Role validation (Merchant only)
 * - Login history tracking
 * - Personal access token generation
 * - Portal-optimized response
 *
 * **Security:**
 * - Restricted to merchant roles only
 * - IP and user agent logging
 * - Failed attempt tracking
 * ",
 *     operationId="merchantLogin",
 *     tags={"Merchant Portal"},
 *     @OA\RequestBody(
 *         required=true,
 *         description="Merchant login credentials",
 *         @OA\JsonContent(ref="#/components/schemas/LoginRequest")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Login successful",
 *         @OA\JsonContent(
 *             @OA\Property(property="success", type="boolean", example=true),
 *             @OA\Property(property="message", type="string", example="Login successful"),
 *             @OA\Property(property="data", ref="#/components/schemas/AuthResponseData"),
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
        Route::prefix("merchant")->controller(MerchantShipmentController::class)->middleware(["auth:sanctum", "role:Merchant,Super Admin"])->group(function () {
            Route::get('dashboard', 'dashboard');
        
            Route::prefix('shipments')->group(function () {
                Route::get('', 'index');
                Route::post('store', 'store');
                Route::post('registerShipment', 'registerShipment');
                Route::post('import_preview', 'import_preview');
                Route::post('import', 'import');
                Route::get('import_template', 'import_template');
                Route::post('{shipment}/finalize-pre', 'finalizePreId');
                Route::delete('{id}', 'destroy');
                Route::get('exception', 'merchantExceptionShipments');
                Route::get('delivered', 'merchantDeliveredShipments');
            });
        
            Route::prefix('notifications')->controller(MerchantNotificationController::class)->group(function () {
                Route::get('', 'index');
                Route::patch('{id}/read', 'read');
                Route::patch('read-all', 'readAll');
                Route::get('stats', 'stats');
                Route::get('customers', 'getCustomerNotifications');
            });
        
        
            Route::prefix('invoices')->controller(MerchantInvoiceController::class)->group(function () {
                Route::get('{merchant_id}', 'index');
                Route::get('{merchant_id}/{invoice_id}', 'show');
                Route::get('{merchant_id}/{invoice_id}/download', 'download');
            });
        
            Route::prefix('merchant_settings')->controller(MerchantSettingController::class)->group(function () {
                Route::get('{merchant_id}', 'show');
                Route::post('{merchant_id}', 'update');
            });
        
            Route::prefix('tickets')->controller(MerchantTicketController::class)->group(function () {
                Route::get('', 'index');
                Route::get('{id}', 'show');
                Route::get('{id}/messages', 'messages');
                Route::post('store', 'store');
                Route::post('{id}/message', 'sendMessage');
                Route::post('{id}/close', 'close');
            });
        
            Route::prefix('address_book')->controller(MerchantAddressBookController::class)->group(function () {
                Route::get('', 'index');
                Route::get('search', 'search');
                Route::get('all', 'all');
                Route::get('{id}', 'show');
                Route::post('store', 'store');
                Route::post('{id}/update', 'update');
                Route::post('{id}/delete', 'destroy');
            });
        
            Route::prefix('branches')->controller(MerchantBranchController::class)->group(function () {
                Route::get('', 'index');
                Route::get('all', 'all');
                Route::get('{id}', 'show');
                Route::get('edit/{id}', 'edit');
                Route::post('store', 'store');
                Route::post('{id}/update', 'update');
                Route::post('{id}/delete', 'destroy');
            });
        
            Route::prefix('shipments')->controller(MerchantShipmentLiveTrackingController::class)->group(function () {
                Route::get('active-tracking', 'getActiveTrackingShipments');
                Route::get('tracking/{trackingNo}', 'getShipmentTracking');
                Route::get('driver-location', 'getDriverLocation');
                Route::get('tracking-summary', 'getTrackingSummary');
            });
        
            Route::prefix('payments')->controller(MerchantPaymentController::class)->group(function () {
                Route::get('summary', 'summary');
                Route::get('transactions', 'transactions');
            });
        
            Route::prefix('dashboard')->controller(MerchantDashboardController::class)->group(function () {
                Route::get('kpi-summary', 'kpiSummary');
                Route::get('charts', 'chartsData');
                Route::get('shipments', 'shipmentsReport');
            });
            Route::prefix('wallet')->controller(MerchantWalletController::class)->group(function () {
                Route::get('/', 'show');
                // Route::post('/deposit', 'deposit');
                Route::post('/withdraw', 'withdraw');
            });
        
            Route::get('{id}/pricing', [MerchantCommissionController::class, 'pricing']);
            Route::get('{id}/profile', 'profile', [MerchantController::class, 'profile']);
            Route::get('consignees', 'getConsignees');
        
            // Pickup Tasks
            Route::prefix('pickup-tasks')->controller(\App\Http\Controllers\Api\v1\MerchantPickupTaskController::class)->group(function () {
                Route::get('', 'indexForMerchant');
            });
        });
        
        Route::post('merchant/login', function (Request $request) {
            if (!$request->filled('login') && $request->filled('email')) {
                $request->merge(['login' => $request->email]);
            }
        
            $request->validate([
                'login' => 'required|string',
                'password' => 'required|string',
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
                $user = User::where('email', $login)->first();
            } elseif ($isPhone) {
                $digits = ltrim($login, '+');
                $phoneSplit = splitPhoneNumber($login);
                $user = User::where('country_code', $phoneSplit['country_code'] ?? null)
                    ->where('phone', $phoneSplit['national_number'] ?? null)
                    ->first();
        
                if (!$user) {
                    $user = User::whereRaw("CONCAT(country_code, phone) = ?", [$digits])
                        ->orWhereRaw("CONCAT('+', country_code, phone) = ?", ['+' . $digits])
                        ->orWhere('phone', $digits)
                        ->first();
                }
            } else {
                $user = User::where('username', $login)->first();
            }
        
            if (!$user) {
                LoginHistory::create($historyData + ['status' => false]);
                return sendResponse('No account found for this identifier.', [], [], 402);
            }
        
            if (!Hash::check($request->password, $user->password)) {
                LoginHistory::create($historyData + ['status' => false, 'user_id' => $user->id]);
                return sendResponse('Password is incorrect.', [], [], 402);
            }
        
            Auth::login($user);
        
            $isMerchant = $user->hasRole(['Merchant']);
        
            if ($isMerchant && $user->status !== 'active') {
                $user->tokens()->delete();
                Auth::guard('web')->logout();
                LoginHistory::create($historyData + ['status' => false, 'user_id' => $user->id]);
                return sendResponse('Your account is inactive. Please contact support.', [], false, 403);
            }
        
            if ($user->status !== 'active') {
                $user->tokens()->delete();
                Auth::guard('web')->logout();
                LoginHistory::create($historyData + ['status' => false, 'user_id' => $user->id]);
                return sendResponse('Your account is inactive. Please contact support.', [], false, 403);
            }
            $user["isMerchant"] = $isMerchant;
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
                    $fcm->subscribeTokenToMerchantTopic($user->id, $request->fcm_token);
                } catch (\Throwable $e) {
                    Log::warning('FCM subscribe failed: ' . $e->getMessage());
                }
            }
        
            LoginHistory::create($historyData + ['status' => true, 'user_id' => $user->id]);
        
            return sendResponse('Login successful', new AuthResource($user), []);
        });

    });
