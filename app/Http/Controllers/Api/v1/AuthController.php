<?php

namespace App\Http\Controllers\Api\v1;
use App\Http\Controllers\Controller;


use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Resources\AuthResource;
use App\Models\LoginHistory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Jenssegers\Agent\Agent;

/**
 * @group Authentication
 * 
 * API endpoints for user authentication and session management.
 * Handles multi-workspace authentication flow with secure token generation/revocation.
 */
class AuthController extends Controller
{
    /**
     * User Authentication with Workspace Context
     *
     * Authenticates a user and manages workspace selection for multi-tenant access.
     * If the user has multiple workspaces, they must select one to proceed.
     * The selected workspace context is encrypted and stored for subsequent requests.
     *
     * @OA\Post(
     *     path="/login",
     *     summary="Authenticate user with workspace selection",
     *     description="Login endpoint that handles multi-workspace authentication.",
     *     operationId="loginUser",
     *     tags={"Auth"},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Login credentials and optional workspace selection",
     *         @OA\JsonContent(ref="#/components/schemas/LoginRequest")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Login successful with workspace context",
     *         @OA\JsonContent(ref="#/components/schemas/LoginResponse")
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Invalid credentials",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Workspace selection required or unauthorized",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation errors",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal Server Error",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     )
     * )
     */
    public function login(Request $request)
    {
        $request->validate([
            'login' => 'required|string',
            'password' => 'required|string',
            'workspace' => 'nullable',
            'tz' => 'nullable|string',
        ]);

        $login = trim($request->input('login'));
        $password = $request->input('password');

        $historyData = [
            'identifier' => $login,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ];

        $agent = new Agent();
        $isMobile = $agent->isMobile();

        $isEmail = filter_var($login, FILTER_VALIDATE_EMAIL);
        $isPhone = preg_match('/^\+?[0-9]{7,15}$/', $login);

        $user = null;

        if ($isEmail) {
            $user = User::where('email', $login)->first();
        } elseif ($isPhone) {
            $digits = ltrim($login, '+');
            $user = User::query()
                ->whereRaw("CONCAT(country_code, phone) = ?", [$digits])
                ->orWhereRaw("CONCAT('+', country_code, phone) = ?", ['+' . $digits])
                ->orWhere('phone', $digits)
                ->first();
        } else {
            $user = User::where('username', $login)->first();

        }

        if (!$user || !Hash::check($password, $user->password)) {
            LoginHistory::create(array_merge($historyData, ['status' => false]));
            return sendResponse('Invalid credentials.', [], false, 401);
        }

        // if ($user->status !== 'active') {
        //     $user->tokens()->delete();
        //     Auth::guard('web')->logout();
        //     LoginHistory::create(array_merge($historyData, [
        //         'user_id' => $user->id,
        //         'status' => false,
        //     ]));
        //     return sendResponse('Your account is inactive. Please contact support.', [], false, 403);
        // }

        $disAllowedRoles = ["Sorter", "Driver", "Vendor Driver"];
        if (!$isMobile && $user->hasAnyRole($disAllowedRoles)) {
            $user->tokens()->delete();
            Auth::guard('web')->logout();
            LoginHistory::create(array_merge($historyData, [
                'user_id' => $user->id,
                'status' => false,
            ]));
            return sendResponse('You are not authorized to access the system.', [], false, 403);
        }

        Auth::login($user);

        // Resolve timezone for this session
        $timezone = app(\App\Services\TimezoneService::class)->resolveTimezoneForLogin(
            $request->input('tz'),
            $user
        );

        // Create token with timezone
        $tokenResult = $user->createToken('main');
        $tokenResult->accessToken->timezone = $timezone;
        $tokenResult->accessToken->save();

        $token = $tokenResult->plainTextToken;
        $user['token'] = $token;

        $hubs = $user->hubs->map(fn($hub) => [
            'id' => $hub->id,
            'name' => $hub->name,
            'type' => accountables("hub"),
        ]);

        $stations = $user->stations->map(fn($station) => [
            'id' => $station->id,
            'name' => $station->name,
            'type' => accountables("station"),
        ]);

        $branches = $user->branches->map(fn($branch) => [
            'id' => $branch->id,
            'name' => $branch->name,
            'type' => accountables("branch"),
        ]);

        if ($hubs->isEmpty() && $stations->isEmpty() && $branches->isEmpty()) {
            LoginHistory::create(array_merge($historyData, [
                'user_id' => $user->id,
                'status' => true,
            ]));    
            return sendResponse('User login successful.', new AuthResource($user), true);
        }

        $totalFacilities = $hubs->count() + $stations->count() + $branches->count();
        if ($totalFacilities === 1) {
            if ($hubs->count() === 1) {
                $selectedWorkspace = $hubs->first();
                $selectedWorkspace['type'] = "App\\Models\\Hub";
            } elseif ($stations->count() === 1) {
                $selectedWorkspace = $stations->first();
                $selectedWorkspace['type'] = "App\\Models\\Station";
            } else {
                $selectedWorkspace = $branches->first();
                $selectedWorkspace['type'] = "App\\Models\\Branch";
            }

            $selectedWorkspace['id'] = Crypt::encryptString($selectedWorkspace['id']);
            $user['workspace'] = $selectedWorkspace;

            $request->headers->set('X-Workspace-Key', $selectedWorkspace['id']);
            $request->headers->set('X-Workspace-Type', $selectedWorkspace['type']);

            LoginHistory::create(array_merge($historyData, [
                'user_id' => $user->id,
                'status' => true,
            ]));

            return sendResponse('Login successful.', new AuthResource($user), true);
        }

        $workspace = json_decode($request->workspace);
        if (!$workspace) {
            Auth::guard('web')->logout();

            LoginHistory::create(array_merge($historyData, [
                'user_id' => $user->id,
                'status' => true,
            ]));

            return sendResponse('Please select a workspace to continue.', [
                'workspace' => collect($hubs)->merge($stations)->merge($branches)->filter()->values()
            ], false, 403);
        }

        $selectedWorkspace = null;
        if ($workspace->type == "App\\Models\\Hub") {
            $selectedWorkspace = $hubs->firstWhere('id', $workspace->id);
            if ($selectedWorkspace)
                $selectedWorkspace['type'] = "App\\Models\\Hub";
        } elseif ($workspace->type == "App\\Models\\Station") {
            $selectedWorkspace = $stations->firstWhere('id', $workspace->id);
            if ($selectedWorkspace)
                $selectedWorkspace['type'] = "App\\Models\\Station";
        } elseif ($workspace->type == "App\\Models\\Branch") {
            $selectedWorkspace = $branches->firstWhere('id', $workspace->id);
            if ($selectedWorkspace)
                $selectedWorkspace['type'] = "App\\Models\\Branch";
        }

        if (!$selectedWorkspace) {
            Auth::guard('web')->logout();
            return sendResponse('Invalid workspace selection.', [], false, 403);
        }

        $selectedWorkspace['id'] = Crypt::encryptString($selectedWorkspace['id']);
        $user['workspace'] = $selectedWorkspace;

        $request->headers->set('X-Workspace-Key', $selectedWorkspace['id']);
        $request->headers->set('X-Workspace-Type', $selectedWorkspace['type']);

        LoginHistory::create(array_merge($historyData, [
            'user_id' => $user->id,
            'status' => true,
        ]));

        return sendResponse('Login successful.', new AuthResource($user), true);
    }


    /**
     * Legacy Authentication Endpoint
     *
     * @deprecated This endpoint is scheduled for removal in v2.0. Use /login instead.
     *
     * @OA\Post(
     *     path="/login-legacy",
     *     summary="Legacy authentication without workspace context",
     *     description="
     * **DEPRECATED:** This endpoint maintains backward compatibility but lacks workspace context.
     * 
     * **Migration Note:** Please update your applications to use the `/login` endpoint instead.
     * This endpoint will be removed in API version 2.0.
     * ",
     *     operationId="loginUserLegacy",
     *     tags={"Authentication"},
     *     deprecated=true,
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email", "password"},
     *             @OA\Property(property="email", type="string", format="email", example="user@gmail.com"),
     *             @OA\Property(property="password", type="string", format="password", example="password123")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Login successful",
     *         @OA\JsonContent(ref="#/components/schemas/LoginResponse")
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Authentication failed",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     )
     * )
     */
    public function login1(Request $request)
    {
        $request->validate([
            'email' => 'required|exists:users,email',
            'password' => 'required',
        ]);
        $count = User::where('email', '=', $request->email)->count();

        if ($count > 0) {
            if (Auth::attempt($request->only('email', 'password'))) {
                $user = Auth::user();
                $user['token'] = $user->createToken('device-' . time())->plainTextToken;

                return sendResponse('Login successful', new AuthResource($user), true);
            } else {
                return sendResponse('Password is incorrect.', [], false, 500);
            }
        } else {
            return sendResponse('No account found with this email.', [], false, 500);
        }
    }

    /**
     * User Logout
     * 
     * Terminates the user's active session by revoking all authentication tokens.
     *
     * @OA\Post(
     *     path="/logout",
     *     summary="Terminate user session",
     *     description="Securely logs out the authenticated user.",
     *     operationId="logoutUser",
     *     tags={"Auth"},
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Logout successful",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Logout Successfull"),
     *             @OA\Property(property="data", type="array", @OA\Items()),
     *             @OA\Property(property="errors", type="array", @OA\Items())
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     )    
     * )
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return sendResponse("Logout Successfull", [], []);
    }
}
