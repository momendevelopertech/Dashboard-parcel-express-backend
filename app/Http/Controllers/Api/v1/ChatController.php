<?php

namespace App\Http\Controllers\Api\v1;
use App\Http\Controllers\Controller;


use App\Http\Requests\StoreChatSessionRequest;
use App\Http\Resources\ChatSessionResource;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use App\Models\ChatSession;
use App\Models\ChatMessage;
use App\Models\Ticket;
use App\Models\ContactHistory;
use App\Traits\Searchable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use App\Events\ChatMessageSent;
use App\Events\ChatSessionStatusChanged;
use App\Models\Shipment;
use App\Services\OtpService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

/**
 * @OA\Tag(name="Other", description="Chat Controller")
 */
class ChatController extends Controller
{
    use Searchable;

    protected $otpService;

    public function __construct(OtpService $otpService)
    {
        $this->otpService = $otpService;
    }

    /**
     * @OA\Get(
     *     path="/chat-sessions/phone/{phone}",
     *     summary="Get chat sessions by phone number",
     *     description="Retrieves all chat sessions for a specific phone number",
     *     tags={"Other"},
     *     @OA\Parameter(
     *         name="phone",
     *         in="path",
     *         description="Phone number to search for",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Chat sessions retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Chat sessions retrieved successfully"),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/ChatSession"))
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="No chat sessions found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="No chat sessions found"),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error Occurred",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Error Occurred."),
     *             @OA\Property(property="errors", type="array", @OA\Items(type="string"))
     *         )
     *     )
     * )
     */
    public function getSessionsByPhone($phone)
    {
        try {
            $chatSessions = ChatSession::where('customer_phone', 'like', "%" . $phone . "%")
                ->with([
                    'messages' => function ($query) {
                        $query->select('id', 'chat_session_id', 'sender_type', 'sender_name', 'message', 'message_type', 'created_at')
                            ->orderBy('created_at', 'asc')
                            ->limit(10);
                    },
                ])
                ->orderBy('created_at', 'desc')
                ->get();
            Log::info($phone);
            if ($chatSessions->isEmpty()) {
                return sendResponse("No chat sessions found for this phone number.", [], 404);
            }

            return sendResponse("Chat sessions retrieved successfully.", ChatSessionResource::collection($chatSessions));
        } catch (QueryException $e) {
            return sendResponse("Error Occurred.", [], [$e->getMessage()], 422);
        } catch (\Exception $e) {
            return sendResponse("Error Occurred.", [], [$e->getMessage()], 422);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/send-otp",
     *     summary="Send OTP to phone number",
     *     description="Sends OTP code to the provided phone number for verification",
     *     tags={"Authentication"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"phone"},
     *             @OA\Property(property="phone", type="string", example="+1234567890")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="OTP sent successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="OTP sent successfully"),
     *             @OA\Property(property="data", type="object", example={})
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Error Occurred."),
     *             @OA\Property(property="errors", type="array", @OA\Items(type="string"))
     *         )
     *     )
     * )
     */
    public function sendOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|max:20'
        ]);
        if ($validator->fails()) {
            return sendResponse("Error Occurred.", [], $validator->errors()->all(), 422);
        }
        try {
            $phone = $request->phone;
            $user = User::where('phone', $phone)->first();
            if (!$user) {
                $user = User::create([
                    'phone' => $phone,
                    'name' => 'Customer_' . Str::random(8),
                    'email' => 'temp_' . Str::random(8) . '@example.com',
                    'password' => Hash::make(Str::random(16)),
                    'role' => 'customer',
                    'status' => 'inactive'
                ]);
            }

            // إرسال OTP
            $otp = $this->otpService->generateAndSendOtp($user, $phone);

            return sendResponse("OTP sent successfully.", [
                'phone' => $phone,
                'expires_in' => 120 // ثانيتين
            ]);
        } catch (\Exception $e) {
            return sendResponse("Error Occurred.", [], [$e->getMessage()], 422);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/verify-otp",
     *     summary="Verify OTP code",
     *     description="Verifies the OTP code sent to the phone number",
     *     tags={"Authentication"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"phone", "otp"},
     *             @OA\Property(property="phone", type="string", example="+1234567890"),
     *             @OA\Property(property="otp", type="string", example="123456")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="OTP verified successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="OTP verified successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="verification_token", type="string"),
     *                 @OA\Property(property="phone", type="string"),
     *                 @OA\Property(property="expires_at", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error or invalid OTP",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Invalid OTP"),
     *             @OA\Property(property="errors", type="array", @OA\Items(type="string"))
     *         )
     *     )
     * )
     */
    public function verifyOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|max:20',
            'otp' => 'required|string|digits:6'
        ]);

        if ($validator->fails()) {
            return sendResponse("Error Occurred.", [], $validator->errors()->all(), 422);
        }

        try {
            $user = User::where('phone', $request->phone)->first();

            if (!$user) {
                return sendResponse("Invalid phone number.", [], ["Phone number not found"], 422);
            }
            $isVerified = $this->otpService->verifyOtp($user, $request->otp);

            if (!$isVerified) {
                return sendResponse("Invalid OTP.", [], ["OTP is invalid or expired"], 422);
            }
            $expiresAt = now()->addDays(3);
            return sendResponse("OTP verified successfully.", [
                'phone' => $user->phone,
                'expires_at' => $expiresAt->toISOString()
            ]);
        } catch (\Exception $e) {
            return sendResponse("Error Occurred.", [], [$e->getMessage()], 422);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/resend-otp",
     *     summary="Resend OTP code",
     *     description="Resends OTP code to the phone number",
     *     tags={"Authentication"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"phone"},
     *             @OA\Property(property="phone", type="string", example="+1234567890")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="OTP resent successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="OTP resent successfully"),
     *             @OA\Property(property="data", type="object", example={})
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Error Occurred."),
     *             @OA\Property(property="errors", type="array", @OA\Items(type="string"))
     *         )
     *     )
     * )
     */
    public function resendOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|max:20'
        ]);

        if ($validator->fails()) {
            return sendResponse("Error Occurred.", [], $validator->errors()->all(), 422);
        }

        try {
            $user = User::where('phone', $request->phone)->first();

            if (!$user) {
                return sendResponse("Phone number not found.", [], ["Phone number not registered"], 422);
            }

            // إعادة إرسال OTP
            $otp = $this->otpService->generateAndSendOtp($user, $request->phone);

            return sendResponse("OTP resent successfully.", [
                'phone' => $request->phone,
                'expires_in' => 120
            ]);
        } catch (\Exception $e) {
            return sendResponse("Error Occurred.", [], [$e->getMessage()], 422);
        }
    }

    protected function modelQuery()
    {
        return ChatSession::query()->select('id', 'session_id', 'customer_name', 'customer_email', 'status', 'priority', 'assigned_agent_id', 'created_at');
    }

    /**
     * @OA\Get(
     *     path="/chat-sessions",
     *     summary="Get all chat sessions",
     *     description="Retrieves a list of chat sessions.  Filtering by status is possible.",
     *     tags={"Other"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filter chat sessions by status",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Chat sessions retrieved successfully",
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error Occurred.",
     *         @OA\JsonContent()
     *     )
     * )
     */
    public function index()
    {
        $chatSessions = ChatSession::query();
        if (request()->has('status')) {
            $chatSessions->where('status', request()->status);
        }

        // if ($user->hasRole('Customer Service')) {
        //     $chatSessions->where(function ($query) use ($user) {
        //         $query->whereDoesntHave('ticket') // Chats without tickets
        //             ->orWhere(function ($q) use ($user) {
        //                 $q->whereHas('ticket', function ($tq) use ($user) {
        //                     $tq->where('assigned_agent_id', $user->id);
        //                 });
        //             });
        //     });
        // }

        $chatSessions = $chatSessions->with(
            'assignedAgent:id,name',
            'ticket:id,ticket_number,assigned_agent_id',
            'messages:id,chat_session_id,message,sender_type,sender_name,message_type,attachments,created_at'
        )
            ->withCount('unreadMessages')
            ->orderBy('created_at', 'desc')
            ->get();

        return sendResponse("Chat sessions retrieved successfully.", ChatSessionResource::collection($chatSessions), []);
    }

    public function markAsRead(ChatSession $chatSession)
    {
        $chatSession->messages()
            ->where('is_read', false)
            ->update(['is_read' => true]);
        broadcast(new ChatSessionStatusChanged($chatSession, 'updated'));
        return response()->json(['success' => true]);
    }

    /**
     * @OA\Post(
     *     path="/chat-sessions",
     *     summary="Start a new chat session",
     *     description="Starts a new chat session.",
     *     tags={"Other"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(property="customer_name", type="string", description="Customer name"),
     *                 @OA\Property(property="customer_email", type="string", format="email", description="Customer email"),
     *                 @OA\Property(property="customer_phone", type="string", description="Customer phone number"),
     *                 @OA\Property(property="initial_message", type="string", description="Initial message"),
     *                 @OA\Property(property="tracking_number", type="string", description="Tracking number"),
     *                 @OA\Property(property="priority", type="string", description="Priority of the chat session (LOW, MEDIUM, HIGH, URGENT)"),
     *                 @OA\Property(property="attachments", type="array", description="File attachments", @OA\Items(type="string", format="binary"))
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Chat session started successfully.",
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error Occurred.",
     *         @OA\JsonContent()
     *     )
     * )
     */
       public function start(Request $request)
    {
        $validated = $request->validate([
            'customer_name' => 'required|string|max:255',
            'customer_phone' => 'required|string|max:20',
            'initial_message' => 'required|string',
            'tracking_number' => 'nullable|string|max:255',
            'priority' => 'nullable|string|in:LOW,MEDIUM,HIGH,URGENT',
            'attachments.*' => 'file|max:10240|mimes:jpg,jpeg,png,gif,pdf,doc,docx,txt' // 10MB max per file
        ], [
            'customer_name.required' => 'The customer name field is required',
            'customer_name.string' => 'The customer name must be a string',
            'customer_name.max' => 'The customer name may not be greater than 255 characters',
            'customer_phone.required' => 'The phone number field is required',
            'customer_phone.string' => 'The phone number must be a string',
            'customer_phone.max' => 'The phone number may not be greater than 20 characters',
            'initial_message.required' => 'The message field is required',
            'initial_message.string' => 'The message must be a string',
            'priority.in' => 'The selected priority is invalid. Valid values are: LOW, MEDIUM, HIGH, URGENT',
            'attachments.*.file' => 'The uploaded file must be a valid file',
            'attachments.*.max' => 'The file may not be greater than 10MB in size',
            'attachments.*.mimes' => 'The file must be a valid image or PDF file'
        ]);

        try {
            if ($request->has('tracking_number') && !empty($request->tracking_number)) {
                $shipment = Shipment::where('tracking_no', $request->tracking_number)->first();

                if (!$shipment) {
                    $trackingData = [
                        'waybillNo' => $request->tracking_number,
                        'lan' => 'en'
                    ];
                    $trackingResponse = $this->trackgfs(json_encode($trackingData));
                    if (
                        isset($trackingResponse['code']) && $trackingResponse['code'] == 50000200 &&
                        ($trackingResponse['msg'] == 'package not found' || $trackingResponse['dmsg'] == 'package not found')
                    ) {
                        return sendResponse("Invalid tracking number", [], ['The provided tracking number is invalid.'], 422);
                    }
                }
            }
            $sessionData = [
                'session_id' => $this->generateSessionId(),
                'customer_name' => $request->customer_name,
                'customer_email' => $request->customer_email,
                'customer_phone' => $request->customer_phone,
                'initial_message' => $request->initial_message,
                'tracking_number' => $request->tracking_number,
                'priority' => $request->priority ?? 'MEDIUM',
                'status' => 'ACTIVE',
                'started_at' => now()
            ];

            $chatSession = ChatSession::create($sessionData);

            // Prepare initial message data
            $messageData = [
                'chat_session_id' => $chatSession->id,
                'sender_type' => 'CUSTOMER',
                'sender_name' => $request->customer_name,
                'message' => $request->initial_message,
                'message_type' => 'TEXT'
            ];

            // ==================== START OF ATTACHMENTS HANDLING ====================
            // Handle file attachments for initial message
            if ($request->hasFile('attachments')) {
                $attachments = [];
                $hasImages = false;

                foreach ($request->file('attachments') as $file) {
                    // التحقق من أن الملف تم رفعه بدون أخطاء
                    if (!$file->isValid()) {
                        // تسجيل خطأ في الرفع والمتابعة للملف التالي
                        Log::error('File upload failed: ' . $file->getMerchantOriginalName());
                        continue;
                    }

                    // Get file info before moving the file
                    $originalName = $file->getMerchantOriginalName();
                    $fileSize = $file->getSize();
                    $mimeType = $file->getMimeType();

                    // Now move the file using your uploadFile function
                    $filePath = uploadFile($file, 'public/live_chat_uploads');

                    // التحقق من أن الدالة أعادت مساراً صالحاً
                    if (!$filePath) {
                        Log::error('uploadFile returned null for: ' . $originalName);
                        continue;
                    }

                    // التحقق من أن الملف موجود فعلياً في نظام الملفات
                    if (empty($filePath)) {
                        Log::error('File path is empty for: ' . $originalName);
                        continue;
                    }

                    // إضافة معلومات الملف إلى المرفقات
                    $attachments[] = [
                        'path' => $filePath,           // المسار النسبي للملف في التخزين
                        'original_name' => $originalName, // الاسم الأصلي للملف
                        'size' => $fileSize,           // حجم الملف بالبايت
                        'mime_type' => $mimeType       // نوع الملف (مثل image/png)
                    ];

                    // تسجيل نجاح الرفع
                    Log::info('File uploaded successfully: ' . $originalName . ' -> ' . $filePath);

                    // Check if any attachment is an image
                    if (str_starts_with($mimeType, 'image/')) {
                        $hasImages = true;
                    }
                }

                // إضافة المرفقات إلى بيانات الرسالة فقط إذا كانت هناك مرفقات صالحة
                if (!empty($attachments)) {
                    $messageData['attachments'] = $attachments;

                    // Set appropriate message type based on attachments
                    if ($hasImages && count($attachments) === 1) {
                        $messageData['message_type'] = 'IMAGE';
                    } else if (!empty($attachments)) {
                        $messageData['message_type'] = 'FILE';
                    }
                }
            }
            // ==================== END OF ATTACHMENTS HANDLING ====================

            $initialMessage = ChatMessage::create($messageData);
            $this->createContactHistory($chatSession, 'CHAT', 'Chat session started');

            // Load the session with the initial message for the response
            $chatSession->load(['messages.sender:id,name']);

            broadcast(new ChatSessionStatusChanged($chatSession, 'created'));
            $notificationContent = "💬 New Chat Session Started\n" .
                "👤 Customer: {$request->customer_name}\n" .
                "📞 Phone: {$request->customer_phone}\n" .
                ($request->tracking_number ? "📦 Tracking: {$request->tracking_number}\n" : "") .
                "📝 Initial Message: " . Str::limit($request->initial_message, 50) . "...\n" .
                "🕒 " . now()->format('M d, Y h:i A');
            $recipients = User::role(['customer service', 'Super Admin'])->get();
            foreach ($recipients as $recipient) {
                create_notification(
                    $recipient,
                    "💬 New Chat Session - {$request->customer_name}",
                    $notificationContent,
                    [
                        'chat_session_id' => $chatSession->id,
                        'customer_name' => $request->customer_name,
                        'customer_phone' => $request->customer_phone,
                        'tracking_number' => $request->tracking_number,
                        'initial_message' => $request->initial_message,
                        'priority' => strtoupper($request->priority ?? 'medium'),
                        'timestamp' => now()->toDateTimeString(),
                        'action_url' => '/admin/chat/' . $chatSession->id,
                        'has_attachments' => $request->hasFile('attachments')
                    ],
                    'new_chat_session',
                    false
                );
            }

            return sendResponse("Chat session started successfully.", new ChatSessionResource($chatSession));
        } catch (QueryException $e) {
            return sendResponse("Error Occurred.", [], [$e->getMessage()], 422);
        }
    }

    private function trackgfs($data)
    {
        $data = json_decode($data);
        $api_url = env('GFS_ENV', 'production') === 'production'
            ? 'https://gli-gw.gfsxpress.com/gli'
            : 'https://test-gli-gw.gfsxpress.com/gli';
        $endpoint = $api_url . '/dwp.serpens.logistics_track_get/2';
        $ct = (string)round(microtime(true) * 1000);
        $headers = [
            'Content-Type' => 'application/x-www-form-urlencoded; charset=UTF-8',
            'gw-ver' => '1',
            'gw-user-group' => env('GFS_USER_GROUP', "OW20250722573830"),
            'ct' => $ct,
            'sign' => ''
        ];
        $requestBody = [
            'waybillNo' => $data->waybillNo,
            'lan' => $data->lan
        ];
        $jsonData = json_encode($requestBody);
        $postData = ['data' => $jsonData];
        $signString = '1'
            . 'dwp.serpens.logistics_track_get'
            . '2'
            . env('GFS_USER_GROUP', "OW20250722573830")
            . $ct
            . $jsonData
            . env('GFS_CIPHER', 'fe4a8bfa1224123f8a04b5b239ef4fa6');
        $headers['sign'] = md5($signString);
        $response = Http::withHeaders($headers)
            ->asForm()
            ->post($endpoint, $postData);
        $responseData = $response->json();
        return $responseData;
    }

    /**
     * @OA\Post(
     *     path="/chat-sessions/{sessionId}/message",
     *     summary="Send a message to a chat session",
     *     description="Sends a message to an active chat session.",
     *     tags={"Other"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="sessionId",
     *         in="path",
     *         description="ID of the chat session",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", description="Message content"),
     *             @OA\Property(property="sender_type", type="string", description="Sender type (CUSTOMER, AGENT, SYSTEM)"),
     *             @OA\Property(property="sender_name", type="string", description="Sender name"),
     *             @OA\Property(property="message_type", type="string", description="Message type (TEXT, FILE, IMAGE, SYSTEM)"),
     *             @OA\Property(property="attachments", type="array", description="File attachments", @OA\Items(type="string", format="binary"))
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Message sent successfully.",
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Chat session is not active.",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error Occurred.",
     *         @OA\JsonContent()
     *     )
     * )
     */
    public function sendMessage(Request $request, $sessionId)
    {
        $request->validate([
            'message' => 'required|string',
            'sender_type' => 'required|string|in:CUSTOMER,AGENT,SYSTEM',
            'sender_name' => 'nullable|string|max:255',
            'message_type' => 'nullable|string|in:TEXT,FILE,IMAGE,SYSTEM',
            'attachments.*' => 'file|max:10240'
        ]);

        try {
            $chatSession = ChatSession::findOrFail($sessionId);

            if ($chatSession->status !== 'ACTIVE') {
                return sendResponse("Chat session is not active.", [], ["Chat session is not active"], 400);
            }

            $messageData = [
                'chat_session_id' => $chatSession->id,
                'sender_type' => $request->sender_type,
                'sender_id' => Auth::id(),
                'sender_name' => $request->sender_name ?? Auth::user()?->name,
                'message' => $request->message,
                'message_type' => $request->message_type ?? 'TEXT'
            ];
            if ($request->hasFile('attachments')) {
                $attachments = [];
                foreach ($request->file('attachments') as $file) {
                    $attachments[] = uploadFile($file, 'chat/attachments');
                }
                $messageData['attachments'] = $attachments;
            }
            $message = ChatMessage::create($messageData);
            $customerName = $chatSession->customer_name ?: 'Guest';
            $messagePreview = Str::limit($message->message, 50) ?: 'No message content';
            $notificationContent = "📝 {$messagePreview}\n👤 Customer: {$customerName}\n📞 " . ($chatSession->customer_phone ?? 'No phone') . "\n📧 " . ($chatSession->customer_email ?? 'No email');
            if ($request->sender_type === "CUSTOMER") {
                $recipients = User::role(['customer service', 'Super Admin'])->get();
                foreach ($recipients as $recipient) {

                    create_notification(
                        $recipient,
                        "💬 New Chat Message from {$customerName}",
                        $notificationContent,
                        [
                            'chat_session_id' => $chatSession->id,
                            'customer_name' => $customerName,
                            'message_preview' => $messagePreview,
                            'timestamp' => now()->toDateTimeString(),
                            'priority' => 'high'
                        ],
                        'unassigned_chat',
                        false
                    );
                }
            }
            broadcast(new ChatMessageSent($message));

            return sendResponse("Message sent successfully.", $message->load('sender:id,name'));
        } catch (QueryException $e) {
            return sendResponse("Error Occurred.", [], [$e->getMessage()], 422);
        }
    }

    /**
     * @OA\Post(
     *     path="/chat-sessions/{sessionId}/assign-agent",
     *     summary="Assign an agent to a chat session",
     *     description="Assigns an agent to a chat session.",
     *     tags={"Other"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="sessionId",
     *         in="path",
     *         description="ID of the chat session",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="agent_id", type="integer", description="ID of the agent to assign")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Agent assigned successfully.",
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error Occurred.",
     *         @OA\JsonContent()
     *     )
     * )
     */
    public function assignAgent(Request $request, $sessionId)
    {
        $request->validate([
            'agent_id' => 'required|integer|exists:users,id'
        ]);

        try {
            $chatSession = ChatSession::findOrFail($sessionId);
            $chatSession->update([
                'assigned_agent_id' => $request->agent_id
            ]);

            $systemMessage = ChatMessage::create([
                'chat_session_id' => $chatSession->id,
                'sender_type' => 'SYSTEM',
                'message' => 'Agent assigned to chat session',
                'message_type' => 'SYSTEM'
            ]);

            $agent = User::find($request->agent_id);
            if ($agent) {
                create_notification(
                    $agent,
                    "New Chat Assigned",
                    "A new chat session has been assigned to you",
                    ['chat_session_id' => $chatSession->id],
                    'assigned_chat'
                );
            }
            broadcast(new ChatMessageSent($systemMessage));
            broadcast(new ChatSessionStatusChanged($chatSession, 'agent_assigned'));
            return sendResponse("Agent assigned successfully.", new ChatSessionResource($chatSession));
        } catch (QueryException $e) {
            return sendResponse("Error Occurred.", [], [$e->getMessage()], 422);
        }
    }


    /**
     * @OA\Post(
     *     path="/chat-sessions/{sessionId}/transfer-agent",
     *     summary="Transfer a chat session to a new agent",
     *     description="Transfers a chat session to a new agent.",
     *     tags={"Other"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="sessionId",
     *         in="path",
     *         description="ID of the chat session",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="new_agent_id", type="integer", description="ID of the new agent"),
     *             @OA\Property(property="reason", type="string", description="Reason for transfer")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Chat transferred successfully.",
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error Occurred.",
     *         @OA\JsonContent()
     *     )
     * )
     */
    public function transferAgent(Request $request, $sessionId)
    {
        $request->validate([
            'new_agent_id' => 'required|integer|exists:users,id',
            'reason' => 'nullable|string'
        ]);

        try {
            $chatSession = ChatSession::findOrFail($sessionId);
            $oldAgentId = $chatSession->assigned_agent_id;

            $chatSession->update([
                'assigned_agent_id' => $request->new_agent_id
            ]);

            // Create system message
            $reason = $request->reason ? " - {$request->reason}" : '';
            ChatMessage::create([
                'chat_session_id' => $chatSession->id,
                'sender_type' => 'SYSTEM',
                'message' => "Chat session transferred to new agent{$reason}",
                'message_type' => 'SYSTEM'
            ]);

            return sendResponse("Chat transferred successfully.", new ChatSessionResource($chatSession));
        } catch (QueryException $e) {
            return sendResponse("Error Occurred.", [], [$e->getMessage()], 422);
        }
    }

    /**
     * @OA\Post(
     *     path="/chat-sessions/{sessionId}/escalate",
     *     summary="Escalate chat session to a ticket",
     *     description="Escalates a chat session to a new ticket.",
     *     tags={"Other"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="sessionId",
     *         in="path",
     *         description="ID of the chat session",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="subject", type="string", description="Subject of the ticket")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Ticket generated successfully. Chat session continues.",
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error Occurred.",
     *         @OA\JsonContent()
     *     )
     * )
     */
    public function escalateToTicket(Request $request, $sessionId)
    {
        $request->validate([
            'subject' => 'required|string|max:255'
        ]);

        try {
            $chatSession = ChatSession::findOrFail($sessionId);

            // Create ticket from chat session
            $ticketData = [
                'ticket_number' => $this->generateTicketNumber(),
                'customer_name' => $chatSession->customer_name,
                'customer_email' => $chatSession->customer_email,
                'customer_phone' => $chatSession->customer_phone,
                'subject' => $request->subject,
                'description' => $this->getChatSummary($chatSession),
                'category' => 'GENERAL', // Default category
                'priority' => $chatSession->priority,
                'status' => 'OPEN',
                'chat_session_id' => $chatSession->id,
                'created_by' => Auth::id()
            ];

            $ticket = Ticket::create($ticketData);

            // Update chat session - keep it ACTIVE but link to ticket
            $chatSession->update([
                'ticket_id' => $ticket->id
            ]);

            // Create system message
            $systemMessage = ChatMessage::create([
                'chat_session_id' => $chatSession->id,
                'sender_id' => Auth::id(),
                'sender_name' => Auth::user()?->name,
                'sender_type' => 'SYSTEM',
                'message' => "Ticket #{$ticket->ticket_number} has been generated for this conversation. You can continue chatting here or use the ticket number to resume later.",
                'message_type' => 'SYSTEM'
            ]);

            // Create contact history entry
            $this->createContactHistory($chatSession, 'CHAT', 'Chat escalated to ticket', $ticket->id);

            // Load the updated session with ticket
            $chatSession->load(['ticket', 'messages.sender:id,name']);

            // Broadcast events
            broadcast(new ChatMessageSent($systemMessage));
            broadcast(new ChatSessionStatusChanged($chatSession, 'escalated'));

            return sendResponse("Ticket generated successfully. Chat session continues.", [
                'ticket' => $ticket,
                'chat_session' => new ChatSessionResource($chatSession)
            ]);
        } catch (QueryException $e) {
            return sendResponse("Error Occurred.", [], [$e->getMessage()], 422);
        }
    }

    /**
     * @OA\Post(
     *     path="/chat-sessions/{sessionId}/close",
     *     summary="Close a chat session",
     *     description="Closes a chat session.",
     *     tags={"Other"},
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
     *         description="Chat session closed successfully.",
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error Occurred.",
     *         @OA\JsonContent()
     *     )
     * )
     */
    public function closeSession(Request $request, $sessionId)
    {
        info("here in close session");

        try {
            $chatSession = ChatSession::findOrFail($sessionId);

            $chatSession->update([
                'status' => 'CLOSED',
                'ended_at' => now(),
            ]);

            // Create system message
            $systemMessage = ChatMessage::create([
                'chat_session_id' => $chatSession->id,
                'sender_type' => 'SYSTEM',
                'sender_name' => Auth::user()?->name ?? 'System',
                'message' => 'Chat session closed',
                'message_type' => 'SYSTEM'
            ]);


            // Create contact history entry
            $this->createContactHistory($chatSession, 'CHAT', 'Chat session closed');

            $chatSession->save();
            info("chat session saved");
            // Broadcast events
            broadcast(new ChatMessageSent($systemMessage));
            broadcast(new ChatSessionStatusChanged($chatSession, 'closed'));

            return sendResponse("Chat session closed successfully.", new ChatSessionResource($chatSession));
        } catch (QueryException $e) {
            return sendResponse("Error Occurred.", [], false, [$e->getMessage()], 422);
        }
    }

    public function archiveSession(Request $request, $sessionId)
    {
        info("here in archive session");

        try {
            $chatSession = ChatSession::findOrFail($sessionId);

            $chatSession->update([
                'status' => 'ARCHIVED',
                'ended_at' => now(),
            ]);

            // Create system message
            $systemMessage = ChatMessage::create([
                'chat_session_id' => $chatSession->id,
                'sender_type' => 'SYSTEM',
                'sender_name' => Auth::user()?->name ?? 'System',
                'message' => 'Chat session archived',
                'message_type' => 'SYSTEM'
            ]);


            // Create contact history entry
            $this->createContactHistory($chatSession, 'CHAT', 'Chat session archived');

            $chatSession->save();
            info("chat session saved");
            // Broadcast events
            broadcast(new ChatMessageSent($systemMessage));
            broadcast(new ChatSessionStatusChanged($chatSession, 'archived'));

            return sendResponse("Chat session archived successfully.", new ChatSessionResource($chatSession));
        } catch (QueryException $e) {
            return sendResponse("Error Occurred.", [], false, [$e->getMessage()], 422);
        }
    }

    /**
     * @OA\Get(
     *     path="/chat-sessions/{sessionId}/history",
     *     summary="Get full chat history for a session",
     *     description="Retrieves the complete chat history for a given session ID.",
     *     tags={"Other"},
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
     *         description="Chat history retrieved successfully.",
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error Occurred.",
     *         @OA\JsonContent()
     *     )
     * )
     */
    public function getChatHistory($sessionId)
    {
        try {
            $chatSession = ChatSession::with([
                'messages' => function ($query) {
                    $query->select('id', 'chat_session_id', 'sender_type', 'sender_id', 'sender_name', 'message', 'message_type', 'attachments', 'created_at')
                        ->orderBy('created_at', 'asc')
                        ->with('sender:id,name');
                },
                'assignedAgent:id,name,email'
            ])->findOrFail($sessionId);

            return sendResponse("Chat history retrieved successfully.", new ChatSessionResource($chatSession));
        } catch (QueryException $e) {
            return sendResponse("Error Occurred.", [], [$e->getMessage()], 422);
        }
    }

    /**
     * @OA\Get(
     *     path="/chat-sessions/{sessionId}/messages",
     *     summary="Get paginated messages for a chat session",
     *     description="Retrieves paginated messages for a given chat session ID.",
     *     tags={"Other"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="sessionId",
     *         in="path",
     *         description="ID of the chat session",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of messages per page (max 100)",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="sort",
     *         in="query",
     *         description="Sort shipment (asc, desc)",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Messages retrieved successfully.",
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error Occurred.",
     *         @OA\JsonContent()
     *     )
     * )
     */
    public function getMessages(Request $request, $sessionId)
    {
        $request->validate([
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
            'sort' => 'nullable|string|in:asc,desc'
        ]);

        try {
            $chatSession = ChatSession::where('session_id', $sessionId)->first();

            $perPage = $request->get('per_page', 20);
            $sort = $request->get('sort', 'desc'); // Default to desc for loading older messages first

            $messages = ChatMessage::where('chat_session_id', $chatSession->id)
                ->with('sender:id,name')
                ->orderBy('created_at', $sort)
                ->paginate($perPage);

            return sendResponse("Messages retrieved successfully.", [
                'data' => $messages->items(),
                'current_page' => $messages->currentPage(),
                'last_page' => $messages->lastPage(),
                'per_page' => $messages->perPage(),
                'total' => $messages->total(),
                'has_more_pages' => $messages->hasMorePages(),
                'next_page_url' => $messages->nextPageUrl(),
                'prev_page_url' => $messages->previousPageUrl()
            ]);
        } catch (QueryException $e) {
            return sendResponse("Error Occurred.", [], [$e->getMessage()], 422);
        }
    }

    /**
     * @OA\Post(
     *     path="/chat-sessions/by-ticket",
     *     summary="Get chat session by ticket number and customer email",
     *     description="Retrieves a chat session based on ticket number and customer email.",
     *     tags={"Other"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="ticket_number", type="string", description="Ticket number"),
     *             @OA\Property(property="customer_email", type="string", format="email", description="Customer email")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Chat session retrieved successfully.",
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="No chat session found for this ticket.",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error Occurred.",
     *         @OA\JsonContent()
     *     )
     * )
     */
    public function getByTicket(Request $request)
    {
        $request->validate([
            'ticket_number' => 'required|string',
            'customer_email' => 'required|email'
        ]);

        try {
            $ticket = Ticket::where('ticket_number', $request->ticket_number)
                ->where('customer_email', $request->customer_email)
                ->firstOrFail();

            $chatSession = ChatSession::with([
                'messages' => function ($query) {
                    $query->select('id', 'chat_session_id', 'sender_type', 'sender_id', 'sender_name', 'message', 'message_type', 'attachments', 'created_at')
                        ->orderBy('created_at', 'asc')
                        ->with('sender:id,name');
                },
                'ticket'
            ])->where('ticket_id', $ticket->id)->first();

            if (!$chatSession) {
                return sendResponse("No chat session found for this ticket.", [], ["Chat session not found"], 404);
            }

            return sendResponse("Chat session retrieved successfully.", new ChatSessionResource($chatSession));
        } catch (QueryException $e) {
            return sendResponse("Error Occurred.", [], [$e->getMessage()], 422);
        }
    }

    /**
     * @OA\Post(
     *     path="/chat-sessions/resume-from-ticket",
     *     summary="Resume chat session from ticket",
     *     description="Resumes a chat session from an existing ticket.",
     *     tags={"Other"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="ticket_number", type="string", description="Ticket number"),
     *             @OA\Property(property="customer_name", type="string", description="Customer name"),
     *             @OA\Property(property="customer_email", type="string", format="email", description="Customer email"),
     *             @OA\Property(property="initial_message", type="string", description="Initial message to resume conversation")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Chat session resumed successfully with full history.",
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="No chat session found for this ticket.",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error Occurred.",
     *         @OA\JsonContent()
     *     )
     * )
     */
    public function resumeFromTicket(Request $request)
    {
        $request->validate([
            'ticket_number' => 'required|string',
            'customer_phone' => 'required|string',
            'initial_message' => 'required|string'
        ]);

        try {
            $ticket = Ticket::where('ticket_number', $request->ticket_number)
                ->where('customer_phone', $request->customer_phone)
                ->firstOrFail();

            // Find the original chat session for this ticket
            $chatSession = ChatSession::where('ticket_id', $ticket->id)
                ->orWhere('id', $ticket->chat_session_id)
                ->first();
            pinfo($chatSession, "chat session");
            if (!$chatSession) {
                return sendResponse("No chat session found for this ticket.", [], ["Chat session not found"], 404);
            }

            // If chat session is not active, reactivate it
            if ($chatSession->status !== 'ACTIVE') {
                $chatSession->update([
                    'status' => 'ACTIVE',
                    'ended_at' => null
                ]);
            }

            // Ensure the very first customer message (initial_message in ChatSession) exists as a ChatMessage record
            if ($chatSession->messages()->count() === 0 && $chatSession->initial_message) {
                ChatMessage::create([
                    'chat_session_id' => $chatSession->id,
                    'sender_type' => 'CUSTOMER',
                    'sender_name' => $chatSession->customer_name,
                    'message' => $chatSession->initial_message,
                    'message_type' => 'TEXT'
                ]);
            }

            // Add the new message to continue the conversation
            $customerMessage = ChatMessage::create([
                'chat_session_id' => $chatSession->id,
                'sender_type' => 'CUSTOMER',
                'sender_name' => $request->customer_name,
                'message' => $request->initial_message,
                'message_type' => 'TEXT'
            ]);

            // Create system message about resuming
            $systemMessage = ChatMessage::create([
                'chat_session_id' => $chatSession->id,
                'sender_name' => Auth::user()?->name ?? 'System',
                'sender_type' => 'SYSTEM',
                'message' => "Conversation resumed using ticket #{$ticket->ticket_number}.",
                'message_type' => 'SYSTEM'
            ]);

            // Load the session with all messages and relationships
            // $chatSession->load([
            //     'messages' => function($query) {
            //         $query->select('id', 'chat_session_id', 'sender_type', 'sender_id', 'sender_name', 'message', 'message_type', 'attachments', 'created_at')
            //               ->orderBy('created_at', 'asc')
            //               ->with('sender:id,name');
            //     },
            //     'ticket'
            // ]);

            // Broadcast events
            broadcast(new ChatMessageSent($customerMessage));
            broadcast(new ChatMessageSent($systemMessage));
            broadcast(new ChatSessionStatusChanged($chatSession, 'resumed'));

            // Debug: log the final data array
            info('ResumeFromTicket returning', ['chatSession' => $chatSession->toArray()]);

            $chatSession['messages'] = ChatMessage::where('chat_session_id', $chatSession->id)->get();
            $chatSession['ticket'] = Ticket::where('id', $chatSession->ticket_id)->first();

            pinfo($chatSession['messages'], "chat session messages");
            pinfo($chatSession['ticket'], "chat session ticket");

            pinfo(ChatMessage::where('chat_session_id', $chatSession->id)->count(), "chat session messages count");
            pinfo(Ticket::where('id', $chatSession->ticket_id)->first(), "chat session ticket");

            return sendResponse("Chat session resumed successfully with full history.", new ChatSessionResource($chatSession));
        } catch (QueryException $e) {
            return sendResponse("Error Occurred.", [], [$e->getMessage()], 422);
        }
    }

    private function generateSessionId()
    {
        return 'CHAT' . now()->format('Ymd') . strtoupper(Str::random(8));
    }

    private function generateTicketNumber()
    {
        $prefix = 'TKT';
        $date = now()->format('Ymd');
        $lastTicket = Ticket::whereDate('created_at', today())
            ->orderBy('id', 'desc')
            ->first();

        $sequence = $lastTicket ? (intval(substr($lastTicket->ticket_number, -4)) + 1) : 1;

        return $prefix . $date . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }

    private function getChatSummary($chatSession)
    {
        $messages = $chatSession->messages()->where('sender_type', '!=', 'SYSTEM')->get();
        $summary = "Chat session summary:\n\n";

        foreach ($messages as $message) {
            $summary .= "[{$message->sender_type}] {$message->sender_name}: {$message->message}\n";
        }

        return $summary;
    }

    private function createContactHistory($chatSession, $type, $summary, $ticketId = null)
    {
        ContactHistory::create([
            'contact_id' => uniqid(),
            'customer_name' => $chatSession->customer_name,
            'customer_email' => $chatSession->customer_email,
            'customer_phone' => $chatSession->customer_phone,
            'interaction_type' => $type,
            'method' => 'CHAT',
            'channel' => 'WEBSITE',
            'summary' => $summary,
            'details' => "Chat Session: {$chatSession->session_id}",
            'status' => 'LOGGED',
            'handled_by' => Auth::id(),
            'related_chat_session_id' => $chatSession->id,
            'related_ticket_id' => $ticketId,
            'contacted_at' => now(),
        ]);
    }
}
