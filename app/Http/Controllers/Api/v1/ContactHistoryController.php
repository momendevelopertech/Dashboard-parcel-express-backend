<?php

namespace App\Http\Controllers\Api\v1;


use App\Http\Controllers\Controller;
use App\Http\Requests\StoreContactHistoryRequest;
use App\Http\Requests\UpdateContactHistoryRequest;
use App\Http\Resources\ContactHistoryResource;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use App\Models\ContactHistory;
use App\Traits\Searchable;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * @OA\Tag(name="Other", description="Contact History Management")
 */
class ContactHistoryController extends Controller
{
    use Searchable;

    protected function modelQuery()
    {
        return ContactHistory::query()->select('id', 'contact_id', 'customer_name', 'customer_email', 'interaction_type', 'method', 'summary', 'status', 'handled_by', 'contacted_at');
    }

    /**
     * @OA\Get(
     *     path="/contact-history",
     *     summary="Get all contact histories",
     *     description="Retrieves a list of contact histories.",
     *     tags={"Other"},
     *     @OA\Response(
     *         response=200,
     *         description="Contact histories retrieved successfully."
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error occurred."
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function index()
    {
        $contactHistories = $this->handleSearch(
            searchColumns: ['contact_id', 'customer_name', 'customer_email', 'summary'],
            withRelationships: [
                'handler:id,name',
                'relatedTicket:id,ticket_number,subject',
                'relatedChatSession:id,session_id,status'
            ],
            perPage: request()->input('per_page', 10),
            shipmentColumn: 'contacted_at',
            shipmentDirection: 'desc'
        );
        return sendResponse("Contact histories retrieved successfully.", new ContactHistoryResource(resource: $contactHistories), []);
    }

    /**
     * @OA\Post(
     *     path="/contact-history/store",
     *     summary="Create a new contact history",
     *     description="Creates a new contact history record.",
     *     tags={"Other"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="contact_id", type="string", description="Contact ID", example="contact_abcdef"),
     *             @OA\Property(property="customer_name", type="string", description="Customer Name (required)", example="John Doe"),
     *             @OA\Property(property="customer_email", type="string", description="Customer Email", example="john.doe@example.com"),
     *             @OA\Property(property="customer_phone", type="string", description="Customer Phone Number", example="123-456-7890"),
     *             @OA\Property(property="interaction_type", type="string", description="Interaction Type (required)", example="CHAT"),
     *             @OA\Property(property="method", type="string", description="Method (required)", example="EMAIL"),
     *             @OA\Property(property="channel", type="string", description="Channel", example="WEBSITE"),
     *             @OA\Property(property="summary", type="string", description="Summary (required)", example="Customer issue resolved"),
     *             @OA\Property(property="details", type="string", description="Details"),
     *             @OA\Property(property="status", type="string", description="Status", example="RESOLVED"),
     *             @OA\Property(property="related_ticket_id", type="integer", description="Related Ticket ID"),
     *             @OA\Property(property="related_chat_session_id", type="integer", description="Related Chat Session ID"),
     *             @OA\Property(property="contacted_at", type="string", format="date", description="Contacted At"),
     *             @OA\Property(property="tags", type="array", description="Tags", @OA\Items(type="string")),
     *             @OA\Property(property="outcome", type="string", description="Outcome"),
     *             @OA\Property(property="follow_up_required", type="boolean", description="Follow Up Required"),
     *             @OA\Property(property="follow_up_date", type="string", format="date", description="Follow Up Date"),
     *         ),
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Contact history created successfully."
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error occurred."
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function store(StoreContactHistoryRequest $request)
    {
        $request->validated();
        try {
            $contactHistoryData = $request->all();
            $contactHistoryData['handled_by'] = Auth::id();
            $contactHistoryData['contacted_at'] = $contactHistoryData['contacted_at'] ?? now();

            $contactHistory = ContactHistory::create($contactHistoryData);

        } catch (QueryException $e) {
            return sendResponse("Error Occurred.", [], [$e->getMessage()], 422);
        }
        return sendResponse("Contact history created successfully.", new ContactHistoryResource($contactHistory));
    }

    /**
     * @OA\Put(
     *     path="/contact-history/update",
     *     summary="Update a contact history",
     *     description="Updates an existing contact history record.",
     *     tags={"Other"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", description="Contact History ID (required)"),
     *             @OA\Property(property="contact_id", type="string", description="Contact ID"),
     *             @OA\Property(property="customer_name", type="string", description="Customer Name"),
     *             @OA\Property(property="customer_email", type="string", description="Customer Email"),
     *             @OA\Property(property="customer_phone", type="string", description="Customer Phone Number"),
     *             @OA\Property(property="interaction_type", type="string", description="Interaction Type"),
     *             @OA\Property(property="method", type="string", description="Method"),
     *             @OA\Property(property="channel", type="string", description="Channel"),
     *             @OA\Property(property="summary", type="string", description="Summary"),
     *             @OA\Property(property="details", type="string", description="Details"),
     *             @OA\Property(property="status", type="string", description="Status"),
     *             @OA\Property(property="related_ticket_id", type="integer", description="Related Ticket ID"),
     *             @OA\Property(property="related_chat_session_id", type="integer", description="Related Chat Session ID"),
     *             @OA\Property(property="contacted_at", type="string", format="date", description="Contacted At"),
     *             @OA\Property(property="tags", type="array", description="Tags", @OA\Items(type="string")),
     *             @OA\Property(property="outcome", type="string", description="Outcome"),
     *             @OA\Property(property="follow_up_required", type="boolean", description="Follow Up Required"),
     *             @OA\Property(property="follow_up_date", type="string", format="date", description="Follow Up Date"),
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Contact history updated successfully."
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error occurred."
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function update(UpdateContactHistoryRequest $request)
    {
        $request->validated();
        try {
            $contactHistory = ContactHistory::find($request->id);
            $contactHistory->update($request->all());

        } catch (QueryException $e) {
            return sendResponse("Error Occurred.", [], [$e->getMessage()], 422);
        }
        return sendResponse("Contact history updated successfully.", new ContactHistoryResource($contactHistory));
    }

    /**
     * @OA\Post(
     *     path="/contact-history/delete",
     *     summary="Delete a contact history",
     *     description="Deletes a contact history record.",
     *     tags={"Other"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", description="Contact History ID (required)")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Contact history deleted successfully."
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error occurred."
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function delete(Request $request)
    {
        try {
            ContactHistory::where('id', $request->id)->delete();
        } catch (QueryException $e) {
            return sendResponse("Error Occurred.", [], [$e->getMessage()], 422);
        }
        return sendResponse("Contact history deleted successfully.", []);
    }

    /**
     * @OA\Get(
     *     path="/contact-history/show/{id}",
     *     summary="Get a single contact history",
     *     description="Retrieves a single contact history record.",
     *     tags={"Other"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Contact History ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Contact history retrieved successfully."
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Contact history not found."
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function show($id)
    {
        $contactHistory = ContactHistory::with([
            'handler:id,name,email',
            'relatedTicket:id,ticket_number,subject,status',
            'relatedChatSession:id,session_id,status'
        ])->find($id);

        if (!$contactHistory) {
            return sendResponse("Contact history not found.", [], ["Contact history not found"], 404);
        }

        return sendResponse("Contact history retrieved successfully.", new ContactHistoryResource($contactHistory));
    }

    /**
     * @OA\Get(
     *     path="/contact-history/customer/{email}",
     *     summary="Get contact history by customer email",
     *     description="Retrieves contact history for a specific customer.",
     *     tags={"Other"},
     *     @OA\Parameter(
     *         name="email",
     *         in="path",
     *         description="Customer Email",
     *         required=true,
     *         @OA\Schema(type="string", format="email")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Customer contact history retrieved successfully."
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error occurred."
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function getCustomerHistory($email)
    {
        try {
            $contactHistories = ContactHistory::byCustomer($email)
                ->with(['handler:id,name', 'relatedTicket', 'relatedChatSession'])
                ->orderBy('contacted_at', 'desc')
                ->paginate(request()->input('per_page', 10));

            return sendResponse("Customer contact history retrieved successfully.", new ContactHistoryResource($contactHistories));

        } catch (QueryException $e) {
            return sendResponse("Error Occurred.", [], [$e->getMessage()], 422);
        }
    }

    /**
     * @OA\Get(
     *     path="/contact-history/follow-ups",
     *     summary="Get follow-ups",
     *     description="Retrieves a list of contact histories requiring follow-up.",
     *     tags={"Other"},
     *     @OA\Response(
     *         response=200,
     *         description="Follow-ups retrieved successfully."
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error occurred."
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function getFollowUps()
    {
        try {
            $followUps = ContactHistory::needingFollowUp()
                ->with(['handler:id,name', 'relatedTicket', 'relatedChatSession'])
                ->orderBy('follow_up_date', 'asc')
                ->paginate(request()->input('per_page', 10));

            return sendResponse("Follow-ups retrieved successfully.", new ContactHistoryResource($followUps));

        } catch (QueryException $e) {
            return sendResponse("Error Occurred.", [], [$e->getMessage()], 422);
        }
    }

    /**
     * @OA\Post(
     *     path="/contact-history/export",
     *     summary="Export contact histories",
     *     description="Exports contact histories in CSV or PDF format.",
     *     tags={"Other"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="format", type="string", description="Export format (csv or pdf) (required)"),
     *             @OA\Property(property="columns", type="string", description="Columns to export (comma-separated)")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Contact histories exported successfully."
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Invalid format specified or error occurred."
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function export(Request $request)
    {
        $format = $request->input('format');
        if (!in_array($format, ['csv', 'pdf'])) {
            return sendResponse('Invalid format specified', [], false, null, 422);
        }

        $availableColumns = [
            'id',
            'contact_id',
            'customer_name',
            'customer_email',
            'customer_phone',
            'interaction_type',
            'method',
            'channel',
            'summary',
            'details',
            'status',
            'handler.name',
            'contacted_at',
            'outcome',
            'follow_up_required',
            'follow_up_date',
            'created_at',
            'updated_at'
        ];

        $selectedColumns = $request->input('columns', $availableColumns);

        // Convert string input to array
        if (is_string($selectedColumns)) {
            $selectedColumns = explode(',', $selectedColumns);
        }

        // Ensure only valid columns are selected
        $columns = array_intersect($availableColumns, $selectedColumns);

        // Handle relationships
        $relationships = [];
        foreach ($availableColumns as $column) {
            if (strpos($column, '.') !== false) {
                list($relation) = explode('.', $column);
                $relationships[] = $relation;
            }
        }
        $relationships = array_unique($relationships);

        // Query construction
        $query = ContactHistory::query();
        if (!empty($relationships)) {
            $query->with($relationships);
        }

        // Date filtering
        if ($request->has(['from_date', 'to_date'])) {
            $query->whereBetween('contacted_at', [
                Carbon::parse($request->from_date)->startOfDay(),
                Carbon::parse($request->to_date)->endOfDay()
            ]);
        }

        // Interaction type filtering
        if ($request->has('interaction_type')) {
            $query->where('interaction_type', $request->interaction_type);
        }

        // Method filtering
        if ($request->has('method')) {
            $query->where('method', $request->method);
        }

        // Status filtering
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $contactHistories = $query->get();

        // Handle PDF export
        if ($format === 'pdf') {
            $html = view('exports.general_export', [
                'name' => "Contact History",
                'rows' => $contactHistories,
                'columns' => $columns,
                'date' => now()->format('Y-m-d H:i:s')
            ])->render();

            return response()->json(['html' => $html]);
        }

        // Handle CSV export
        return response()->json([
            'data' => $contactHistories->map(function ($item) use ($columns) {
                $row = [];
                foreach ($columns as $column) {
                    if (strpos($column, '.') !== false) {
                        list($relation, $field) = explode('.', $column);
                        $row[$column] = $item->$relation ? $item->$relation->$field : '';
                    } else {
                        $row[$column] = $item->$column;
                    }
                }
                return $row;
            }),
            'filename' => 'contact_history_' . date('Y-m-d') . '.csv'
        ]);
    }

    /**
     * @OA\Get(
     *     path="/contact-history/stats",
     *     summary="Get contact history statistics",
     *     description="Retrieves various statistics related to contact history.",
     *     tags={"Other"},
     *     @OA\Response(
     *         response=200,
     *         description="Contact history stats retrieved successfully."
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function getStats(Request $request)
    {
        $stats = [
            'total_interactions' => ContactHistory::count(),
            'chat_interactions' => ContactHistory::byType('CHAT')->count(),
            'ticket_interactions' => ContactHistory::byType('TICKET')->count(),
            'email_interactions' => ContactHistory::byMethod('EMAIL')->count(),
            'phone_interactions' => ContactHistory::byMethod('PHONE')->count(),
            'pending_follow_ups' => ContactHistory::needingFollowUp()->count(),
            'today_interactions' => ContactHistory::whereDate('contacted_at', today())->count(),
            'this_week_interactions' => ContactHistory::whereBetween('contacted_at', [
                now()->startOfWeek(),
                now()->endOfWeek()
            ])->count(),
            'this_month_interactions' => ContactHistory::whereMonth('contacted_at', now()->month)
                ->whereYear('contacted_at', now()->year)
                ->count(),
        ];

        return sendResponse("Contact history stats retrieved successfully.", $stats);
    }
}
