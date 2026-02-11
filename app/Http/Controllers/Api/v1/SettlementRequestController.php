<?php

namespace App\Http\Controllers\Api\v1;

// SettlementRequestController.php
use App\Http\Controllers\Controller;


use App\Models\SettlementRequest;
use App\Models\MerchantTicket;
use App\Models\MerchantTicketMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Events\MerchantTicketChanged;
use App\Events\MerchantTicketMessageSent;
use App\Models\User;

class SettlementRequestController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'merchant_id' => 'required|integer|exists:users,id',
            'amount' => 'required|numeric|min:0'
        ]);

        $settlementRequest = SettlementRequest::create([
            'merchant_user_id' => $data['merchant_id'],
            'amount' => $data['amount'],
            'status' => 'pending',
        ]);

        // Create or find ticket
        $ticket = MerchantTicket::firstOrCreate([
            'merchant_id' => $data['merchant_id'],
            'subject' => 'Settlement Request',
            'status' => 'ACTIVE',
        ], [
            'initial_message' => "Settlement request for " . getCurrency('en') . " {$data['amount']}"
        ]);

        // Create message with request details
        $message = MerchantTicketMessage::create([
            'merchant_ticket_id' => $ticket->id,
            'sender_id' => Auth::id(),
            'sender_type' => 'AGENT',
            'message' => json_encode([
                'type' => 'settlement_request',
                'data' => $settlementRequest->toArray(),
            ]),
            'message_type' => 'card',
        ]);

        // Broadcast events
        event(new MerchantTicketChanged($ticket, 'updated'));
        event(new MerchantTicketMessageSent($message));

        // Notify admin
        $admin = User::where('email', 'admin@gmail.com')->first();
        if ($admin) {
            $merchant = User::find($data['merchant_id']);
            $notificationContent = "Settlement request of " . getCurrency("en") . " {$data['amount']} for merchant {$merchant->name}";

            create_notification(
                $admin,
                "💰 Settlement Request",
                $notificationContent,
                [
                    'merchant_ticket_id' => $ticket->id,
                    'merchant_name' => $merchant->name,
                    'amount' => $data['amount'],
                    'action_url' => "/admin/tickets/{$ticket->id}",
                ],
                'settlement_request',
                false
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Settlement request created successfully',
            'data' => $settlementRequest
        ]);
    }
}
