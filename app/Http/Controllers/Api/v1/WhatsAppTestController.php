<?php

namespace App\Http\Controllers\Api\v1;
use App\Http\Controllers\Controller;


use App\Services\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WhatsAppTestController extends Controller
{
    protected WhatsAppService $whatsAppService;

    public function __construct(WhatsAppService $whatsAppService)
    {
        $this->whatsAppService = $whatsAppService;
    }

    /**
     * Test WhatsApp Cloud API configuration
     */
    public function testConfiguration()
    {
        $configStatus = $this->whatsAppService->getConfigurationStatus();
        
        return response()->json([
            'success' => true,
            'message' => 'WhatsApp Cloud API Configuration Status',
            'data' => $configStatus,
        ]);
    }

    /**
     * Send a test message via WhatsApp
     */
    public function sendTestMessage(Request $request)
    {
        $request->validate([
            'phone' => 'required|string',
            'message' => 'nullable|string',
            'image_url' => 'nullable|url',
        ]);

        $phone = $request->input('phone');
        $message = $request->input('message', 'Hello! This is a test message from Parcel Express WhatsApp Cloud API integration.');
        $imageUrl = $request->input('image_url');

        try {
            if ($imageUrl) {
                $result = $this->whatsAppService->sendImage($phone, $imageUrl, $message);
            } else {
                $result = $this->whatsAppService->sendMessage($phone, $message);
            }

            if ($result) {
                return response()->json([
                    'success' => true,
                    'message' => 'Test message sent successfully',
                    'data' => [
                        'phone' => $phone,
                        'message' => $message,
                        'has_image' => !empty($imageUrl),
                    ],
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to send test message',
                    'data' => [
                        'phone' => $phone,
                        'message' => $message,
                        'has_image' => !empty($imageUrl),
                    ],
                ], 500);
            }
        } catch (\Exception $e) {
            Log::error('WhatsApp test message failed', [
                'error' => $e->getMessage(),
                'phone' => $phone,
                'message' => $message,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error sending test message: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Test notification system with ShipmentCreatedNotification
     */
    public function testNotification(Request $request)
    {
        $request->validate([
            'phone' => 'required|string',
            'shipment_id' => 'required|exists:shipments,id',
        ]);

        try {
            $shipment = \App\Models\Shipment::findOrFail($request->input('shipment_id'));
            $notification = new \App\Notifications\ShipmentCreatedNotification($shipment);
            
            // Create a mock notifiable object
            $notifiable = new class {
                public $cellphone;
                public $alternatePhone;
                
                public function __construct($phone)
                {
                    $this->cellphone = $phone;
                    $this->alternatePhone = null;
                }
            };
            
            $notifiable = new $notifiable($request->input('phone'));

            // Send the notification
            $notifiable->notify($notification);

            return response()->json([
                'success' => true,
                'message' => 'Test notification sent successfully',
                'data' => [
                    'shipment_id' => $shipment->id,
                    'tracking_number' => $shipment->tracking_no,
                    'phone' => $request->input('phone'),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('WhatsApp test notification failed', [
                'error' => $e->getMessage(),
                'shipment_id' => $request->input('shipment_id'),
                'phone' => $request->input('phone'),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error sending test notification: ' . $e->getMessage(),
            ], 500);
        }
    }
} 