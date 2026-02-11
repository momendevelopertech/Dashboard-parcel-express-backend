<?php

namespace App\Http\Controllers\Api\v1;


use App\Http\Controllers\Controller;
use App\Http\Requests\StoreComplianceChecklistRequest;
use App\Http\Requests\UpdateComplianceChecklistRequest;
use App\Http\Resources\ComplianceChecklistResource;
use App\Models\ComplianceChecklist;
use App\Models\ChecklistItem;
use App\Traits\Searchable;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * @OA\Tag(name="Other", description="Compliance Checklist Controller")
 * @OA\Controller(description="Manage compliance checklists.")
 */
class ComplianceChecklistController extends Controller
{
    use Searchable;

    protected function modelQuery()
    {
        $query = ComplianceChecklist::query()->select('id', 'name', 'category', 'last_completed', 'status');
        
        // Apply category filter
        if (request()->has('category') && request()->category) {
            $query->where('category', request()->category);
        }
        
        // Apply status filter
        if (request()->has('status') && request()->status) {
            $query->where('status', request()->status);
        }
        
        return $query;
    }

    /**
     * @OA\Get(
     *     path="/compliance-checklists",
     *     summary="Get a list of compliance checklists.",
     *     tags={"Other"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Compliance Checklists retrieved successfully."
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error Occurred."
     *     )
     * )
     */
    public function index()
    {
        $checklists = $this->handleSearch(
            searchColumns: ['name', 'category'],
            withRelationships: ['items'],
            perPage: request()->input('per_page', 15),
            shipmentColumn: 'created_at',
            shipmentDirection: 'desc'
        );
        return sendResponse("Compliance Checklists retrieved successfully.", new ComplianceChecklistResource($checklists), []);
    }

    /**
     * @OA\Post(
     *     path="/compliance-checklists/store",
     *     summary="Create a new compliance checklist.",
     *     tags={"Other"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", description="Name of the checklist", example="Checklist 1"),
     *             @OA\Property(property="category", type="string", description="Category of the checklist", example="Category A"),
     *             @OA\Property(property="items", type="array", description="Array of checklist items", @OA\Items(
     *                 @OA\Property(property="description", type="string", description="Description of the checklist item", example="Item 1")
     *             ))
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Compliance Checklist created successfully."
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error Occurred.",
     *     ),
     *     @OA\Parameter(
     *          name="name",
     *          in="query",
     *          description="Name of the checklist (required, string, max 255)",
     *          required=true,
     *          @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *          name="category",
     *          in="query",
     *          description="Category of the checklist (required, string, max 255)",
     *          required=true,
     *          @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *          name="items",
     *          in="query",
     *          description="Array of checklist items (required, array, min 1)",
     *          required=true,
     *          @OA\Schema(type="array", @OA\Items(type="object", @OA\Property(property="description", type="string", description="Description of the checklist item (required, string, max 500)")))
     *     )
     * )
     */
    public function store(StoreComplianceChecklistRequest $request)
    {
        $request->validated();
        try {
            $checklist = ComplianceChecklist::create([
                'name' => $request->name,
                'category' => $request->category,
            ]);

            // Create checklist items
            foreach ($request->items as $item) {
                $checklist->items()->create($item);
            }

            $checklist->load('items');
        } catch (QueryException $e) {
            return sendResponse("Error Occurred.", [], [$e->getMessage()], 422);
        }
        return sendResponse("Compliance Checklist created successfully.", new ComplianceChecklistResource($checklist));
    }

    /**
     * @OA\Get(
     *     path="/compliance-checklists/show/{id}",
     *     summary="Get a specific compliance checklist.",
     *     tags={"Other"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of the checklist",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Compliance Checklist retrieved successfully."
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error Occurred."
     *     ),
     *     @OA\Response(response=404, description="Checklist not found")
     * )
     */
    public function show($id)
    {
        try {
            $checklist = ComplianceChecklist::with('items')->findOrFail($id);
            return sendResponse("Compliance Checklist retrieved successfully.", new ComplianceChecklistResource($checklist));
        } catch (QueryException $e) {
            return sendResponse("Error Occurred.", [], [$e->getMessage()], 422);
        }
    }

    /**
     * @OA\Post(
     *     path="/compliance-checklists/update",
     *     summary="Update a compliance checklist.",
     *     tags={"Other"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", description="ID of the checklist (required)"),
     *             @OA\Property(property="name", type="string", description="Name of the checklist (string, max 255)"),
     *             @OA\Property(property="category", type="string", description="Category of the checklist (string, max 255)"),
     *             @OA\Property(property="last_completed", type="string", format="date", description="Last completed date (date)"),
     *             @OA\Property(property="status", type="string", description="Status of the checklist (Compliant, Non-Compliant)")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Compliance Checklist updated successfully."
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error Occurred."
     *     ),
     *     @OA\Parameter(
     *          name="id",
     *          in="query",
     *          description="ID of the checklist (required)",
     *          required=true,
     *          @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *          name="name",
     *          in="query",
     *          description="Name of the checklist (string, max 255)",
     *          @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *          name="category",
     *          in="query",
     *          description="Category of the checklist (string, max 255)",
     *          @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *          name="last_completed",
     *          in="query",
     *          description="Last completed date (date)",
     *          @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *          name="status",
     *          in="query",
     *          description="Status of the checklist (Compliant, Non-Compliant)",
     *          @OA\Schema(type="string")
     *     )
     * )
     */
    public function update(UpdateComplianceChecklistRequest $request)
    {
        $request->validated();
        try {
            $checklist = ComplianceChecklist::findOrFail($request->id);
            $checklist->update($request->all());
            $checklist->load('items');
        } catch (QueryException $e) {
            return sendResponse("Error Occurred.", [], [$e->getMessage()], 422);
        }
        return sendResponse("Compliance Checklist updated successfully.", new ComplianceChecklistResource($checklist));
    }

    /**
     * @OA\Post(
     *     path="/compliance-checklists/delete",
     *     summary="Delete a compliance checklist.",
     *     tags={"Other"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", description="ID of the checklist to delete")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Compliance Checklist deleted successfully."
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error Occurred."
     *     ),
     *     @OA\Parameter(
     *          name="id",
     *          in="query",
     *          description="ID of the checklist to delete",
     *          required=true,
     *          @OA\Schema(type="integer")
     *     )
     * )
     */
    public function delete(Request $request)
    {
        try {
            ComplianceChecklist::where('id', $request->id)->delete();
        } catch (QueryException $e) {
            return sendResponse("Error Occurred.", [], [$e->getMessage()], 422);
        }
        return sendResponse("Compliance Checklist deleted successfully.", []);
    }

    /**
     * @OA\Get(
     *     path="/compliance-checklists/edit/{id}",
     *     summary="Get a specific compliance checklist for editing.",
     *     tags={"Other"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of the checklist",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Compliance Checklist"
     *     ),
     *      @OA\Response(response=404, description="Checklist not found")
     * )
     */
    public function edit($id)
    {
        $checklist = ComplianceChecklist::with('items')->findOrFail($id);
        return sendResponse("Compliance Checklist", new ComplianceChecklistResource($checklist));
    }

    /**
     * @OA\Get(
     *     path="/compliance-checklists/all",
     *     summary="Get all compliance checklists.",
     *     tags={"Other"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Compliance Checklists"
     *     )
     * )
     */
    public function all()
    {
        return sendResponse("Compliance Checklists", new ComplianceChecklistResource(ComplianceChecklist::with('items')->get()));
    }

    /**
     * @OA\Post(
     *     path="/compliance-checklists/{checklistId}/items/{itemId}/mark",
     *     summary="Mark a checklist item as completed or not completed.",
     *     tags={"Other"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="checklistId",
     *         in="path",
     *         description="ID of the checklist",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="itemId",
     *         in="path",
     *         description="ID of the item",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="is_completed", type="boolean", description="Whether the item is completed (required)")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Checklist item updated successfully."
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error Occurred."
     *     ),
     *     @OA\Parameter(
     *          name="is_completed",
     *          in="query",
     *          description="Whether the item is completed (required, boolean)",
     *          required=true,
     *          @OA\Schema(type="boolean")
     *     )
     * )
     */
    public function markItem(Request $request, $checklistId, $itemId)
    {
        $request->validate([
            'is_completed' => 'required|boolean',
        ]);

        try {
            $checklist = ComplianceChecklist::with('items')->findOrFail($checklistId);
            $item = $checklist->items()->findOrFail($itemId);
            
            $item->update(['is_completed' => $request->is_completed]);

            // Update checklist status and last_completed
            $allCompleted = $checklist->items()->where('is_completed', false)->count() === 0;
            $checklist->update([
                'status' => $allCompleted ? 'Compliant' : 'Non-Compliant',
                'last_completed' => $allCompleted ? now() : $checklist->last_completed,
            ]);

            $checklist->refresh();
            $checklist->load('items');

            return sendResponse("Checklist item updated successfully.", [
                'checklist' => new ComplianceChecklistResource($checklist),
                'item' => $item
            ]);
        } catch (QueryException $e) {
            return sendResponse("Error Occurred.", [], [$e->getMessage()], 422);
        }
    }

    /**
     * @OA\Post(
     *     path="/compliance-checklists/export",
     *     summary="Export compliance checklists.",
     *     tags={"Other"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="format",
     *         in="query",
     *         description="Format of the export (csv, pdf)",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="columns",
     *         in="query",
     *         description="Columns to include in the export (id,name,category,last_completed,status,created_at,updated_at)",
     *         @OA\Schema(type="array", @OA\Items(type="string"))
     *     ),
     *     @OA\Parameter(
     *         name="from_date",
     *         in="query",
     *         description="Start date for filtering",
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="to_date",
     *         in="query",
     *         description="End date for filtering",
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Compliance Checklists exported successfully."
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Invalid format specified or Error Occurred."
     *     )
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
            'name',
            'category',
            'last_completed',
            'status',
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

        // Query construction
        $query = ComplianceChecklist::query();

        // Date filtering
        if ($request->has(['from_date', 'to_date'])) {
            $query->whereBetween('created_at', [
                Carbon::parse($request->from_date)->startOfDay(),
                Carbon::parse($request->to_date)->endOfDay()
            ]);
        }

        if ($format === 'csv') {
            return $this->exportCsv($query, $columns);
        }

        // Handle PDF export
        if ($format === 'pdf') {
            $checklists = $query->get();
            $html = view('exports.general_export', [
                'name' => "Compliance Checklists",
                'rows' => $checklists,
                'columns' => $columns,
                'date' => now()->format('Y-m-d H:i:s')
            ])->render();

            return response()->json(['html' => $html]);
        }
    }

    private function exportCsv($query, $columns): StreamedResponse
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename=\"compliance_checklists.csv\"',
        ];

        $callback = function() use ($query, $columns) {
            $handle = fopen('php://output', 'w');
            
            // Write header row
            $headerLabels = [];
            foreach ($columns as $column) {
                $headerLabels[] = ucfirst(str_replace('_', ' ', $column));
            }
            fputcsv($handle, $headerLabels);

            // Write data rows
            $query->chunk(100, function($checklists) use ($handle, $columns) {
                foreach ($checklists as $checklist) {
                    $row = [];
                    foreach ($columns as $column) {
                        $row[] = $checklist->{$column};
                    }
                    fputcsv($handle, $row);
                }
            });

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }
}