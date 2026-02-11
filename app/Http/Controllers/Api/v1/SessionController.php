<?php

namespace App\Http\Controllers\Api\v1;
use App\Http\Controllers\Controller;


use ALajusticia\Logins\Models\Login;
use App\Exports\GeneralExport;
use App\Http\Resources\GeneralResource;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;
use Maatwebsite\Excel\Facades\Excel;

/**
 * @OA\Tag(name="Other", description="Manage user sessions")
 * @OA\Controller(description="Manage user sessions")
 */
class SessionController extends Controller
{
    /**
     * @OA\Get(
     *     path="/sessions",
     *     summary="Get a list of user sessions",
     *     description="Retrieve a paginated list of user sessions with filtering and search capabilities.",
     *     tags={"Other"},
     *     @OA\Parameter(
     *         name="from",
     *         in="query",
     *         description="Filter sessions from a specific date",
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="to",
     *         in="query",
     *         description="Filter sessions to a specific date",
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filter sessions by status (active or inactive)",
     *         @OA\Schema(type="string", enum={"active", "inactive"})
     *     ),
     *     @OA\Parameter(
     *         name="user",
     *         in="query",
     *         description="Filter sessions by user ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search for sessions by user name",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function index(Request $request)
    {
        $perPage = request()->query('per_page', 8);
        $request->validate([
            'status' => ['sometimes', 'in:active,inactive'],
        ]);
        $query = Login::query();
        if ($request->from) {
            $query->where('created_at', '>=', Carbon::parse($request->from));
        }
        if ($request->to) {
            $query->where('created_at', '<=', Carbon::parse($request->to));
        }
        if ($request->filled('status')) {
            switch ($request->status) {
                case 'active':
                    $query->whereExists(function ($query) {
                        $query->select(DB::raw(1))
                            ->from('personal_access_tokens')
                            ->whereRaw('personal_access_tokens.id = logins.personal_access_token_id')
                            ->whereNull('personal_access_tokens.expires_at')
                            ->orWhere('personal_access_tokens.expires_at', '>', now());
                    });
                    break;

                case 'inactive':
                    $query->where(function ($query) {
                        $query->whereNull('personal_access_token_id')
                            ->orWhereExists(function ($query) {
                                $query->select(DB::raw(1))
                                    ->from('personal_access_tokens')
                                    ->whereRaw('personal_access_tokens.id = logins.personal_access_token_id')
                                    ->where(function ($q) {
                                        $q->whereNotNull('personal_access_tokens.expires_at')
                                            ->where('personal_access_tokens.expires_at', '<=', now());
                                    });
                            });
                    });
                    break;
            }
        }
        if ($request->user) {
            $query->where('authenticatable_id', $request->user);
        }
        if ($request->search) {
            $search = $request->search;
            $query->whereHas('authenticatable', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%");
            });
        }

        $logins = $query->with(['authenticatable', 'personalAccessToken'])->orderBy('created_at', 'desc')->paginate($perPage);

        // Transform the data to include proper active status
        $logins->through(function ($login) {
            $token = $login->personalAccessToken;
            $login->is_active = $token &&
                ($token->expires_at === null || $token->expires_at > now());
            return $login;
        });

        return sendResponse("Sessions.", new GeneralResource($logins), []);
    }

    /**
     * @OA\Post(
     *     path="/sessions/destroy/{loginId}",
     *     summary="Terminate a user session",
     *     description="Terminate a specific user session.",
     *     tags={"Other"},
     *     @OA\Parameter(
     *         name="loginId",
     *         in="path",
     *         description="ID of the session to terminate",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Session doesn't exists or you cannot terminate your own current session."
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error"
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function destroy(Request $request, $loginId)
    {
        try {
            DB::beginTransaction();

            $login = Login::with(['authenticatable', 'personalAccessToken'])->find($loginId);

            if (!$login) {
                return sendResponse("Error.", [], false, ["Session doesn't exists"], Response::HTTP_BAD_REQUEST);
            }

            $currentLogin = $request->user()->current_login;
            if ($currentLogin && $currentLogin->id == $login->id) {
                return sendResponse("You cannot terminate your own current session.", [], false, [], Response::HTTP_BAD_REQUEST);
            }

            // Delete the token if it exists
            if ($login->personalAccessToken) {
                $login->personalAccessToken->delete();
            }

            // Update the login record
            $login->update([
                'personal_access_token_id' => null
            ]);

            DB::commit();
            return response()->json(['message' => 'Session terminated successfully.'], Response::HTTP_OK);
        } catch (\Exception $e) {
            DB::rollBack();
            return sendResponse("Error terminating session.", [], false, [$e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @OA\Post(
     *     path="/sessions/export",
     *     summary="Export user sessions",
     *     description="Export user sessions to CSV or PDF format.",
     *     tags={"Other"},
     *     @OA\Parameter(
     *         name="format",
     *         in="query",
     *         description="Format of the exported file (csv or pdf)",
     *         required=true,
     *         @OA\Schema(type="string", enum={"csv", "pdf"})
     *     ),
     *     @OA\Parameter(
     *         name="columns",
     *         in="query",
     *         description="Columns to include in the export (comma-separated)",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="from_date",
     *         in="query",
     *         description="Filter sessions from a specific date",
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="to_date",
     *         in="query",
     *         description="Filter sessions to a specific date",
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filter sessions by status (active or inactive)",
     *         @OA\Schema(type="string", enum={"active", "inactive"})
     *     ),
     *      @OA\Parameter(
     *         name="user",
     *         in="query",
     *         description="Filter sessions by user ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search for sessions by user name",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Invalid format specified or validation error"
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
            'authenticatable.name',
            'ip_address',
            'user_agent',
            'created_at',
            'updated_at',
            'is_active'
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

        $query = Login::query();
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
        if ($request->filled('status')) {
            switch ($request->status) {
                case 'active':
                    $query->whereExists(function ($query) {
                        $query->select(DB::raw(1))
                            ->from('personal_access_tokens')
                            ->whereRaw('personal_access_tokens.id = logins.personal_access_token_id')
                            ->whereNull('personal_access_tokens.expires_at')
                            ->orWhere('personal_access_tokens.expires_at', '>', now());
                    });
                    break;

                case 'inactive':
                    $query->where(function ($query) {
                        $query->whereNull('personal_access_token_id')
                            ->orWhereExists(function ($query) {
                                $query->select(DB::raw(1))
                                    ->from('personal_access_tokens')
                                    ->whereRaw('personal_access_tokens.id = logins.personal_access_token_id')
                                    ->where(function ($q) {
                                        $q->whereNotNull('personal_access_tokens.expires_at')
                                            ->where('personal_access_tokens.expires_at', '<=', now());
                                    });
                            });
                    });
                    break;
            }
        }

        // User filtering
        if ($request->filled('user')) {
            $query->where('authenticatable_id', $request->user);
        }

        // Search filtering
        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('authenticatable', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%");
            });
        }

        $sessions = $query->get();

        // Transform the data to include proper active status
        $sessions->transform(function ($session) {
            $token = $session->personalAccessToken;
            $session->is_active = $token &&
                ($token->expires_at === null || $token->expires_at > now());
            return $session;
        });

        if ($format === 'pdf') {
            $html = view('exports.general_export', [
                'name' => "Sessions",
                'rows' => $sessions,
                'columns' => $columns,
                'date' => now()->format('Y-m-d H:i:s')
            ])->render();
            return response()->json(['html' => $html]);
        }

        $name = 'sessions.' . $format;
        return Excel::download(new GeneralExport($sessions, $columns), $name);
    }
}
