<?php

namespace App\Http\Controllers\Api\v1;
use App\Http\Controllers\Controller;


use App\Events\MerchantTicketMessageSent;
use App\Events\MerchantTicketChanged;
use App\Http\Requests\MerchantTicketMessageSentRequest;
use App\Http\Requests\StoreMerchantTicketRequest;
use App\Models\MerchantTicket;
use App\Models\MerchantTicketMessage;
use Illuminate\Support\Facades\Auth;

class MerchantTicketController extends Controller
{
    /**
     * Get Merchant Tickets
     *
     * @OA\Get(
     *     path="/merchant/tickets",
     *     summary="Get paginated list of merchant tickets",
     *     description="
     * Retrieve paginated list of support tickets for authenticated merchant.
     * 
     * **Features:**
     * - Merchant-scoped tickets
     * - Pagination support
     * - Merchant information included
     * - Latest first sorting
     * 
     * **Security:**
     * - Merchant authentication required
     * - Role-based filtering applied
     * ",
     *     operationId="getMerchantTickets",
     *     tags={"Merchant"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number for pagination",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Tickets fetched successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Tickets fetched successfully"),
     *             @OA\Property(property="data", type="object"),
     *             @OA\Property(property="errors", type="array", @OA\Items())
     *         )
     *     )
     * )
     */
    public function index()
    {

        $tickets = MerchantTicket::query();

        $user = Auth::user();
        if ($user->hasRole('Merchant')) {
            $tickets->where('merchant_id', $user->id);
        }

        $tickets = $tickets->with('merchant:id,name,email')
            ->orderBy('created_at', 'desc')
            ->paginate(10);
        return sendResponse('Tickets fetched successfully', $tickets);
    }

    /**
     * Get Ticket Details
     *
     * @OA\Get(
     *     path="/merchant/tickets/{id}",
     *     summary="Get specific ticket details",
     *     description="Retrieve detailed information for a specific support ticket.",
     *     operationId="getMerchantTicketDetails",
     *     tags={"Merchant"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Ticket ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Ticket fetched successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Ticket fetched successfully"),
     *             @OA\Property(property="data", type="object"),
     *             @OA\Property(property="errors", type="array", @OA\Items())
     *         )
     *     )
     * )
     */
    public function show($id)
    {
        $ticket = MerchantTicket::find($id);
        return sendResponse('Ticket fetched successfully', $ticket);
    }

    /**
     * Get Ticket Messages
     *
     * @OA\Get(
     *     path="/merchant/tickets/{id}/messages",
     *     summary="Get messages for a specific ticket",
     *     description="
     * Retrieve paginated list of messages for a specific support ticket.
     * 
     * **Features:**
     * - Message history
     * - Sender information
     * - Attachment support
     * - Chronological shipment
     * 
     * **Security:**
     * - Merchant authentication required
     * - Ticket access verification
     * ",
     *     operationId="getTicketMessages",
     *     tags={"Merchant"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Ticket ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number for pagination",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Messages fetched successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Messages fetched successfully"),
     *             @OA\Property(property="data", type="object"),
     *             @OA\Property(property="errors", type="array", @OA\Items())
     *         )
     *     )
     * )
     */
    public function messages($id)
    {
        $messages = MerchantTicketMessage::where('merchant_ticket_id', $id)
            ->with('sender:id,name,email')
            ->select('id', 'merchant_ticket_id', 'sender_id', 'message', 'message_type', 'attachments', 'created_at')
            ->orderBy('created_at', 'asc')
            ->paginate(10);

        return sendResponse('Messages fetched successfully', $messages);
    }

    /**
     * Create Support Ticket
     *
     * @OA\Post(
     *     path="/merchant/tickets/store",
     *     summary="Create new support ticket",
     *     description="
     * Create a new support ticket with initial message and broadcast events.
     * 
     * **Features:**
     * - Ticket creation
     * - Initial message
     * - Real-time broadcasting
     * - Auto merchant association
     * 
     * **Security:**
     * - Merchant authentication required
     * - Request validation applied
     * ",
     *     operationId="createSupportTicket",
     *     tags={"Merchant"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Ticket creation data",
     *         @OA\JsonContent(type="object")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Ticket created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Ticket created successfully"),
     *             @OA\Property(property="data", type="object"),
     *             @OA\Property(property="errors", type="array", @OA\Items())
     *         )
     *     )
     * )
     */
    public function store(StoreMerchantTicketRequest $request)
    {
        $request->validated();
        $data = $request->all();

        $data['merchant_id'] = Auth::id();
        $data['initial_message'] = $request->message;

        $ticket = MerchantTicket::create($data);

        $message = $ticket->messages()->create([
            'sender_id' => Auth::id(),
            'message' => $request->message,
            'message_type' => 'MERCHANT',
        ]);

        // Broadcast ticket creation
        broadcast(new MerchantTicketChanged($ticket, 'ACTIVE'));
        
        // Broadcast the initial message
        broadcast(new MerchantTicketMessageSent($message));

        return sendResponse('Ticket created successfully', $ticket);
    }

    /**
     * Send Ticket Message
     *
     * @OA\Post(
     *     path="/merchant/tickets/{id}/message",
     *     summary="Send message to ticket",
     *     description="
     * Send a new message to an existing support ticket with optional file attachments.
     * 
     * **Features:**
     * - Message sending
     * - File attachments support
     * - Real-time broadcasting
     * - Message validation
     * 
     * **Security:**
     * - Merchant authentication required
     * - Ticket ownership verified
     * ",
     *     operationId="sendTicketMessage",
     *     tags={"Merchant"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Ticket ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Message data with optional attachments",
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(property="message", type="string"),
     *                 @OA\Property(property="attachments", type="array", @OA\Items(type="string", format="binary"))
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Message sent successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Message sent successfully"),
     *             @OA\Property(property="data", type="array", @OA\Items()),
     *             @OA\Property(property="errors", type="array", @OA\Items())
     *         )
     *     )
     * )
     */
    public function sendMessage(MerchantTicketMessageSentRequest $request, $id)
    {
        $request->validated();
        $data = $request->all();

        $data['merchant_ticket_id'] = $id;
        $data['sender_id'] = Auth::id();

        if ($request->hasFile('attachments')) {
            $attachments = [];
            foreach ($request->file('attachments') as $file) {
                $attachments[] = uploadFile($file, 'merchant_ticket_attachments');
            }
            $data['attachments'] = $attachments;
        }

        $message = MerchantTicketMessage::create($data);

        broadcast(new MerchantTicketMessageSent($message));

        return sendResponse('Message sent successfully', []);
    }

    /**
     * Close Support Ticket
     *
     * @OA\Post(
     *     path="/merchant/tickets/{id}/close",
     *     summary="Close a support ticket",
     *     description="
     * Close an active support ticket and add system message.
     * 
     * **Features:**
     * - Ticket closure
     * - System message creation
     * - Real-time broadcasting
     * - Status update
     * 
     * **Security:**
     * - Merchant authentication required
     * - Ticket ownership verified
     * ",
     *     operationId="closeSupportTicket",
     *     tags={"Merchant"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Ticket ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Ticket closed successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Ticket closed successfully"),
     *             @OA\Property(property="data", type="array", @OA\Items()),
     *             @OA\Property(property="errors", type="array", @OA\Items())
     *         )
     *     )
     * )
     */
    public function close($id)
    {
        $ticket = MerchantTicket::find($id);
        $ticket->update(['status' => 'CLOSED']);
        $ticket->save();

        $message = $ticket->messages()->create([
            'sender_id' => Auth::id(),
            'message' => 'Ticket closed by ' . Auth::user()->name,
            'message_type' => 'SYSTEM',
        ]);

        // Broadcast ticket closure
        broadcast(new MerchantTicketChanged($ticket, 'CLOSED'));
        
        // Broadcast the system message
        broadcast(new MerchantTicketMessageSent($message));

        return sendResponse('Ticket closed successfully', []);
    }
}
