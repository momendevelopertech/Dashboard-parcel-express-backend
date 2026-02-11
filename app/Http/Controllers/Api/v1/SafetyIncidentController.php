<?php

namespace App\Http\Controllers\Api\v1;
use App\Http\Controllers\Controller;


use App\Exports\SafetyIncidentExport;
use App\Http\Requests\StoreSafetyIncidentRequest;
use App\Http\Requests\UpdateSafetyIncidentRequest;
use App\Http\Resources\SafetyIncidentResource;
use App\Models\SafetyIncident;
use App\Models\SafetyIncidentAttachment;
use App\Traits\Searchable;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

/**
 * @OA\Tag(
 *     name="Other",
 *     description="Safety Incident Management"
 * )
 */
class SafetyIncidentController extends Controller
{
    use Searchable;

    protected function modelQuery()
    {
        return SafetyIncident::query()->select('id', 'occurred_at', 'location', 'description', 'severity', 'status', 'reported_by', 'assigned_to', 'investigation_notes', 'created_at', 'updated_at');
    }

    /**
     * @OA\Get(
     *     path="/safety-incidents",
     *     summary="Get a list of safety incidents",
     *     tags={"Other"},
     *     @OA\Response(
     *         response=200,
     *         description="Safety incidents retrieved successfully."
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
        $incidents = $this->handleSearch(
            searchColumns: ['location', 'description', 'severity', 'status'],
            withRelationships: [
                'reportedBy:id,name',
                'assignedTo:id,name',
                'attachments:id,safety_incident_id,file_path,original_name'
            ],
            perPage: request()->input('per_page', 10),
            shipmentColumn: 'created_at',
            shipmentDirection: 'desc'
        );
        return sendResponse("Safety incidents retrieved successfully.", new SafetyIncidentResource($incidents));
    }

    /**
     * @OA\Post(
     *     path="/safety-incidents/store",
     *     summary="Create a new safety incident",
     *     tags={"Other"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="occurred_at", type="date", description="Occurred at (required)", example="2024-07-26"),
     *             @OA\Property(property="location", type="string", description="Location (required)", example="Building A"),
     *             @OA\Property(property="description", type="string", description="Description (required)", example="Accident near the entrance"),
     *             @OA\Property(property="severity", type="string", description="Severity (required, in: Low,Medium,High)", example="High"),
     *             @OA\Property(property="status", type="string", description="Status (nullable, in: Open,Under Investigation,Resolved)", example="Open"),
     *             @OA\Property(property="reported_by", type="integer", description="Reported by (nullable, exists:users,id)"),
     *             @OA\Property(property="assigned_to", type="integer", description="Assigned to (nullable, exists:users,id)"),
     *             @OA\Property(property="investigation_notes", type="string", description="Investigation notes (nullable)"),
     *             @OA\Property(property="attachments", type="array", description="Attachments (nullable, array of files)", @OA\Items(type="string", format="binary"))
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Safety incident created successfully."
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error occurred."
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     * @param StoreSafetyIncidentRequest $request
     */
    public function store(StoreSafetyIncidentRequest $request)
    {
        $request->validated();
        try {
            // Create the incident
            $incident = SafetyIncident::create($request->except('attachments'));

            // Handle file attachments
            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    $filePath = uploadFile($file, 'public/safety_incidents');
                    if ($filePath) {
                        SafetyIncidentAttachment::create([
                            'safety_incident_id' => $incident->id,
                            'file_path' => $filePath,
                            'original_name' => $file->getMerchantOriginalName()
                        ]);
                    }
                }
            }

            $incident->load(['reportedBy:id,name', 'assignedTo:id,name', 'attachments']);
        } catch (QueryException $e) {
            return sendResponse("Error occurred.", [], [$e->getMessage()], 422);
        }
        return sendResponse("Safety incident created successfully.", new SafetyIncidentResource($incident));
    }

    /**
     * @OA\Get(
     *     path="/safety-incidents/show/{id}",
     *     summary="Get a safety incident by ID",
     *     tags={"Other"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of the safety incident",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Safety incident retrieved successfully."
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Safety incident not found."
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error occurred."
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function show($id)
    {
        try {
            $incident = SafetyIncident::with(['reportedBy:id,name', 'assignedTo:id,name', 'attachments'])->find($id);
            if (!$incident) {
                return sendResponse("Safety incident not found.", [], ["Incident not found"], 404);
            }
        } catch (QueryException $e) {
            return sendResponse("Error occurred.", [], [$e->getMessage()], 422);
        }
        return sendResponse("Safety incident retrieved successfully.", new SafetyIncidentResource($incident));
    }

    /**
     * @OA\Post(
     *     path="/safety-incidents/update",
     *     summary="Update a safety incident",
     *     tags={"Other"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", description="ID of the safety incident (required)"),
     *             @OA\Property(property="occurred_at", type="date", description="Occurred at (required)"),
     *             @OA\Property(property="location", type="string", description="Location (required)"),
     *             @OA\Property(property="description", type="string", description="Description (required)"),
     *             @OA\Property(property="severity", type="string", description="Severity (required, in: Low,Medium,High)"),
     *             @OA\Property(property="status", type="string", description="Status (required, in: Open,Under Investigation,Resolved)"),
     *             @OA\Property(property="assigned_to", type="integer", description="Assigned to (nullable, exists:users,id)"),
     *             @OA\Property(property="investigation_notes", type="string", description="Investigation notes (nullable)"),
     *             @OA\Property(property="attachments", type="array", description="Attachments (nullable, array of files)", @OA\Items(type="string", format="binary"))
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Safety incident updated successfully."
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Safety incident not found."
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error occurred."
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     * @param UpdateSafetyIncidentRequest $request
     */
    public function update(UpdateSafetyIncidentRequest $request)
    {
        $request->validated();
        try {
            $incident = SafetyIncident::find($request->id);
            if (!$incident) {
                return sendResponse("Safety incident not found.", [], ["Incident not found"], 404);
            }

            $incident->update($request->except('attachments'));

            // Handle new file attachments
            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    $filePath = uploadFile($file, 'public/safety_incidents');
                    if ($filePath) {
                        SafetyIncidentAttachment::create([
                            'safety_incident_id' => $incident->id,
                            'file_path' => $filePath,
                            'original_name' => $file->getMerchantOriginalName()
                        ]);
                    }
                }
            }

            $incident->load(['reportedBy:id,name', 'assignedTo:id,name', 'attachments']);
        } catch (QueryException $e) {
            return sendResponse("Error occurred.", [], [$e->getMessage()], 422);
        }
        return sendResponse("Safety incident updated successfully.", new SafetyIncidentResource($incident));
    }

    /**
     * @OA\Post(
     *     path="/safety-incidents/delete",
     *     summary="Delete a safety incident",
     *     tags={"Other"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", description="ID of the safety incident (required)")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Safety incident deleted successfully."
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Safety incident not found."
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
            $incident = SafetyIncident::find($request->id);
            if (!$incident) {
                return sendResponse("Safety incident not found.", [], ["Incident not found"], 404);
            }

            // Delete associated files
            foreach ($incident->attachments as $attachment) {
                if (file_exists(public_path($attachment->file_path))) {
                    unlink(public_path($attachment->file_path));
                }
            }

            $incident->delete();
        } catch (QueryException $e) {
            return sendResponse("Error occurred.", [], [$e->getMessage()], 422);
        }
        return sendResponse("Safety incident deleted successfully.", []);
    }

    /**
     * @OA\Get(
     *     path="/safety-incidents/all",
     *     summary="Get all safety incidents",
     *     tags={"Other"},
     *     @OA\Response(
     *         response=200,
     *         description="Safety incidents"
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function all()
    {
        return sendResponse("Safety incidents", new SafetyIncidentResource(SafetyIncident::with(['reportedBy:id,name', 'assignedTo:id,name'])->get()));
    }

    /**
     * @OA\Get(
     *     path="/safety-incidents/edit/{id}",
     *     summary="Get a safety incident by ID for editing",
     *     tags={"Other"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of the safety incident",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Safety incident"
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function edit($id)
    {
        $incident = SafetyIncident::with(['reportedBy:id,name', 'assignedTo:id,name', 'attachments'])->find($id);
        return sendResponse("Safety incident", $incident);
    }

    /**
     * @OA\Post(
     *     path="/safety-incidents/export",
     *     summary="Export safety incidents",
     *     tags={"Other"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="format", type="string", description="Export format (csv, pdf) (required)"),
     *             @OA\Property(property="columns", type="string", description="Columns to export (optional, comma-separated)")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Safety incidents exported successfully."
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Invalid format specified."
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function export(Request $request)
    {
        $format = $request->input('format');
        if (!in_array($format, ['csv', 'pdf'])) {
            return sendResponse('Invalid format specified', [], ["Invalid format"], 422);
        }

        $availableColumns = [
            'id',
            'occurred_at',
            'location',
            'description',
            'severity',
            'status',
            'reportedBy.name',
            'assignedTo.name',
            'investigation_notes',
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
        $query = SafetyIncident::query();
        if (!empty($relationships)) {
            $query->with($relationships);
        }

        // Date filtering
        if ($request->has(['from_date', 'to_date'])) {
            $query->whereBetween('created_at', [
                Carbon::parse($request->from_date)->startOfDay(),
                Carbon::parse($request->to_date)->endOfDay()
            ]);
        }

        // Status filtering
        if ($request->has('status') && $request->status != '') {
            $query->where('status', $request->status);
        }

        // Severity filtering
        if ($request->has('severity') && $request->severity != '') {
            $query->where('severity', $request->severity);
        }

        $incidents = $query->get();

        // Handle PDF export
        if ($format === 'pdf') {
            $html = view('exports.general_export', [
                'name' => "Safety Incidents",
                'rows' => $incidents,
                'columns' => $columns,
                'date' => now()->format('Y-m-d H:i:s')
            ])->render();
            return response()->json(['html' => $html]);
        }

        // Handle CSV export (you'll need to create SafetyIncidentExport class)
        $name = 'safety_incidents.' . $format;
        return Excel::download(new SafetyIncidentExport($incidents, $columns), $name);
    }
}
