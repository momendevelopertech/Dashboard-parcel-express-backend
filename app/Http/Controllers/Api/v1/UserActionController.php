<?php

namespace App\Http\Controllers\Api\v1;
use App\Http\Controllers\Controller;


use App\Models\UserAction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Http\Resources\UserActionResource;
use Illuminate\Support\Facades\Auth;
use App\Exports\GeneralExport;
use Maatwebsite\Excel\Facades\Excel;

/**
 * @OA\Tag(
 *     name="Other",
 *     description="User Action Management"
 * )
 * @OA\Server(url="http://localhost")
 */
class UserActionController extends Controller
{
    /**
     * @OA\Get(
     *     path="/user_actions",
     *     summary="Get a list of user actions",
     *     tags={"Other"},
     *     @OA\Parameter(
     *         name="from",
     *         in="query",
     *         description="Filter from date",
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="to",
     *         in="query",
     *         description="Filter to date",
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="user",
     *         in="query",
     *         description="Filter by user ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="action",
     *         in="query",
     *         description="Filter by action type",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search query",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="is_suspicious",
     *         in="query",
     *         description="Filter by suspicious actions",
     *         @OA\Schema(type="boolean")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="User Actions retrieved successfully."
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function index(Request $request)
    {
        $perPage = request()->query('per_page', 8);
        $query = UserAction::query();

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
            $query->where('action_type', $request->action);
        }

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('details', 'like', "%{$request->search}%")
                    ->orWhere('entity_type', 'like', "%{$request->search}%")
                    ->orWhere('device', 'like', "%{$request->search}%")
                    ->orWhere('ip_address', 'like', "%{$request->search}%");
            });
        }

        $is_suspicious = $request->is_suspicious == "true" ? 1 : 0;

        if ($is_suspicious) {
            $query->where('is_suspicious', true);
        }

        $user_actions = $query->with(['user'])
            ->orderBy('id', 'desc')
            ->paginate($perPage);

        return sendResponse("User Actions retrieved successfully.", new UserActionResource(resource: $user_actions), []);
    }

    /**
     * @OA\Get(
     *     path="/user_actions/show/{id}",
     *     summary="Get a single user action",
     *     tags={"Other"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of the user action",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="User Action"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="User Action not found"
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function show($id)
    {
        $user_action = UserAction::with('user')->find($id);
        return sendResponse("User Action", new UserActionResource($user_action));
    }

    /**
     * @OA\Get(
     *     path="/user_actions/all",
     *     summary="Get all user actions",
     *     tags={"Other"},
     *     @OA\Response(
     *         response=200,
     *         description="User Actions"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function all()
    {
        return sendResponse("User Actions", new UserActionResource(UserAction::with('user')->get()));
    }

    protected static function isSuspiciousAction($actionType, $userId, $ipAddress): bool
    {
        $recentActionsCount = UserAction::where('action_type', $actionType)
            ->where('user_id', $userId)
            ->where('ip_address', $ipAddress)
            ->where('created_at', '>=', now()->subMinutes(5))
            ->count();

        if ($recentActionsCount > 10) {
            return true;
        }

        $rapidFireActionsCount = UserAction::where('ip_address', $ipAddress)
            ->where('created_at', '>=', now()->subMinute())
            ->count();

        if ($rapidFireActionsCount > 30) {
            return true;
        }

        return false;
    }

    /**
     * @OA\Post(
     *     path="/user_actions/export",
     *     summary="Export user actions",
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
     *      @OA\Parameter(
     *         name="from_date",
     *         in="query",
     *         description="Filter from date",
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="to_date",
     *         in="query",
     *         description="Filter to date",
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="user",
     *         in="query",
     *         description="Filter by user ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="action",
     *         in="query",
     *         description="Filter by action type",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search query",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="is_suspicious",
     *         in="query",
     *         description="Filter by suspicious actions",
     *         @OA\Schema(type="boolean")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="User actions exported successfully"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Invalid format specified"
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

        // Get columns to export
        $availableColumns = [
            'id',
            'user.name',
            'action_type',
            'details',
            'entity_type',
            'entity_id',
            'ip_address',
            'device',
            'is_suspicious',
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

        $query = UserAction::query();
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

        // Action type filtering
        if ($request->filled('action')) {
            $query->where('action_type', $request->action);
        }

        // Search filtering
        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('details', 'like', "%{$request->search}%")
                    ->orWhere('entity_type', 'like', "%{$request->search}%")
                    ->orWhere('device', 'like', "%{$request->search}%")
                    ->orWhere('ip_address', 'like', "%{$request->search}%");
            });
        }

        // Suspicious actions filtering
        if ($request->filled('is_suspicious')) {
            $query->where('is_suspicious', boolval($request->is_suspicious));
        }

        $actions = $query->get();

        // Transform action_type to be more readable
        $actions->transform(function ($action) {
            $action->action_type = str_replace('_', ' ', ucfirst($action->action_type));
            $action->is_suspicious = $action->is_suspicious ? 'Yes' : 'No';
            return $action;
        });

        if ($format === 'pdf') {
            $html = view('exports.general_export', [
                'name' => "User Actions",
                'rows' => $actions,
                'columns' => $columns,
                'date' => now()->format('Y-m-d H:i:s')
            ])->render();
            return response()->json(['html' => $html]);
        }

        $name = 'user_actions.' . $format;
        return Excel::download(new GeneralExport($actions, $columns), $name);
    }
}
