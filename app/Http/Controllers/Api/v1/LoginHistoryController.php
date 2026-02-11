<?php

namespace App\Http\Controllers\Api\v1;
use App\Http\Controllers\Controller;


use App\Http\Resources\GeneralResource;
use App\Models\LoginHistory;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Exports\GeneralExport;
use Maatwebsite\Excel\Facades\Excel;

/**
 * @OA\Tag(name="Other", description="API endpoints for managing driver login history")
 */
class LoginHistoryController extends Controller
{
    /**
     * @OA\Get(
     *     path="/login_histories",
     *     summary="Get login history",
     *     description="Retrieve a list of login histories with pagination and filtering options.",
     *     tags={"Other"},
     *     @OA\Parameter(
     *         name="from",
     *         in="query",
     *         description="Filter by start date",
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="to",
     *         in="query",
     *         description="Filter by end date",
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filter by status (success or failed)",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="user",
     *         in="query",
     *         description="Filter by user ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search by email",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function index(Request $request)
    {
        $query = LoginHistory::query();
        $perPage = request()->query('per_page', 8);
        if ($request->from) {
            $query->where('created_at', '>=', Carbon::parse($request->from));
        }
        if ($request->to) {
            $query->where('created_at', '<=', Carbon::parse($request->to));
        }
        if ($request->status) {
            $query->where('status', $request->status == 'success' ? 1 : ($request->status == 'failed' ? 0 : ''));
        }
        if ($request->user) {
            $query->where('user_id', $request->user);
        }
        if ($request->search) {
            $query->where('email', 'like', "%{$request->search}%");
        }
        $histories = $query->with(['user'])
            ->orderBy('id', 'desc')
            ->paginate($perPage);
        $stats = [];
        $stats['total_logins'] = LoginHistory::count();
        $stats['failed_logins'] = LoginHistory::where('status', false)->count();
        $stats['success_rate'] = LoginHistory::selectRaw(
            'IFNULL(ROUND(
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) 
                / COUNT(*) * 100
            , 2), 0) AS success_rate',
            [true]
        )->value('success_rate');
        return sendResponse("Login History.", new GeneralResource(['histories' => $histories, 'stats' => $stats]), []);
    }

    /**
     * @OA\Post(
     *     path="/login_histories/export",
     *     summary="Export login history",
     *     description="Export login history data in CSV or PDF format.",
     *     tags={"Other"},
     *     @OA\Parameter(
     *         name="format",
     *         in="query",
     *         description="Format of export (csv or pdf)",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="columns",
     *         in="query",
     *         description="Columns to include in export (comma-separated)",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="from_date",
     *         in="query",
     *         description="Filter by start date",
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="to_date",
     *         in="query",
     *         description="Filter by end date",
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filter by status (success or failed)",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="user",
     *         in="query",
     *         description="Filter by user ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search by email",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Invalid format specified"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized"
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
            'user.name',
            'email',
            'status',
            'ip_address',
            'user_agent',
            'created_at',
            'updated_at'
        ];
        $selectedColumns = $request->input('columns', $availableColumns);
        if (is_string($selectedColumns)) {
            $selectedColumns = explode(',', $selectedColumns);
        }
        $columns = array_intersect($availableColumns, $selectedColumns);
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
        $query = LoginHistory::query();
        if (!empty($relationships)) {
            $query->with($relationships);
        }
        if ($request->has(['from_date', 'to_date'])) {
            $query->whereBetween('created_at', [
                Carbon::parse($request->from_date)->startOfDay(),
                Carbon::parse($request->to_date)->endOfDay()
            ]);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status == 'success' ? 1 : ($request->status == 'failed' ? 0 : ''));
        }
        if ($request->filled('user')) {
            $query->where('user_id', $request->user);
        }
        if ($request->filled('search')) {
            $query->where('email', 'like', "%{$request->search}%");
        }
        $histories = $query->get();
        $histories->transform(function ($history) {
            $history->status = $history->status ? 'Success' : 'Failed';
            return $history;
        });
        if ($format === 'pdf') {
            $html = view('exports.general_export', [
                'name' => "Login History",
                'rows' => $histories,
                'columns' => $columns,
                'date' => now()->format('Y-m-d H:i:s')
            ])->render();
            return response()->json(['html' => $html]);
        }
        $name = 'login_history.' . $format;
        return Excel::download(new GeneralExport($histories, $columns), $name);
    }
}
