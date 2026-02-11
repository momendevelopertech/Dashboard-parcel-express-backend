<?php

use App\Http\Controllers\Api\v1\AdminNotificationController;
use App\Http\Controllers\Api\v1\ChatController;
use App\Http\Controllers\Api\v1\SettingController;
use App\Http\Controllers\Api\v1\WhatsappWebhookController;
use App\Http\Resources\GeneralResource;
use App\Models\City;
use App\Models\Consignee;
use App\Models\Country;
use App\Models\Governorate;
use App\Models\Notification;
use App\Models\Shipment;
use App\Models\Place;
use App\Models\State;
use App\Models\TransferShipment;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Hash;

Route::post('ultramsg/webhook', [WhatsappWebhookController::class, 'handle']);

Route::prefix('v1')
    ->group(function () {

        Route::prefix('notifications')->controller(AdminNotificationController::class)->group(function () {
            Route::get('', 'index');
            Route::patch('{id}/read', 'read');
            Route::patch('read-all', 'readAll');
            Route::get('stats', 'stats');
        });
        
        Route::get('countries', function (Request $request) {
            $countries = Country::query();
            if ($request->timestamp) {
                $countries = $countries->where('updated_at', '<', $request->timestamp);
                if ($countries->count() < 0) {
                    return sendResponse("Countries", []);
                }
            }
            return sendResponse("Countries", $countries->select("id", "name", "updated_at")->get());
        });
        
        Route::get('governorates', function () {
            return sendResponse("Governorates", Governorate::select("id", "en_name", "ar_name", "isActive")->get());
        });
        
        Route::get('states', function () {
            return sendResponse("States", State::select("id", "en_name", "ar_name", "isActive")->get());
        });
        
        Route::get('places', function () {
            return sendResponse("Places", Place::select("id", "en_name", "ar_name", "isActive")->get());
        });
        
        Route::get('cities', function () {
            return sendResponse("Cities", City::select("id", "name")->get());
        });
        
        
        Route::get('facility_types', function () {
            return sendResponse("FacilityTypes", facility_types());
        });
        
        Route::get('facilities', function () {
            return sendResponse("Facilities", facilities());
        });
        
        
        Route::get('accountables', function () {
            return sendResponse("Accountables", accountables());
        });
        
        Route::get('setting_select_options', function () {
            return sendResponse("Setting Select Options", setting_select_options());
        });
        
        Route::get('transfer_areas', function () {
            $shipments = TransferShipment::byOwner()->select('owner_id', 'owner_type', DB::raw('COUNT(*) as shipments_count'))
                ->whereDoesntHave('transfer_task_shipment')
                ->groupBy('owner_id', 'owner_type')
                ->get();
            $shipments->load('owner');
        
            $data = $shipments->map(function ($shipment) {
                return [
                    'owner_id' => $shipment->owner_id,
                    'owner_type' => $shipment->owner_type,
                    'owner' => $shipment->owner ? $shipment->owner->name : null,
                    'shipments_count' => $shipment->shipments_count,
                ];
            });
        
            return sendResponse("Transfer Areas with shipments data", $data);
        });
        
        Route::get('system_delivery_exceptions', function () {
            return array_values(system_delivery_exceptions());
        });
        
        Route::get('setting/{key}', function ($key) {
            return response()->json(['value' => setting($key)]);
        });
        
        Route::get('settings/get-default-settings', [SettingController::class, 'getDefaultSettings']);
        
        Route::post('shipment_history', function () {
        
            $shipment = Shipment::where('tracking_no', request()->tracking_no)->first();
        
            shipmentHistory([
                "shipment_id" => $shipment->id,
                "status" => "PRINT",
                "description" => "Airway bill has been printed",
                "type" => "PRINT"
            ]);
        
            return sendResponse("Shipment history created", [], true, [], 200);
        })->middleware('auth:sanctum');
        
        
        Route::post('update_shipment_location', function (Request $request) {
            $request->validate([
                "tracking_no" => "required|exists:shipments,tracking_no",
                "latitude" => "required",
                "longitude" => "required",
                "country_id" => "required|exists:countries,id",
                "state_id" => "required|exists:states,id",
                "governorate_id" => "required|exists:governorates,id",
                "street_address" => "required|string",
                "location" => "nullable|string"
            ]);
        
            DB::beginTransaction();
            try {
                $shipment = Shipment::where('tracking_no', $request->tracking_no)->first();
                $consignee = Consignee::find($shipment->consignee_id);
        
                $updateData = [
                    "latitude" => $request->latitude,
                    "longitude" => $request->longitude,
                    "country_id" => $request->country_id,
                    "state_id" => $request->state_id,
                    "governorate_id" => $request->governorate_id,
                    "streetAddress" => $request->street_address,
                    "location" => $request->location
                ];
        
                // Only include place_id if it's provided
                if ($request->has('place_id')) {
                    $updateData['place_id'] = $request->place_id;
                }
        
                $consignee->update($updateData);
        
                DB::commit();
                return sendResponse("Location updated successfully.", []);
            } catch (QueryException $e) {
                DB::rollBack();
                return sendResponse("Error Occurred.", [], false, [$e->getMessage()], 500);
            }
        });
        
        // Verify address change OTP for a specific device and update token
        Route::post('verify_address_otp', function (Request $request) {
            $request->validate([
                'tracking_no' => 'required|exists:shipments,tracking_no',
                'token' => 'required|string',
                'otp' => 'required|integer',
            ]);
            $shipment = Shipment::with(['consignee', 'shipment_delivery'])->where('tracking_no', $request->tracking_no)->firstOrFail();
            $consignee = $shipment->consignee;
            if (!$consignee) {
                return sendResponse('Consignee not found', [], false, [], 404);
            }
            if (!Hash::check($request->token, $consignee->update_token)) {
                return sendResponse('Invalid update token', [], false, [], 422);
            }
            if ($consignee->token_expires_at && $consignee->token_expires_at->isPast()) {
                return sendResponse('Update token expired', [], false, [], 422);
            }
            $delivery = $shipment->shipment_delivery;
            if (!$delivery) {
                return sendResponse('Delivery not found', [], false, [], 404);
            }
            if ($delivery->delivery_otp !== (int)$request->otp) {
                return sendResponse('Invalid OTP', [], false, [], 422);
            }
            if ($delivery->otp_generated_at && $delivery->otp_generated_at->diffInMinutes(now()) > 15) {
                return sendResponse('OTP expired', [], false, [], 422);
            }
            $identifier = $request->ip();
            $tokens = $delivery->otp_verified_address_tokens ?: [];
            if (!in_array($identifier, $tokens)) {
                $tokens[] = $identifier;
                $delivery->otp_verified_address_tokens = $tokens;
                if (!$delivery->otp_verified) {
                    $delivery->otp_verified = true;
                }
                $delivery->save();
            }
            return sendResponse('Address OTP verified for this device', []);
        });
        
        // Check if the current device has already verified the address OTP for a given update token
        Route::get('address_otp_verified', function (Request $request) {
            $request->validate([
                'tracking_no' => 'required|exists:shipments,tracking_no',
                'token' => 'required|string',
            ]);
            $shipment = Shipment::with(['consignee', 'shipment_delivery'])->where('tracking_no', $request->tracking_no)->firstOrFail();
            $consignee = $shipment->consignee;
            if (!$consignee) {
                return sendResponse('Consignee not found', [], false, [], 404);
            }
            if (!Hash::check($request->token, $consignee->update_token)) {
                return sendResponse('Invalid update token', [], false, [], 422);
            }
            if ($consignee->token_expires_at && $consignee->token_expires_at->isPast()) {
                return sendResponse('Update token expired', [], false, [], 422);
            }
            $delivery = $shipment->shipment_delivery;
            if (!$delivery) {
                return sendResponse('Delivery not found', [], false, [], 404);
            }
            $identifier = $request->ip();
            $tokens = $delivery->otp_verified_address_tokens ?: [];
            $verified = in_array($identifier, $tokens);
            return sendResponse('Address OTP verification status', ['verified' => $verified]);
        });
        Route::post('/ultramsg/webhook', [WhatsappWebhookController::class, 'handle']);
        
        Route::prefix('chat-sessions')->controller(ChatController::class)->group(function () {
            Route::post('start', 'start'); // Public endpoint for sessionId
            Route::post('{chatSession}/mark-as-read', 'markAsRead'); // Public endpoint for sessionId
            Route::post('send-otp', 'sendOtp'); // Public endpoint for sessionId
            Route::post('verify-otp', 'verifyOtp'); // Public endpoint for sessionId
            Route::post('resend-otp', 'resendOtp'); // Public endpoint for sessionId
            Route::get('/phone/{phone}', [ChatController::class, 'getSessionsByPhone']);
            Route::post('resume-from-ticket', 'resumeFromTicket'); // Public endpoint for customers
            Route::post('get-by-ticket', 'getByTicket'); // Public endpoint for customers
            Route::get('{sessionId}/messages', 'getMessages'); // Updated to use pagination
            Route::post('{sessionId}/message', 'sendMessage'); // Public endpoint for customers and agents
            Route::get('{sessionId}/history', 'getChatHistory'); // Public endpoint for customers and agents
        });
        // Chat messages
        Route::prefix('merchant-chat')->middleware('auth:sanctum')->controller(\App\Http\Controllers\Api\v1\MerchantChatController::class)->group(function () {
            Route::post('/start-chat', 'startChat');
            Route::get('/{merchantId}/messages', 'getMessages');
            Route::get('/messages/{merchantId}', 'getMessagesForMerchant');
            Route::post('/message/{merchantId}', 'sendMessageFromMerchant');
            Route::post('/{merchantId}/message', 'sendMessage');
            Route::post('/{merchantId}/request/pickup', 'createPickupRequest');
            Route::post('/{merchantId}/request/settlement', 'createSettlementRequest');
            Route::post('/{merchantId}/request/waybills', 'createWaybillRequest');
            Route::post('/{sessionId}/close', 'closeSession');
            Route::post('/{sessionId}/mark-read', 'markAsRead');
        });
        Route::get('/merchants/with-chat-info', [\App\Http\Controllers\Api\v1\MerchantController::class, 'getMerchantsWithChatInfo']);

    });

