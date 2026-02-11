<?php

namespace App\Http\Controllers\Api\v1;
use App\Http\Controllers\Controller;


use App\Http\Requests\StoreTicketRequest;
use App\Http\Requests\UpdateTicketRequest;
use App\Http\Resources\TicketResource;
use App\Models\ChatSession;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\ContactHistory;
use App\Traits\Searchable;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use App\Models\ChatMessage;
use App\Events\ChatMessageSent;
use App\Events\ChatSessionStatusChanged;
use App\Models\User;

/**
 * @OA\Tag(name="Other", description="Ticket Management API")
 */
class TicketController extends Controller
{
    // use Searchable;

    /**
     * @OA\Get(
     *     path="/tickets",
     *     summary="Get all tickets",
     *     description="Retrieves a list of tickets. Only Customer Service agents see tickets assigned to them.",
     *     tags={"Other"},
     *     @OA\Parameter(   
     *         name="per_page",
     *         in="query",
     *         description="Number of tickets per page",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search query for ticket number, customer name, email, or subject",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Tickets retrieved successfully",
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *     ),
     *     security={{"bearerAuth":{}}}
     * )
     */

    public function index(Request $request)
    {
        $perPage = request()->query('per_page', 8);
        $search = $request->input(key: "query");
        $isCustomerService = Auth::user()->hasRole('Customer Service');
        $tickets = Ticket::select('id', 'ticket_number', 'customer_name', 'customer_email', 'subject', 'priority', 'status', 'assigned_agent_id', 'created_at', 'due_date')
            ->with('assignedAgent:id,name')
            ->when($search, function ($query, $search) {
                return $query->where('ticket_number', 'like', "%{$search}%");
            })
            ->when($isCustomerService, function ($query) {
                return $query->where('assigned_agent_id', Auth::id());
            })
            ->paginate($perPage);

        return sendResponse(
            "Tickets retrieved successfully.",
            $tickets,
            true,
            []
        );
    }
    // protected function modelQuery()
    // {
    //     $query = Ticket::query()->select('id', 'ticket_number', 'customer_name', 'customer_email', 'subject', 'priority', 'status', 'assigned_agent_id', 'created_at', 'due_date');
    //     $user = Auth::user();
    //     if ($user->hasRole('Customer Service')) {
    //         $query->where('assigned_agent_id', $user->id);
    //     }
    //     return $query;
    // }

    // public function index()
    // {
    //     $tickets = $this->handleSearch(
    //         searchColumns: ['ticket_number', 'customer_name', 'customer_email', 'subject'],
    //         withRelationships: [
    //             'assignedAgent:id,name',
    //             'comments:id,ticket_id,comment,created_at'
    //         ],
    //         perPage: request()->input('per_page', 10),
    //         shipmentColumn: 'created_at',
    //         shipmentDirection: 'desc'
    //     );
    //     return sendResponse("Tickets retrieved successfully.", new TicketResource(resource: $tickets), []);
    // }

    /**
     * @OA\Post(
     *     path="/tickets/store",
     *     summary="Create a new ticket",
     *     tags={"Other"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(
     *                     property="customer_name",
     *                     type="string",
     *                     description="Customer name (required)",
     *                 ),
     *                 @OA\Property(
     *                     property="customer_email",
     *                     type="string",
     *                     format="email",
     *                     description="Customer email (required)",
     *                 ),
     *                 @OA\Property(
     *                     property="customer_phone",
     *                     type="string",
     *                     description="Customer phone number",
     *                 ),
     *                 @OA\Property(
     *                     property="subject",
     *                     type="string",
     *                     description="Ticket subject (required)",
     *                 ),
     *                 @OA\Property(
     *                     property="description",
     *                     type="string",
     *                     description="Ticket description (required)",
     *                 ),
     *                 @OA\Property(
     *                     property="category",
     *                     type="string",
     *                     description="Ticket category",
     *                 ),
     *                 @OA\Property(
     *                     property="priority",
     *                     type="string",
     *                     description="Ticket priority",
     *                 ),
     *                 @OA\Property(
     *                     property="due_date",
     *                     type="string",
     *                     format="date",
     *                     description="Ticket due date",
     *                 ),
     *                 @OA\Property(
     *                     property="tags",
     *                     type="array",
     *                     description="Ticket tags",
     *                     @OA\Items(type="string")
     *                 ),
     *                 @OA\Property(
     *                     property="attachments",
     *                     type="array",
     *                     description="Ticket attachments",
     *                     @OA\Items(type="string", format="binary")
     *                 ),
     *                 @OA\Property(
     *                     property="assigned_agent_id",
     *                     type="integer",
     *                     description="Assigned agent ID",
     *                 ),
     *                 @OA\Property(
     *                     property="internal_notes",
     *                     type="string",
     *                     description="Internal notes",
     *                 ),
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Ticket created successfully",
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *     ),
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function store(StoreTicketRequest $request)
    {
        $request->validated();
        try {
            $ticketData = $request->all();
            $ticketData['ticket_number'] = $this->generateTicketNumber();
            $ticketData['created_by'] = Auth::id();
            if ($request->hasFile('attachments')) {
                $attachments = [];
                foreach ($request->file('attachments') as $file) {
                    $attachments[] = uploadFile($file, 'public/tickets/attachments');
                }
                $ticketData['attachments'] = $attachments;
            }
            $ticket = Ticket::create($ticketData);
            ChatSession::create([
                'session_id' => uniqid(),
                'customer_name' => $request->customer_name,
                'customer_email' => $request->customer_email,
                'customer_phone' => $request->customer_phone,
                'priority' => $request->priority ?? 'MEDIUM',
                'status' => 'ACTIVE',
                'started_at' => now(),
                'ticket_id' => $ticket->id,
            ]);
            $this->createContactHistory($ticket, 'TICKET', 'Ticket created: ' . $ticket->subject);
        } catch (QueryException $e) {
            return sendResponse("Error Occurred.", [], [$e->getMessage()], 422);
        }
        return sendResponse("Ticket created successfully.", new TicketResource($ticket));
    }

    /**
     * @OA\Post(
     *     path="/tickets/update",
     *     summary="Update a ticket",
     *     tags={"Other"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(
     *                     property="id",
     *                     type="integer",
     *                     description="Ticket ID (required)",
     *                 ),
     *                 @OA\Property(
     *                     property="customer_name",
     *                     type="string",
     *                     description="Customer name",
     *                 ),
     *                 @OA\Property(
     *                     property="customer_email",
     *                     type="string",
     *                     format="email",
     *                     description="Customer email",
     *                 ),
     *                 @OA\Property(
     *                     property="customer_phone",
     *                     type="string",
     *                     description="Customer phone number",
     *                 ),
     *                 @OA\Property(
     *                     property="subject",
     *                     type="string",
     *                     description="Ticket subject",
     *                 ),
     *                 @OA\Property(
     *                     property="description",
     *                     type="string",
     *                     description="Ticket description",
     *                 ),
     *                 @OA\Property(
     *                     property="category",
     *                     type="string",
     *                     description="Ticket category",
     *                 ),
     *                 @OA\Property(
     *                     property="priority",
     *                     type="string",
     *                     description="Ticket priority",
     *                 ),
     *                 @OA\Property(
     *                     property="status",
     *                     type="string",
     *                     description="Ticket status",
     *                 ),
     *                 @OA\Property(
     *                     property="due_date",
     *                     type="string",
     *                     format="date",
     *                     description="Ticket due date",
     *                 ),
     *                 @OA\Property(
     *                     property="tags",
     *                     type="array",
     *                     description="Ticket tags",
     *                     @OA\Items(type="string")
     *                 ),
     *                 @OA\Property(
     *                     property="attachments",
     *                     type="array",
     *                     description="Ticket attachments",
     *                     @OA\Items(type="string", format="binary")
     *                 ),
     *                 @OA\Property(
     *                     property="assigned_agent_id",
     *                     type="integer",
     *                     description="Assigned agent ID",
     *                 ),
     *                 @OA\Property(
     *                     property="resolution",
     *                     type="string",
     *                     description="Ticket resolution",
     *                 ),
     *                 @OA\Property(
     *                     property="internal_notes",
     *                     type="string",
     *                     description="Internal notes",
     *                 ),
     *                 @OA\Property(
     *                     property="rating",
     *                     type="integer",
     *                     description="Customer rating",
     *                 ),
     *                 @OA\Property(
     *                     property="feedback",
     *                     type="string",
     *                     description="Customer feedback",
     *                 ),
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Ticket updated successfully",
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *     ),
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function update(UpdateTicketRequest $request)
    {
        $request->validated();
        try {
            $ticket = Ticket::find($request->id);
            // Track previous agent assignment
            $oldAgent = $ticket->assigned_agent_id;
            $oldStatus = $ticket->status;
            $updateData = $request->all();
            if ($request->hasFile('attachments')) {
                $attachments = $ticket->attachments ?? [];
                foreach ($request->file('attachments') as $file) {
                    $attachments[] = uploadFile($file, 'public/tickets/attachments');
                }
                $updateData['attachments'] = $attachments;
            }
            if ($request->status === 'RESOLVED' && $oldStatus !== 'RESOLVED') {
                $updateData['resolved_at'] = now();
                $ticket->chatSession->update(['status' => 'CLOSED']);
            }
            if ($request->status === 'CLOSED' && $oldStatus !== 'CLOSED') {
                $updateData['closed_at'] = now();
                $ticket->chatSession->update(['status' => 'CLOSED']);
            }
            $ticket->update($updateData);
            // If the assigned agent changed, log history and assign corresponding chat session
            if ($request->has('assigned_agent_id') && $oldAgent !== $request->assigned_agent_id) {
                // Log ticket assignment in contact history
                $agent = User::find($request->assigned_agent_id);
                $this->createContactHistory($ticket, 'TICKET', "Assigned to agent {$agent->name}");
                // Update chat session assignment
                $chatSession = ChatSession::where('ticket_id', $ticket->id)
                    ->orWhere('id', $ticket->chat_session_id)
                    ->first();
                if ($chatSession) {
                    $chatSession->update(['assigned_agent_id' => $request->assigned_agent_id]);
                    $systemMessage = ChatMessage::create([
                        'chat_session_id' => $chatSession->id,
                        'sender_type' => 'SYSTEM',
                        'message' => 'Agent assigned to support ticket',
                        'message_type' => 'SYSTEM',
                    ]);
                    broadcast(new ChatMessageSent($systemMessage));
                    broadcast(new ChatSessionStatusChanged($chatSession, 'agent_assigned'));
                }
            }
            if ($oldStatus !== $request->status) {
                $this->createContactHistory($ticket, 'TICKET', "Status changed from {$oldStatus} to {$request->status}");
            }
        } catch (QueryException $e) {
            return sendResponse("Error Occurred.", [], [$e->getMessage()], 422);
        }
        return sendResponse("Ticket updated successfully.", new TicketResource($ticket));
    }

    /**
     * @OA\Post(
     *     path="/tickets/delete",
     *     summary="Delete a ticket",
     *     tags={"Other"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", description="Ticket ID (required)")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Ticket deleted successfully",
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *     ),
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function delete(Request $request)
    {
        try {
            Ticket::where('id', $request->id)->delete();
        } catch (QueryException $e) {
            return sendResponse("Error Occurred.", [], [$e->getMessage()], 422);
        }
        return sendResponse("Ticket deleted successfully.", []);
    }

    /**
     * @OA\Get(
     *     path="/tickets/show/{id}",
     *     summary="Get a ticket by ID",
     *     tags={"Other"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Ticket ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Ticket retrieved successfully",
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Ticket not found",
     *     ),
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function show($id)
    {
        $ticket = Ticket::with([
            'assignedAgent:id,name,email',
            'comments.user:id,name',
            'chatSession',
            'contactHistories'
        ])->find($id);
        if (!$ticket) {
            return sendResponse("Ticket not found.", [], ["Ticket not found"], 404);
        }
        return sendResponse("Ticket retrieved successfully.", new TicketResource($ticket));
    }

    /**
     * @OA\Post(
     *     path="/tickets/{ticketId}/comments",
     *     summary="Add a comment to a ticket",
     *     tags={"Other"},
     *     @OA\Parameter(
     *         name="ticketId",
     *         in="path",
     *         description="Ticket ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(
     *                     property="comment",
     *                     type="string",
     *                     description="Comment text (required)",
     *                 ),
     *                 @OA\Property(
     *                     property="is_internal",
     *                     type="boolean",
     *                     description="Whether the comment is internal",
     *                 ),
     *                 @OA\Property(
     *                     property="attachments",
     *                     type="array",
     *                     description="Comment attachments",
     *                     @OA\Items(type="string", format="binary")
     *                 ),
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Comment added successfully",
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *     ),
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function addComment(Request $request, $ticketId)
    {
        $request->validate([
            'comment' => 'required|string',
            'is_internal' => 'boolean',
            'attachments.*' => 'file|max:10240' // 10MB max per file
        ]);
        try {
            $ticket = Ticket::findOrFail($ticketId);
            $commentData = [
                'ticket_id' => $ticketId,
                'user_id' => Auth::id(),
                'comment' => $request->comment,
                'is_internal' => $request->boolean('is_internal', false)
            ];
            if ($request->hasFile('attachments')) {
                $attachments = [];
                foreach ($request->file('attachments') as $file) {
                    $attachments[] = uploadFile($file, 'public/tickets/comments');
                }
                $commentData['attachments'] = $attachments;
            }
            $comment = TicketComment::create($commentData);
            $this->createContactHistory($ticket, 'TICKET', 'Comment added to ticket');
            return sendResponse("Comment added successfully.", $comment->load('user:id,name'));
        } catch (QueryException $e) {
            return sendResponse("Error Occurred.", [], [$e->getMessage()], 422);
        }
    }

    /**
     * @OA\Post(
     *     path="/tickets/customer-tickets",
     *     summary="Get tickets for a customer",
     *     tags={"Other"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="customer_email", type="string", format="email", description="Customer email (required)")
     *         )
     *     ),
     *      @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of tickets per page",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Customer tickets retrieved successfully",
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *     )
     * )
     */
    public function customerTickets(Request $request)
    {
        $request->validate([
            'customer_email' => 'required|email',
        ]);
        $tickets = Ticket::where('customer_email', $request->customer_email)
            ->with(['assignedAgent:id,name', 'comments'])
            ->orderBy('created_at', 'desc')
            ->paginate(request()->input('per_page', 10));
        return sendResponse("Customer tickets retrieved successfully.", new TicketResource($tickets));
    }

    /**
     * @OA\Get(
     *     path="/tickets/stats",
     *     summary="Get ticket statistics",
     *     tags={"Other"},
     *     @OA\Response(
     *         response=200,
     *         description="Ticket stats retrieved successfully",
     *     ),
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function getStats(Request $request)
    {
        $stats = [
            'total_tickets' => Ticket::count(),
            'open_tickets' => Ticket::where('status', 'OPEN')->count(),
            'in_progress_tickets' => Ticket::where('status', 'IN_PROGRESS')->count(),
            'resolved_tickets' => Ticket::where('status', 'RESOLVED')->count(),
            'overdue_tickets' => Ticket::overdue()->count(),
            'high_priority_tickets' => Ticket::where('priority', 'HIGH')->count(),
            'urgent_tickets' => Ticket::where('priority', 'URGENT')->count(),
        ];
        return sendResponse("Ticket stats retrieved successfully.", $stats);
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

    /**
     * @OA\Get(
     *     path="/tickets/agents",
     *     summary="Get customer service agents",
     *     tags={"Other"},
     *     @OA\Response(
     *         response=200,
     *         description="Customer service agents retrieved successfully",
     *     ),
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function getAgents()
    {
        $agents = \App\Models\User::whereHas('roles', function ($query) {
            $query->where('name', 'Customer Service');
        })->select('id', 'name')->get();
        return sendResponse("Customer service agents retrieved successfully.", $agents);
    }

    /**
     * @OA\Get(
     *     path="/tickets/{ticketId}/chat",
     *     summary="Open chat session for ticket",
     *     tags={"Other"},
     *     @OA\Parameter(
     *         name="ticketId",
     *         in="path",
     *         description="Ticket ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Chat session found",
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Unauthorized access to ticket",
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="No chat session found for this ticket",
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *     ),
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function openChatSession(Request $request, $ticketId)
    {
        try {
            $ticket = Ticket::findOrFail($ticketId);
            $user = Auth::user();
            if ($user->hasRole('Customer Service') && $ticket->assigned_agent_id !== $user->id) {
                return sendResponse("Unauthorized access to ticket.", [], ["You can only access tickets assigned to you"], 403);
            }
            $chatSession = \App\Models\ChatSession::where('ticket_id', $ticket->id)
                ->orWhere('id', $ticket->chat_session_id)
                ->first();
            if (!$chatSession) {
                return sendResponse("No chat session found for this ticket.", [], ["Chat session not found"], 404);
            }
            return sendResponse("Chat session found.", [
                'chat_session_id' => $chatSession->id,
                'session_id' => $chatSession->session_id,
                'ticket' => $ticket
            ]);
        } catch (QueryException $e) {
            return sendResponse("Error Occurred.", [], [$e->getMessage()], 422);
        }
    }

    private function createContactHistory($ticket, $type, $summary)
    {
        ContactHistory::create([
            'contact_id' => uniqid(),
            'customer_name' => $ticket->customer_name,
            'customer_email' => $ticket->customer_email,
            'customer_phone' => $ticket->customer_phone,
            'interaction_type' => $type,
            'method' => 'WEBSITE',
            'channel' => 'WEBSITE',
            'summary' => $summary,
            'details' => "Ticket: {$ticket->ticket_number} - {$ticket->subject}",
            'status' => 'LOGGED',
            'handled_by' => Auth::id(),
            'related_ticket_id' => $ticket->id,
            'contacted_at' => now(),
        ]);
    }
}
