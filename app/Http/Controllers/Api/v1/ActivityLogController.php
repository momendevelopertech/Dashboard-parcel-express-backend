<?php

namespace App\Http\Controllers\Api\v1;
use App\Http\Controllers\Controller;


use App\Http\Resources\ActivityLogResource;
use App\Models\ActivityLog;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Exports\GeneralExport;
use Maatwebsite\Excel\Facades\Excel;

/**
 * @OA\Tag(name="Other", description="Activity Log management")
 * @OA\Server(url="/api")
 */
class ActivityLogController extends Controller
{
    /**
     * @OA\Get(
     *     path="/activity_logs",
     *     summary="Get a list of activity logs",
     *     tags={"Other"},
     *     @OA\Parameter(
     *         name="from",
     *         in="query",
     *         description="Start date for filtering (YYYY-MM-DD)",
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="to",
     *         in="query",
     *         description="End date for filtering (YYYY-MM-DD)",
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="user",
     *         in="query",
     *         description="User ID for filtering",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="action",
     *         in="query",
     *         description="Action for filtering",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search term for filtering",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Activity logs retrieved successfully",
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent()
     *     ),
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function index(Request $request)
    {
        $query = ActivityLog::query();
        $perPage = request()->query('per_page', 8);

        if ($request->from) {
            $query->where('created_at', '>=', Carbon::parse($request->from));
        }
        if ($request->to) {
            $query->where('created_at', '<=', Carbon::parse($request->to));
        }

        if ($request->user) {
            $query->where('user_id', $request->user);
        }

        if ($request->action) {
            $query->where('action', $request->action);
        }

        if ($request->search) {
            $query->where('description', 'like', "%{$request->search}%");
        }

        $activity_logs = $query->with(['user'])
            ->orderBy('id', 'desc')
            ->paginate($perPage);

        return sendResponse("Activity Logs reterived successfully.", new ActivityLogResource(resource: $activity_logs), []);
    }

    /**
     * @OA\Post(
     *     path="/activity_logs/edit/{id}",
     *     summary="Get a specific activity log",
     *     tags={"Other"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of the activity log",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Activity log retrieved successfully",
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Activity log not found",
     *         @OA\JsonContent()
     *     ),
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function edit($id)
    {
        $activity_log = ActivityLog::find($id);
        return sendResponse("Activity Log", $activity_log);
    }

    /**
     * @OA\Get(
     *     path="/activity_logs/all",
     *     summary="Get all activity logs",
     *     tags={"Other"},
     *     @OA\Response(
     *         response=200,
     *         description="Activity logs retrieved successfully",
     *     ),
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function all()
    {
        return sendResponse("Activity Logs", new ActivityLogResource(ActivityLog::with('user')->get()));
    }

    /**
     * @OA\Post(
     *     path="/activity_logs/export",
     *     summary="Export activity logs",
     *     tags={"Other"},
     *     @OA\Parameter(
     *         name="format",
     *         in="query",
     *         description="Export format (csv, pdf)",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="columns",
     *         in="query",
     *         description="Columns to export (comma-separated)",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="from_date",
     *         in="query",
     *         description="Start date for filtering (YYYY-MM-DD)",
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="to_date",
     *         in="query",
     *         description="End date for filtering (YYYY-MM-DD)",
     *         @OA\Schema(type="string", format="date")
     *     ),
     *      @OA\Parameter(
     *         name="user",
     *         in="query",
     *         description="User ID for filtering",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="action",
     *         in="query",
     *         description="Action for filtering",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search term for filtering",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Activity logs exported successfully",
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Invalid format specified",
     *         @OA\JsonContent()
     *     ),
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function export(Request $request)
    {
        $format = $request->input('format');
        if (!in_array($format, ['csv', 'pdf'])) {
            return sendResponse('Invalid format specified', [], false, null, 422);
        }

        // Get columns to export
        $availableColumns = [
            'id',
            'user.name',
            'action',
            'description',
            'ip_address',
            'user_agent',
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
                $parts = explode('.', $column);
                array_pop($parts);
                if (!empty($parts)) {
                    $relationships[] = implode('.', $parts);
                }
            }
        }

        $query = ActivityLog::query();
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

        // User filtering
        if ($request->filled('user')) {
            $query->where('user_id', $request->user);
        }

        // Action filtering
        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }

        // Search filtering
        if ($request->filled('search')) {
            $query->where('description', 'like', "%{$request->search}%");
        }

        $logs = $query->get();

        // Transform action to be more readable
        $logs->transform(function ($log) {
            $log->action = str_replace('_', ' ', ucfirst($log->action));
            return $log;
        });

        if ($format === 'pdf') {
            $html = view('exports.general_export', [
                'name' => "Activity Logs",
                'rows' => $logs,
                'columns' => $columns,
                'date' => now()->format('Y-m-d H:i:s')
            ])->render();
            return response()->json(['html' => $html]);
        }

        $name = 'activity_logs.' . $format;
        return Excel::download(new GeneralExport($logs, $columns), $name);
    }
}
