<?php

namespace App\Http\Controllers\Api\v1;
use App\Http\Controllers\Controller;


use App\Events\MerchantChatMessageSent;
use App\Events\MerchantChatSessionStatusChanged;
use App\Http\Resources\MerchantChatMessageResource;
use App\Http\Resources\MerchantChatSessionResource;
use App\Models\Merchant;
use App\Models\MerchantChatSession;
use App\Models\MerchantChatMessage;
use App\Models\SettlementRequest;
use App\Models\WaybillRequest;
use App\Models\PickupRequest;
use App\Models\User;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\QueryException;
use openapi\annotations as OA;
use App\Services\AdminCounterService;

/**
 * @OA\Tag(name="Merchant Chat", description="Merchant Chat Management")
 */
class MerchantChatController extends Controller
{


       public $adminCounterService;

    public function __construct(AdminCounterService $adminCounterService)
    {
        $this->adminCounterService = $adminCounterService;
    }
    /**
     * @OA\Get(
     *     path="/api/merchant-chat/{merchantId}/messages",
     *     summary="Get merchant chat messages",
     *     description="Retrieves messages for a specific merchant chat session",
     *     tags={"Merchant Chat"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="merchantId",
     *         in="path",
     *         description="ID of the merchant",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Messages retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Messages retrieved successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="messages", type="array", @OA\Items(ref="#/components/schemas/MerchantChatMessage")),
     *                 @OA\Property(property="session", ref="#/components/schemas/MerchantChatSession")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Merchant or session not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Merchant not found"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     )
     * )
     */
    public function getMessages($merchantId)
    {
        try {
            $user = User::find($merchantId);
            $merchant=$user->merchant;
            if (!$merchant) {
                return sendResponse("Merchant not found.", [], 404);
            }

            // Get or create chat session
            $session = MerchantChatSession::firstOrCreate(
                ['merchant_id' => $user->id, 'status' => 'ACTIVE'],
                [
                    'session_id' => $this->generateSessionId(),
                    'subject' => 'General Chat',
                    'priority' => 'MEDIUM'
                ]
            );

            // Eager load the chatSession relationship with the merchant
            $messages = MerchantChatMessage::where('merchant_chat_session_id', $session->id)
                ->with(['sender' => function ($query) {
                    $query->select('id', 'name');
                }])
                ->orderBy('created_at', 'asc')
                ->get();

            return sendResponse("Messages retrieved successfully.", [
                'messages' => MerchantChatMessageResource::collection($messages),
                'session' => new MerchantChatSessionResource($session->load('merchant'))
            ]);

        } catch (QueryException $e) {
            return sendResponse("Error Occurred.", [], [$e->getMessage()], 422);
        } catch (\Exception $e) {
            return sendResponse("Error Occurred.", [], [$e->getMessage()], 422);
        }
    }

    public function getMessagesForMerchant($merchantId)
    {
        try {
            $user = User::with('merchant')->find($merchantId);
            $merchant = $user->merchant;
            if (!$merchant) {
                return sendResponse("Merchant not found.", [], 404);
            }

            // Get or create chat session
            $session = MerchantChatSession::firstOrCreate(
                ['merchant_id' => $user->id, 'status' => 'ACTIVE'],
                [
                    'session_id' => $this->generateSessionId(),
                    'subject' => 'General Chat',
                    'priority' => 'MEDIUM'
                ]
            );





            // Eager load the chatSession relationship with the merchant
            $messages = MerchantChatMessage::where('merchant_chat_session_id', $session->id)
                ->with(['sender' => function ($query) {
                    $query->select('id', 'name');
                }])
                ->orderBy('created_at', 'desc')
                ->get();

            return sendResponse("Messages retrieved successfully.", [
                'messages' => MerchantChatMessageResource::collection($messages),
                'session' => new MerchantChatSessionResource($session->load('merchant'))
            ]);

        } catch (QueryException $e) {
            return sendResponse("Error Occurred.", [], [$e->getMessage()], 422);
        } catch (\Exception $e) {
            return sendResponse("Error Occurred.", [], [$e->getMessage()], 422);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/merchant-chat/{merchantId}/message",
     *     summary="Send message to merchant chat",
     *     description="Sends a message to merchant chat session",
     *     tags={"Merchant Chat"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="merchantId",
     *         in="path",
     *         description="ID of the merchant",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"message"},
     *             @OA\Property(property="message", type="string", description="Message content"),
     *             @OA\Property(property="message_type", type="string", description="Message type (TEXT, FILE, IMAGE, SYSTEM, CARD)"),
     *             @OA\Property(property="attachments", type="array", description="Attachments", @OA\Items(type="object"))
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Message sent successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Message sent successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/MerchantChatMessage")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Error Occurred"),
     *             @OA\Property(property="errors", type="array", @OA\Items(type="string"))
     *         )
     *     )
     * )
     */
    public function sendMessage(Request $request, $merchantId)
    {
        $request->validate([
            'message' => 'required|string',
            'message_type' => 'nullable|string|in:TEXT,FILE,IMAGE,SYSTEM,CARD',
        ]);

        try {
            $user = User::find($merchantId);
            $merchant=$user->merchant;
            if (!$merchant) {
                return sendResponse("Merchant not found.", [], 404);
            }
            $session = MerchantChatSession::firstOrCreate(
                ['merchant_id' => $user->id, 'status' => 'ACTIVE'],
                [
                    'session_id' => $this->generateSessionId(),
                    'subject' => 'General Chat',
                    'priority' => 'MEDIUM'
                ]
            );
            $senderId = $user->id ?? Auth::id();
            $senderName = $request->input('sender_name') ?? (Auth::check() ? Auth::user()->name : 'System');
            $messageData = [
                'merchant_chat_session_id' => $session->id,
                'sender_type' => 'AGENT',
                'sender_id' => $senderId,
                'sender_name' => $senderName,
                'message' => $request->message,
                'message_type' => $request->message_type ?? 'TEXT',
                'attachments' => $request->attachments
            ];
            $message = MerchantChatMessage::create($messageData);
            $message->load('sender:id,name');
            broadcast(new MerchantChatMessageSent($message));
            $session->touch();
            create_notification(
                $merchant->user,
                "💬 New Message From Agent - {$senderName}",
                "💬" . $request->message,
                [
                    'chat_session_id' => $session->id,
                    'customer_name' => $merchant->user->name ?? 'Unknown',
                    'customer_phone' => $merchant->user->phone ?? null,
                    'tracking_number' => $request->tracking_number ?? null,
                    'initial_message' => $request->message,
                    'priority' => strtoupper($request->priority ?? 'MEDIUM'),
                    'timestamp' => now()->toDateTimeString(),
                    'action_url' => '/merchant/chat?chat_id=' . $session->id,
                    'has_attachments' => $request->hasFile('attachments')
                ],
                'admin_merchant_message',
                false
            );
            return sendResponse("Message sent successfully.", new MerchantChatMessageResource($message));

        } catch (QueryException $e) {
            return sendResponse("Error Occurred.", [], [$e->getMessage()], 422);
        } catch (\Exception $e) {
            return sendResponse("Error Occurred.", [], [$e->getMessage()], 422);
        }
    }
    public function sendMessageFromMerchant(Request $request, $merchantId)
    {
        $request->validate([
            'message' => 'required|string',
            'message_type' => 'nullable|string|in:TEXT,FILE,IMAGE,SYSTEM,CARD',
        ]);

        try {
            $user = User::find($merchantId);
            $merchant=$user->merchant;
            if (!$merchant) {
                return sendResponse("Merchant not found.", [], 404);
            }

            // Get or create active session
            $session = MerchantChatSession::firstOrCreate(
                ['merchant_id' => $user->id, 'status' => 'ACTIVE'],
                [
                    'session_id' => $this->generateSessionId(),
                    'subject' => 'General Chat',
                    'priority' => 'MEDIUM'
                ]
            );


            // Use provided sender details or fall back to authenticated user
            $senderId = $user->id ?? Auth::user()->id;
            $senderName = $request->input('sender_name') ?? (Auth::check() ? Auth::user()->name : 'System');

            $messageData = [
                'merchant_chat_session_id' => $session->id,
                'sender_type' => 'MERCHANT',
                'sender_id' => $senderId,
                'sender_name' => $senderName,
                'message' => $request->message,
                'message_type' => $request->message_type ?? 'TEXT',
                'attachments' => $request->attachments
            ];

            $message = MerchantChatMessage::create($messageData);
            $message->load('sender:id,name');

            // Broadcast message
            broadcast(new MerchantChatMessageSent($message));

            // Update session timestamp
            $session->touch();

            // Send notification to workspace users only
            $merchantProfile = $merchant;
            $notificationContent = "💬" . $request->message;
            if ($merchantProfile && $merchantProfile->owner_id && $merchantProfile->owner_type) {
                // Get facility info
                $facilityName = null;
                $facilityType = null;
                try {
                    $facilityModel = $merchantProfile->owner_type::find($merchantProfile->owner_id);
                    if ($facilityModel) {
                        $facilityName = $facilityModel->name;
                        $facilityType = class_basename($merchantProfile->owner_type);
                    }
                } catch (\Exception $e) {
                    Log::warning('Could not fetch facility name', ['error' => $e->getMessage()]);
                }

                notify_workspace_users(
                    $merchantProfile->owner_id,
                    $merchantProfile->owner_type,
                    ['Merchant Ticket Chat access'],
                    "💬 New Chat From Merchant - {$senderName}",
                    $notificationContent,
                    [
                        'chat_session_id' => $session->id,
                        'customer_name' => $senderName,
                        'customer_phone' => $request->customer_phone,
                        'tracking_number' => $request->tracking_number,
                        'initial_message' => $request->initial_message,
                        'priority' => strtoupper($request->priority ?? 'medium'),
                        'timestamp' => now()->toDateTimeString(),
                        'action_url' => '/admin/chat/' . $session->id,
                        'has_attachments' => $request->hasFile('attachments'),
                        'facility_name' => $facilityName,
                        'facility_type' => $facilityType,
                        'facility_id' => $merchantProfile->owner_id,
                    ],
                    'new_merchant_message',
                    false
                );
            }

            return sendResponse("Message sent successfully.", new MerchantChatMessageResource($message));

        } catch (QueryException $e) {
            return sendResponse("Error Occurred.", [], [$e->getMessage()], 422);
        } catch (\Exception $e) {
            return sendResponse("Error Occurred.", [], [$e->getMessage()], 422);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/merchant-chat/{merchantId}/request/pickup",
     *     summary="Create pickup request",
     *     description="Creates a pickup request for merchant",
     *     tags={"Merchant Chat"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="merchantId",
     *         in="path",
     *         description="ID of the merchant",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"scheduled_at", "shipments_count"},
     *             @OA\Property(property="scheduled_at", type="string", format="date-time", description="Pickup schedule date"),
     *             @OA\Property(property="shipments_count", type="integer", description="Number of shipments")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Pickup request created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Pickup request created successfully"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     )
     * )
     */
    public function createPickupRequest(Request $request, $merchantId)
    {
        $request->validate([
            'scheduled_at' => 'required|date',
            'shipments_count' => 'required|integer|min:1'
        ]);

        try {
            // Resolve merchant_user_id: could be user ID or merchant model ID
            $merchantUserId = $merchantId;
            $user = User::find($merchantId);

            if (!$user) {
                // If not found as user, try as merchant model ID
                $merchant = Merchant::find($merchantId);
                if (!$merchant || !$merchant->user_id) {
                    return sendResponse("Merchant not found.", [], false, ["Invalid merchant ID"], 404);
                }
                $merchantUserId = $merchant->user_id;
            }

            $pickupRequest = PickupRequest::create([
                'merchant_user_id' => $merchantUserId,
                'scheduled_at' => $request->scheduled_at,
                'shipments_count' => $request->shipments_count,
                'status' => 'pending'
            ]);

            // Create chat message with request details (use merchantUserId for consistency)
            $this->sendRequestMessage($merchantUserId, 'pickup_request', $pickupRequest->toArray());
                    $this->adminCounterService->broadcastToAllAdmins();

            return sendResponse("Pickup request created successfully.", $pickupRequest);

        } catch (\Exception $e) {
            return sendResponse("Error Occurred.", [], [$e->getMessage()], 422);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/merchant-chat/{merchantId}/request/waybills",
     *     summary="Create waybill request",
     *     description="Creates a waybill request for merchant",
     *     tags={"Merchant Chat"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="merchantId",
     *         in="path",
     *         description="ID of the merchant",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"waybills_count"},
     *             @OA\Property(property="waybills_count", type="integer", description="Number of waybills requested")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Waybill request created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Waybill request created successfully"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     )
     * )
     */
    public function createWaybillRequest(Request $request, $merchantId)
    {
        $request->validate([
            'waybills_count' => 'required|integer|min:1'
        ]);

        try {
            $waybillRequest = WaybillRequest::create([
                'merchant_user_id' => $merchantId,
                'waybills_count' => $request->waybills_count,
                'status' => 'pending'
            ]);

            // Create chat message with request details
            $this->sendRequestMessage($merchantId, 'waybill_request', $waybillRequest->toArray());

            return sendResponse("Waybill request created successfully.", $waybillRequest);

        } catch (\Exception $e) {
            return sendResponse("Error Occurred.", [], [$e->getMessage()], 422);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/merchant-chat/{sessionId}/close",
     *     summary="Close chat session",
     *     description="Closes a merchant chat session",
     *     tags={"Merchant Chat"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="sessionId",
     *         in="path",
     *         description="ID of the chat session",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Chat session closed successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Chat session closed successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/MerchantChatSession")
     *         )
     *     )
     * )
     */
    public function closeSession($sessionId)
    {
        try {
            $session = MerchantChatSession::findOrFail($sessionId);

            $session->update([
                'status' => 'CLOSED',
                'ended_at' => now()
            ]);

            // Create system message
            $message = MerchantChatMessage::create([
                'merchant_chat_session_id' => $session->id,
                'sender_type' => 'SYSTEM',
                'sender_name' => 'System',
                'message' => 'Chat session closed by agent',
                'message_type' => 'SYSTEM'
            ]);

            broadcast(new MerchantChatMessageSent($message));
            broadcast(new MerchantChatSessionStatusChanged($session, 'closed'));

            return sendResponse("Chat session closed successfully.", new MerchantChatSessionResource($session));

        } catch (\Exception $e) {
            return sendResponse("Error Occurred.", [], [$e->getMessage()], 422);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/merchant-chat/{sessionId}/mark-read",
     *     summary="Mark messages as read",
     *     description="Marks all messages in session as read",
     *     tags={"Merchant Chat"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="sessionId",
     *         in="path",
     *         description="ID of the chat session",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Messages marked as read",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Messages marked as read"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     )
     * )
     */
    public function markAsRead($sessionId)
    {
        try {
            MerchantChatMessage::where('merchant_chat_session_id', $sessionId)
                ->where('is_read', false)
                ->update([
                    'is_read' => true,
                    'read_at' => now()
                ]);

            $session = MerchantChatSession::find($sessionId);
            if ($session) {
                broadcast(new MerchantChatSessionStatusChanged($session, 'updated'));
            }

            return response()->json(['success' => true, 'message' => 'Messages marked as read']);

        } catch (\Exception $e) {
            return sendResponse("Error Occurred.", [], [$e->getMessage()], 422);
        }
    }

    private function generateSessionId()
    {
        return 'MERCHANTCHAT' . now()->format('Ymd') . strtoupper(Str::random(8));
    }


    private function composeHumanRequestMessage(string $type, array $data): string
    {
        $title = strtoupper(str_replace('_', ' ', $type));

        $lines = [];
        foreach ($data as $key => $value) {
            // skip noisy fields
            if (in_array($key, ['id', 'updated_at', 'deleted_at'], true)) {
                continue;
            }

            if (is_array($value)) {
                $value = implode(', ', $value);
            }

            $label = ucfirst(str_replace('_', ' ', $key));
            $lines[] = "{$label}: {$value}";
        }

        $lines[] = 'Status: Pending';
        $lines[] = 'Requested at: ' . now()->toDateTimeString();

        return $title . "\n" . implode("\n", $lines);
    }


    private function sendRequestMessage($merchantId, $requestType, $requestData)
    {
        try {
            $session = MerchantChatSession::where('merchant_id', $merchantId)
                ->where('status', 'ACTIVE')
                ->first();

            if (!$session) {
                return;
            }

            // ✅ Human-readable message
            $messageText = $this->composeHumanRequestMessage($requestType, $requestData);

            $message = MerchantChatMessage::create([
                'merchant_chat_session_id' => $session->id,
                'sender_type' => 'SYSTEM',
                'sender_name' => 'System',
                'message' => $messageText,   // 👈 plain string
                'message_type' => 'CARD',
            ]);

            broadcast(new MerchantChatMessageSent($message));

        } catch (\Throwable $e) {
            Log::error('Error sending request message: ' . $e->getMessage());
        }
    }

    public function startChat(Request $request)
    {
        try {
            $user = Auth::user();
            if (!$user || !$user->merchant) {
                return sendResponse('Merchant not authenticated.', [], false, [], 401);
            }

            $messageText = $request->input('message', 'Merchant has started a new chat.');

            // Find an existing active session or create a new one
            $session = MerchantChatSession::firstOrCreate(
                ['merchant_id' => $user->id, 'status' => 'ACTIVE'],
                [
                    'session_id' => $this->generateSessionId(),
                    'subject' => 'Request Support',
                    'priority' => 'HIGH',
                    'initial_message' => $messageText,
                    'status' => 'ACTIVE',
                    'started_at' => now(),
                ]
            );

            // Create the initial message if provided
            if ($request->has('message')) {
                $message = MerchantChatMessage::create([
                    'merchant_chat_session_id' => $session->id,
                    'sender_type' => 'MERCHANT',
                    'sender_id' => $user->id,
                    'sender_name' => $user->name,
                    'message' => $messageText,
                    'message_type' => 'TEXT',
                ]);

                $message->load('chatSession');
                broadcast(new MerchantChatMessageSent($message));
            }

            return sendResponse('Chat session started successfully.', new MerchantChatSessionResource($session), true, [], 200);
        } catch (QueryException $e) {
            return sendResponse('Error Occurred.', [], false, [$e->getMessage()], 422);
        } catch (\Exception $e) {
            return sendResponse('Error Occurred.', [], false, [$e->getMessage()], 422);
        }
    }
}
