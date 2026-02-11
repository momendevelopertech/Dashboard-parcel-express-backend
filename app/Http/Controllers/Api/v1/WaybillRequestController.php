<?php

namespace App\Http\Controllers\Api\v1;
use App\Http\Controllers\Controller;


use App\Events\MerchantChatMessageSent;
use App\Models\MerchantChatMessage;
use App\Models\MerchantChatSession;
use App\Models\MerchantTicket;
use App\Models\PickupRequest;
use App\Models\WaybillRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Events\MerchantTicketChanged;
use App\Events\MerchantTicketMessageSent;
use App\Models\MerchantTicketMessage;
use App\Models\User;
use Illuminate\Support\Str;

class WaybillRequestController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'waybills_count' => 'required|integer|min:1',
            'scheduled_at' => 'required',
        ]);

        $userId = $request->input('sender_id') ?? Auth::id();
        $user = User::findOrFail($userId);

        if (!$user->merchant) {
            return response()->json([
                'message' => 'Merchant not found for this user.',
                'success' => false
            ], 404);
        }

        $merchantId = $user->merchant->id;
        $senderName = $request->input('sender_name') ?? $user->name;

        $waybillRequest = WaybillRequest::create([
            'merchant_user_id' => $merchantId,
            'shipments_count' => $data['waybills_count'],
            'scheduled_at' => $data['scheduled_at'],
            'status' => 'pending',
        ]);

        // Get the existing active chat session
        $session = MerchantChatSession::where('merchant_id', $merchantId)
            ->where('status', 'ACTIVE')
            ->orderBy('created_at', 'desc')
            ->first();

        // If no active session exists, create one
        if (!$session) {
            $session = MerchantChatSession::create([
                'merchant_id' => $merchantId,
                'session_id' => 'CHAT' . now()->format('Ymd') . strtoupper(Str::random(8)),
                'subject' => 'General Chat',
                'priority' => 'MEDIUM',
                'status' => 'ACTIVE'
            ]);
        }

        // Create chat message with waybill request details
        $messageContent = json_encode([
            'type' => 'waybill_request',
            'data' => $waybillRequest->toArray(),
            'timestamp' => now()->toISOString()
        ]);

        $message = MerchantChatMessage::create([
            'merchant_chat_session_id' => $session->id,
            'sender_type' => 'MERCHANT',
            'sender_id' => $merchantId,
            'sender_name' => $senderName,
            'message' => $messageContent,
            'message_type' => 'CARD'
        ]);

        $recipients = User::role(['customer service', 'Super Admin'])->get();
        foreach ($recipients as $recipient) {
            create_notification(
                $recipient,
                '📌 New Waybill Request',
                sprintf('A new waybill request has been received from %s for %d waybills', $user->name, $waybillRequest->shipments_count),
                [
                    'type' => 'waybill_request_created',
                    'waybill_request_id' => $waybillRequest->id,
                    'merchant_name' => $user->name,
                    'scheduled_date' => $waybillRequest->scheduled_at,
                    'waybills_count' => $waybillRequest->shipments_count,
                    'status' => $waybillRequest->status
                ],
                'waybill_request',
                false
            );
        }
        // Broadcast message to chat
        broadcast(new MerchantChatMessageSent($message));

        // Update session timestamp
        $session->touch();

        return response()->json([
            'message' => 'Waybill request created successfully.',
            'data' => $waybillRequest
        ]);
    }
}
