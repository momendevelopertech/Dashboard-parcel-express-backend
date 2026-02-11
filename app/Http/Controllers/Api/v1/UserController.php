<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;


use App\Exports\UsersExport;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Http\Resources\UserDetailResource;
use App\Imports\UsersImport;
use App\Models\Account;
use App\Models\Branch;
use App\Models\BranchUser;
use App\Models\Driver;
use App\Models\DriverBonus;
use App\Models\DriverBonusTemplate;
use App\Models\DriverFile;
use App\Models\DriverRelative;
use App\Models\Hub;
use App\Models\HubUser;
use App\Models\Transaction;
use Illuminate\Support\Facades\Crypt;
use App\Models\Setting;
use App\Models\State;
use App\Models\Station;
use App\Models\StationUser;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rules\Password;
use Log;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use App\Services\DriverService;

/**
 * @OA\Tag(
 *     name="WMS",
 *     description="User management endpoints"
 * )
 */
/**
 * Controller handling user management operations
 *
 * Features:
 * - User CRUD operations
 * - Role-based access control
 * - Facility-specific user assignments
 * - Driver management
 * - Password management
 */
class UserController extends Controller
{
    /**
     * Get list of users
     *
     * @OA\Get(
     *   path="/users",
     *   tags={"WMS"},
     *   summary="Get list of users",
     *   description="Retrieve list of users with optional search",
     *   operationId="getUsers",
     *   security={
     *     {"bearerAuth": {}}
     *   },
     *   @OA\Parameter(
     *     name="query",
     *     in="query",
     *     description="Search term for user name",
     *     required=false,
     *     @OA\Schema(
     *         type="string"
     *     )
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Successful operation",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="Users retrieved successfully."),
     *       @OA\Property(
     *         property="data",
     *         type="array",
     *         @OA\Items(
     *             @OA\Property(property="id", type="integer", format="int64"),
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="email", type="string"),
     *             @OA\Property(property="roles", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="branch_user", type="object"),
     *             @OA\Property(property="station_user", type="object"),
     *             @OA\Property(property="hub_user", type="object")
     *         )
     *       )
     *     )
     *   ),
     *   @OA\Response(
     *     response=422,
     *     description="Invalid search syntax",
     *     @OA\JsonContent(
     *       @OA\Property(property="message", type="string", example="Error Occured."),
     *       @OA\Property(property="errors", type="array", @OA\Items(type="string"))
     *     )
     *   )
     * )
     */
    public function index()
    {
        $perPage = request()->query('per_page', 8);
        // Start building the query
        $users = User::query()
            ->whereDoesntHave('driver')
            ->byOwnerOrFacilityAccess()
            ->whereDoesntHave('roles', function ($query) {
                $query->whereIn('name', [
                    'Merchant',
                    'Merchant Admin',
                    'Driver',
                    'Truck Driver',
                    'Driver Admin',
                    'Guest Driver',
                ]);
            })
            ->with([
                'roles',
                'owner',
                'hubUsers.hub',
                'stationUsers.station',
                'branchUsers.branch'
            ]);

        if (request()->has('query')) {
            $query = request()->input('query');
            $users->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($query) . '%']);
        }

        $users = $users->orderBy('id', 'desc')
            ->paginate($perPage);

        return sendResponse("Users retrieved successfully.", new UserResource($users), []);
    }

    /**
     * Create new user
     * e
     * @OA\Post(
     *   path="/users/store",
     *   tags={"WMS"},
     *   summary="Create new user",
     *   description="Create a new user with role assignment",
     *   operationId="createUser",
     *   security={
     *     {"bearerAuth": {}}
     *   },
     *   @OA\RequestBody(
     *     description="User creation data",
     *     required=true,
     *     @OA\JsonContent(
     *       required={
     *         "name",
     *         "email",
     *         "password",
     *         "role",
     *         "type"
     *       },
     *       @OA\Property(property="name", type="string", example="John Doe"),
     *       @OA\Property(property="email", type="string", format="email", example="john@example.com"),
     *       @OA\Property(property="password", type="string", format="password"),
     *       @OA\Property(property="role", type="integer", format="int64", example=1),
     *       @OA\Property(property="type", type="string", example="user")
     *     )
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="User created successfully",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="User created successfully."),
     *       @OA\Property(
     *         property="data",
     *         type="object",
     *         @OA\Property(property="id", type="integer", format="int64"),
     *         @OA\Property(property="name", type="string"),
     *         @OA\Property(property="email", type="string"),
     *         @OA\Property(property="roles", type="array", @OA\Items(type="object")),
     *         @OA\Property(property="branch_user", type="object"),
     *         @OA\Property(property="station_user", type="object"),
     *         @OA\Property(property="hub_user", type="object")
     *       )
     *     )
     *   ),
     *   @OA\Response(
     *     response=422,
     *     description="Validation error",
     *     @OA\JsonContent(
     *       @OA\Property(property="message", type="string", example="Error Occured."),
     *       @OA\Property(property="errors", type="array", @OA\Items(type="string"))
     *     )
     *   )
     * )
     */
    public function store(StoreUserRequest $request)
    {
        $request->validated();

        \DB::beginTransaction();
        try {
            $phoneSplit = splitPhoneNumber($request->phone);

            $user = User::create([
                'name' => $request->name,
                'username' => $request->username,
                'email' => $request->email,
                'country_code' => $phoneSplit['country_code'],
                'phone' => $phoneSplit['national_number'],
                'password' => \Hash::make($request->password),
            ]);

            $role = Role::find($request->role);
            $user->syncRoles($role);

            $rolesWithoutWorkspaces = ['Customer Service', 'Driver'];

            // لو الرول يتطلب workspace
            if ($role && !in_array($role->name, $rolesWithoutWorkspaces)) {

                $attached = 0;

                // 1) من الفورم
                if ($request->has('workspaces') && is_array($request->workspaces)) {
                    foreach ($request->workspaces as $ws) {
                        $rawId = $ws['id'] ?? null;
                        $rawType = $ws['type'] ?? null;
                        $type = self::normalizeWorkspaceType($rawType);
                        $id = self::resolveWorkspaceId($rawId);

                        if (!$type || !$id) {
                            continue;
                        }

                        $attached += self::attachWorkspace($user, $type, $id);
                    }
                }

                if ($attached === 0) {
                    $hdrKey = $request->header('X-Workspace-Key');
                    $hdrType = $request->header('X-Workspace-Type');

                    if ($hdrKey && $hdrType) {
                        $type = self::normalizeWorkspaceType($hdrType);
                        $id = self::resolveWorkspaceId($hdrKey);

                        if ($type && $id) {
                            self::attachWorkspace($user, $type, $id);
                        }
                    }
                }
            }

            // Assign Default Driver Bonuses (Delivery/Pickup) from Templates
            if ($role && $role->name === 'Driver') {
                $facilityId = $user->owner_id ?? facility('id');
                $facilityType = $user->owner_type ?? facility('type');

                // Fallback to headers if still empty
                if (!$facilityId && $request->header('X-Workspace-Key')) {
                    $val = $request->header('X-Workspace-Key');
                    if (is_numeric($val)) {
                        $facilityId = $val;
                    } else {
                        try {
                            $facilityId = \Crypt::decryptString($val);
                        } catch (\Throwable $e) {
                        }
                    }
                }
                if (!$facilityType && $request->header('X-Workspace-Type')) {
                    $val = $request->header('X-Workspace-Type');
                    // Normalize type if needed or take as is
                    $facilityType = self::normalizeWorkspaceType($val);
                }

                // Update user owner if missing
                if (!$user->owner_id && $facilityId) {
                    $user->owner_id = $facilityId;
                    $user->owner_type = $facilityType;
                    $user->saveQuietly();
                }

                $driver = Driver::firstOrCreate(
                    ['user_id' => $user->id],
                    [
                        'phone' => $phoneSplit['national_number'] ?? $request->phone,
                        'country_code' => $phoneSplit['country_code'] ?? null,
                        'owner_id' => $facilityId,
                        'owner_type' => $facilityType,
                    ]
                );

                if ($facilityId && $facilityType) {
                    $states = State::select('id')->get();
                    $templates = DriverBonusTemplate::where('owner_id', $facilityId)
                        ->where('owner_type', $facilityType)
                        ->get();

                    $globalTemplate = $templates->where('state_id', null)->first();
                    $stateTemplates = $templates->whereNotNull('state_id')->keyBy('state_id');

                    foreach ($states as $state) {
                        $tpl = $stateTemplates->get($state->id) ?? $globalTemplate;

                        if ($tpl) {
                            DriverBonus::updateOrCreate(
                                [
                                    'driver_id' => $user->id,
                                    'state_id' => $state->id,
                                ],
                                [
                                    'delivery_bonus' => $tpl->delivery_bonus,
                                    'pickup_bonus' => $tpl->pickup_bonus,
                                    'owner_id' => $facilityId,
                                    'owner_type' => $facilityType,
                                ]
                            );
                        }
                    }
                }
            }
                 activityLog('user created',"new user created with username: $user->username");
            \DB::commit();

            return sendResponse("User created successfully.", $user->load('roles'));
        } catch (\Illuminate\Database\QueryException $e) {
            \DB::rollBack();
            return sendResponse("Error Occurred.", [], [$e->getMessage()], 422);
        } catch (\Throwable $th) {
            \DB::rollBack();
            return sendResponse("Unexpected error.", [], [$th->getMessage()], 500);
        }
    }

    /**
     * يقبل id مشفّر أو عادي
     */
    private static function resolveWorkspaceId($value)
    {
        if (is_null($value))
            return null;

        if (is_numeric($value)) {
            return (int) $value;
        }

        try {
            return (int) \Crypt::decryptString($value);
        } catch (\Throwable $e) {
            return null;
        }
    }


    private static function normalizeWorkspaceType($type)
    {
        if (!$type)
            return null;

        $t = trim($type, '\\');
        $t = str_replace('/', '\\', $t);
        $tLower = strtolower($t);

        if (in_array($tLower, ['hub', 'hubs']))
            return \App\Models\Hub::class;
        if (in_array($tLower, ['station', 'stations']))
            return \App\Models\Station::class;
        if (in_array($tLower, ['branch', 'branches']))
            return \App\Models\Branch::class;

        if ($tLower === 'app\models\hub')
            return \App\Models\Hub::class;
        if ($tLower === 'app\models\station')
            return \App\Models\Station::class;
        if ($tLower === 'app\models\branch')
            return \App\Models\Branch::class;

        return null;
    }


    private static function attachWorkspace(User $user, string $type, int $id): int
    {
        switch ($type) {
            case Hub::class:
                HubUser::updateOrCreate(
                    ['hub_id' => $id, 'user_id' => $user->id],
                    []
                );
                return 1;

            case Station::class:
                StationUser::updateOrCreate(
                    ['station_id' => $id, 'user_id' => $user->id],
                    []
                );
                return 1;

            case \App\Models\Branch::class:
                \App\Models\BranchUser::updateOrCreate(
                    ['branch_id' => $id, 'user_id' => $user->id],
                    []
                );
                return 1;

            default:
                return 0;
        }
    }

    public function edit($id)
    {
        $user = User::with(["roles", "permissions", "hub_users", "station_users", "branch_users"])->find($id);
        return sendResponse("Role", new UserDetailResource($user));
    }

    /**
     * Update user information
     *
     * @OA\Post(
     *   path="/users/update",
     *   tags={"WMS"},
     *   summary="Update user information",
     *   description="Update existing user's information and role",
     *   operationId="updateUser",
     *   security={
     *     {"bearerAuth": {}}
     *   },
     *   @OA\RequestBody(
     *     description="User update data",
     *     required=true,
     *     @OA\JsonContent(
     *       required={
     *         "id",
     *         "name",
     *         "email",
     *         "role"
     *       },
     *       @OA\Property(property="id", type="integer", format="int64", example=1),
     *       @OA\Property(property="name", type="string", example="John Doe"),
     *       @OA\Property(property="email", type="string", format="email", example="john@example.com"),
     *       @OA\Property(property="role", type="integer", format="int64", example=1)
     *     )
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="User updated successfully",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="User updated successfully."),
     *       @OA\Property(
     *         property="data",
     *         type="object",
     *         @OA\Property(property="id", type="integer", format="int64"),
     *         @OA\Property(property="name", type="string"),
     *         @OA\Property(property="email", type="string"),
     *         @OA\Property(property="roles", type="array", @OA\Items(type="object"))
     *       )
     *     )
     *   ),
     *   @OA\Response(
     *     response=422,
     *     description="Validation error",
     *     @OA\JsonContent(
     *       @OA\Property(property="message", type="string", example="Error Occured."),
     *       @OA\Property(property="errors", type="array", @OA\Items(type="string"))
     *     )
     *   )
     * )
     */

    public function update(UpdateUserRequest $request)
    {
        $request->validated();

        \DB::beginTransaction();
        try {
            $user = User::findOrFail($request->id);

            $data = $request->only([
                'name',
                'username',
                'email',
            ]);

            if ($request->filled('phone')) {
                $phoneSplit = splitPhoneNumber($request->phone);
                $data['country_code'] = $phoneSplit['country_code'] ?? null;
                $data['phone'] = $phoneSplit['national_number'] ?? null;
            }

            if ($request->file('image')) {
                $data['image'] = uploadFile($request->file('image'), 'public/users/images');
            }

            $user->update($data);
            $hub = getUserHub($user); 

            $role = Role::where('id', $request->role)
            ->where('roleable_type', \App\Models\Hub::class)
            ->when($hub, fn ($q) => $q->where('roleable_id', $hub->id))
            ->first();
            $rolesWithoutWorkspaces = ['Customer Service', 'Driver'];

            if ($role) {
                $user->syncRoles([$role->id]);
            } else {
                $user->syncRoles([]);
            }

            if ($role && !in_array($role->name, $rolesWithoutWorkspaces)) {
                // Check if workspaces key exists in request
                // Empty array or clear marker means clear all workspaces to prevent old data/duplicates
                if ($request->has('workspaces')) {
                    $targets = [
                        Hub::class => [],
                        Station::class => [],
                        Branch::class => [],
                    ];

                    $workspaces = $request->workspaces;
                    $hasClearMarker = false;

                    // Check if this is an empty array request (clear marker sent)
                    if (is_array($workspaces) && !empty($workspaces)) {
                        $firstItem = reset($workspaces);
                        if (isset($firstItem['clear']) && $firstItem['clear'] === 'true') {
                            $hasClearMarker = true;
                            // Clear all workspaces - targets remain empty
                        } else {
                            // Process normal workspaces
                            foreach ($workspaces as $ws) {
                                $rawId = $ws['id'] ?? null;
                                $rawTyp = $ws['type'] ?? null;

                                $type = $this->normalizeWorkspaceType($rawTyp);
                                $id = $this->resolveWorkspaceId($rawId); // يقبل مشفّر أو رقم خام

                                if (!$type || !$id) {
                                    continue;
                                }

                                $targets[$type][] = (int) $id;
                            }
                        }
                    } else {
                        // Empty array - clear all workspaces
                        $hasClearMarker = true;
                    }

                    // Clear all workspaces first, then add new ones
                    // This ensures no old data/duplicates remain

                    // Hubs: Delete all, then add new ones
                    HubUser::where('user_id', $user->id)->delete();
                    foreach (array_unique($targets[Hub::class]) as $hid) {
                        HubUser::updateOrCreate(
                            ['user_id' => $user->id, 'hub_id' => $hid],
                            []
                        );
                    }

                    // Stations: Delete all, then add new ones
                    StationUser::where('user_id', $user->id)->delete();
                    foreach (array_unique($targets[Station::class]) as $sid) {
                        StationUser::updateOrCreate(
                            ['user_id' => $user->id, 'station_id' => $sid],
                            []
                        );
                    }

                    // Branches: Delete all, then add new ones
                    BranchUser::where('user_id', $user->id)->delete();
                    foreach (array_unique($targets[Branch::class]) as $bid) {
                        BranchUser::updateOrCreate(
                            ['user_id' => $user->id, 'branch_id' => $bid],
                            []
                        );
                    }
                } else {
                    // لو الرول يسمح بالورك سبيس لكن العميل مبعتش workspaces key:
                    // نسيب القديم زي ما هو (ما نمسحش حاجة) - فقط لو مفيش key خالص
                }
            } else {
                // role لا يحتاج workspaces → امسح أي ربطات قديمة
                HubUser::where('user_id', $user->id)->delete();
                StationUser::where('user_id', $user->id)->delete();
                BranchUser::where('user_id', $user->id)->delete();
            }

            // ====== Direct permissions ======
            $userPermissionIds = array_filter((array) $request->input('user_permissions', []));
            if (!empty($userPermissionIds)) {
                $perms = Permission::whereIn('id', $userPermissionIds)->get();
                $user->syncPermissions($perms);
            } else {
                $user->syncPermissions([]); // امسح المباشرة فقط
            }
              activityLog('user updated',"user updated with username: $user->username");
            \DB::commit();

            return sendResponse(
                "User updated successfully.",
                $user->load('roles', 'permissions', 'hubUsers.hub', 'stationUsers.station', 'branchUsers.branch')
            );
        } catch (QueryException $e) {
            \DB::rollBack();
            return sendResponse("Error Occurred.", [], [$e->getMessage()], 422);
        } catch (\Throwable $th) {
            \DB::rollBack();
            return sendResponse("Unexpected error.", [], [$th->getMessage()], 500);
        }
    }
    // public function update(UpdateUserRequest $request)
    // {
    //     $request->validated();

    //     try {
    //         $user = User::findOrFail($request->id);
    //         $data = $request->all();

    //         // phone split
    //         if ($request->has('phone')) {
    //             $phoneSplit = splitPhoneNumber($request->phone);
    //             $data['country_code'] = $phoneSplit['country_code'];
    //             $data['phone'] = $phoneSplit['national_number'];
    //         }

    //         // image
    //         if ($request->image) {
    //             $image = uploadFile($request->image, 'public/users/images');
    //             $data['image'] = $image;
    //         } else {
    //             $data['image'] = $user->image;
    //         }

    //         $user->update($data);

    //         // role
    //         $role = Role::find($request->role);
    //         $rolesWithoutWorkspaces = ['Customer Service', 'Driver'];

    //         // workspaces logic زي ما هو عندك
    //         if ($role && !in_array($role->name, $rolesWithoutWorkspaces) && $request->has('workspaces')) {
    //             $user->hubUsers()->delete();
    //             $user->stationUsers()->delete();
    //             $user->branchUsers()->delete();

    //             foreach ($request->workspaces as $workspace) {
    //                 try {
    //                     $workspaceId = Crypt::decryptString($workspace['id']);
    //                 } catch (\Exception $e) {
    //                     continue;
    //                 }

    //                 switch ($workspace['type']) {
    //                     case 'App\\Models\\Branch':
    //                         BranchUser::create(['user_id' => $user->id, 'branch_id' => $workspaceId]);
    //                         break;
    //                     case 'App\\Models\\Station':
    //                         StationUser::create(['user_id' => $user->id, 'station_id' => $workspaceId]);
    //                         break;
    //                     case 'App\\Models\\Hub':
    //                         HubUser::create(['user_id' => $user->id, 'hub_id' => $workspaceId]);
    //                         break;
    //                 }
    //             }
    //         } elseif ($role && in_array($role->name, $rolesWithoutWorkspaces)) {
    //             $user->hubUsers()->delete();
    //             $user->stationUsers()->delete();
    //             $user->branchUsers()->delete();
    //         }

    //         // ثبّت الدور
    //         if ($role) {
    //             $user->syncRoles([$role->id]);
    //         } else {
    //             $user->syncRoles([]);
    //         }

    //         // ========= NEW: صلاحيات مباشرة للمستخدم =========
    //         // ممكن تبعته IDs أو names. هنا هنفترض IDs جاية في user_permissions[]
    //         $userPermissionIds = (array) $request->input('user_permissions', []);

    //         if (!empty($userPermissionIds)) {
    //             // هات أسماء/أوبچكتات الصلاحيات
    //             $perms = Permission::whereIn('id', $userPermissionIds)->get();

    //             // syncPermissions بتتعامل مع "الصلاحيات المباشرة" فقط
    //             // (مش بتلغي صلاحيات الـ Role)
    //             $user->syncPermissions($perms);
    //         } else {
    //             // لو عايز تمسح كل الصلاحيات المباشرة في حالة مبعتش حاجة
    //             $user->syncPermissions([]);
    //         }

    //         // لو غيرت في تعريف permissions/roles نفسهم، امسح الكاش
    //         // app(PermissionRegistrar::class)->forgetCachedPermissions();

    //     } catch (QueryException $e) {
    //         return sendResponse("Error Occurred.", [], [$e->getMessage()]);
    //     }

    //     return sendResponse(
    //         "User updated successfully.",
    //         $user->load('roles', 'permissions', 'hubUsers.hub', 'stationUsers.station', 'branchUsers.branch')
    //     );
    // }

    /**
     * Delete user
     *
     * @OA\Post(
     *   path="/users/delete",
     *   tags={"WMS"},
     *   summary="Delete user",
     *   description="Delete an existing user",
     *   operationId="deleteUser",
     *   security={
     *     {"bearerAuth": {}}
     *   },
     *   @OA\RequestBody(
     *     description="User deletion data",
     *     required=true,
     *     @OA\JsonContent(
     *       required={
     *         "id"
     *       },
     *       @OA\Property(property="id", type="integer", format="int64", example=1)
     *     )
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="User deleted successfully",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="User deleted successfully."),
     *       @OA\Property(property="data", type="array", @OA\Items(type="string"))
     *     )
     *   ),
     *   @OA\Response(
     *     response=422,
     *     description="Constraint violations",
     *     @OA\JsonContent(
     *       @OA\Property(property="message", type="string", example="Error Occured."),
     *       @OA\Property(property="errors", type="array", @OA\Items(type="string"))
     *     )
     *   )
     * )
     */
    public function delete(Request $request)
    {
        try {
            $user = User::find($request->id);
            $user->delete();
            activityLog('user deleted',"user deleted with username: $user->username");
        } catch (QueryException $e) {
            return sendResponse("Error Occured.", [], [$e->getMessage()], 422);
        }
        return sendResponse("User deleted successfully.", []);
    }

    /**
     * Change user password
     *
     * @OA\Post(
     *   path="/users/change_password",
     *   tags={"WMS"},
     *   summary="Change user password",
     *   description="Change an existing user's password",
     *   operationId="changePassword",
     *   security={
     *     {"bearerAuth": {}}
     *   },
     *   @OA\RequestBody(
     *     description="Password change data",
     *     required=true,
     *     @OA\JsonContent(
     *       required={
     *         "password",
     *         "id"
     *       },
     *       @OA\Property(property="password", type="string", format="password"),
     *       @OA\Property(property="id", type="integer", format="int64", example=1)
     *     )
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Password changed successfully",
     *     @OA\JsonContent(
     *       @OA\Property(property="message", type="string", example="Password changed successfully.")
     *     )
     *   ),
     *   @OA\Response(
     *     response=422,
     *     description="Validation error",
     *     @OA\JsonContent(
     *       @OA\Property(property="message", type="string", example="Error Occured."),
     *       @OA\Property(property="errors", type="array", @OA\Items(type="string"))
     *     )
     *   )
     * )
     */
    public function change_password(Request $request)
    {
        $request->validate(
            [
                'password' => [
                    'required',
                    'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/',
                    Password::defaults()
                ],
                'id' => 'required'
            ],
            [
                'password.regex' => 'The password must contain at least one uppercase letter, one lowercase letter, and one number.',
            ]
        );

        try {
            $user = User::find($request->id);
            $user->password = Hash::make($request->password);
            $user->save();
            activityLog('user password changed',"user password changed with username: $user->username");
            return response()->json(['message' => 'Password changed successfully.']);
        } catch (QueryException $e) {
            return sendResponse("Error Occured.", [], [$e->getMessage()]);
        }
        return response()->json(['message' => 'Current password is incorrect.'], 422);
    }


    /**
     * Create branch admin
     *
     * @OA\Post(
     *   path="/users/store_branch_admin",
     *   tags={"WMS"},
     *   summary="Create branch admin",
     *   description="Create a new branch administrator",
     *   operationId="createBranchAdmin",
     *   security={
     *     {"bearerAuth": {}}
     *   },
     *   @OA\RequestBody(
     *     description="Branch admin creation data",
     *     required=true,
     *     @OA\JsonContent(
     *       required={
     *         "name",
     *         "email",
     *         "password",
     *         "branch_id"
     *       },
     *       @OA\Property(property="name", type="string", example="Branch Admin"),
     *       @OA\Property(property="email", type="string", format="email", example="branch@example.com"),
     *       @OA\Property(property="password", type="string", format="password"),
     *       @OA\Property(
     *         property="branch_id",
     *         type="array",
     *         @OA\Items(type="integer", format="int64"),
     *         example="[1]"
     *       )
     *     )
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Branch admin created successfully",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="User created successfully."),
     *       @OA\Property(
     *         property="data",
     *         type="object",
     *         @OA\Property(property="id", type="integer", format="int64"),
     *         @OA\Property(property="name", type="string"),
     *         @OA\Property(property="email", type="string"),
     *         @OA\Property(property="roles", type="array", @OA\Items(type="object")),
     *         @OA\Property(property="branch_user", type="object"),
     *         @OA\Property(property="driver", type="object")
     *       )
     *     )
     *   ),
     *   @OA\Response(
     *     response=422,
     *     description="Validation error",
     *     @OA\JsonContent(
     *       @OA\Property(property="message", type="string", example="Error Occured."),
     *       @OA\Property(property="errors", type="array", @OA\Items(type="string"))
     *     )
     *   )
     * )
     */
    public function store_branch_admin(Request $request)
    {
        $request->validate(
            [
                "name" => "required",
                "email" => "nullable|email|unique:users,email",
                "phone" => "required|phone:AUTO",
                'password' => [
                    'required',
                    'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/',
                    Password::defaults()
                ],
                "branch_id" => "required|array",
                "branch_id.*" => "exists:stations,id"
            ],
            [
                'password.regex' => 'The password must contain at least one uppercase letter, one lowercase letter, and one number.',
            ]
        );
        try {
            // $phoneSplit = splitPhoneNumber($request->phone);
            $phoneSplit = splitPhoneNumber($request->phone);
            // $last4 = substr($phoneSplit['national_number'], -4);
            // $nameWithPhone = "{$last4} {$request->name}";
            $user = User::create([
                'owner_id' => $request->branch_id,
                'owner_type' => Branch::class,
                'name' => $request->name,
                'username' => $request->username,

                'email' => $request->email,
                'country_code' => $phoneSplit['country_code'],
                'phone' => $phoneSplit['national_number'],
                'password' => Hash::make($request->password),
            ]);

            foreach ($request->branch_id as $branchId) {
                BranchUser::create([
                    "user_id" => $user->id,
                    "branch_id" => $branchId
                ]);
            }
            $user->syncRoles("Branch Admin");
            activityLog('Branch Admin created',"new branch admin created with username: $user->username");
        } catch (QueryException $e) {
            return sendResponse("Error Occured.", [], [$e->getMessage()], 422);
        }
        return sendResponse("User created successfully.", [$user->load('roles', 'branch_user.branch', 'driver')]);
    }

    /**
     * Create station admin
     *
     * @OA\Post(
     *   path="/users/store_station_admin",
     *   tags={"WMS"},
     *   summary="Create station admin",
     *   description="Create a new station administrator",
     *   operationId="createStationAdmin",
     *   security={
     *     {"bearerAuth": {}}
     *   },
     *   @OA\RequestBody(
     *     description="Station admin creation data",
     *     required=true,
     *     @OA\JsonContent(
     *       required={
     *         "name",
     *         "email",
     *         "password",
     *         "station_id"
     *       },
     *       @OA\Property(property="name", type="string", example="Station Admin"),
     *       @OA\Property(property="email", type="string", format="email", example="station@example.com"),
     *       @OA\Property(property="password", type="string", format="password"),
     *       @OA\Property(
     *         property="station_id",
     *         type="array",
     *         @OA\Items(type="integer", format="int64"),
     *         example="[1]"
     *       )
     *     )
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Station admin created successfully",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="User created successfully."),
     *       @OA\Property(
     *         property="data",
     *         type="object",
     *         @OA\Property(property="id", type="integer", format="int64"),
     *         @OA\Property(property="name", type="string"),
     *         @OA\Property(property="email", type="string"),
     *         @OA\Property(property="roles", type="array", @OA\Items(type="object")),
     *         @OA\Property(property="station_user", type="object")
     *       )
     *     )
     *   ),
     *   @OA\Response(
     *     response=422,
     *     description="Validation error",
     *     @OA\JsonContent(
     *       @OA\Property(property="message", type="string", example="Error Occured."),
     *       @OA\Property(property="errors", type="array", @OA\Items(type="string"))
     *     )
     *   )
     * )
     */
    public function store_station_admin(Request $request)
    {
        $request->validate(
            [
                "name" => "required",
                "email" => "nullable|email|unique:users,email",
                "phone" => "required|phone:AUTO",
                'password' => [
                    'required',
                    'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/',
                    Password::defaults()
                ],
                "station_id" => "required|array",
                "station_id.*" => "exists:stations,id"
            ],
            [
                'password.regex' => 'The password must contain at least one uppercase letter, one lowercase letter, and one number.',
            ]
        );

        try {
            session()->put('skip_user_observer', true);
            // $phoneSplit = splitPhoneNumber($request->phone);
            $phoneSplit = splitPhoneNumber($request->phone);
            // $last4 = substr($phoneSplit['national_number'], -4);
            // $nameWithPhone = "{$last4} {$request->name}";
            $user = User::create([
                'owner_id' => $request->station_id[0],
                'owner_type' => Station::class,
                'username' => $request->username,

                'name' => $request->name,
                'email' => $request->email,
                'country_code' => $phoneSplit['country_code'],
                'phone' => $phoneSplit['national_number'],
                'password' => Hash::make($request->password),
            ]);

            foreach ($request->station_id as $stationId) {
                StationUser::create([
                    "user_id" => $user->id,
                    "station_id" => $stationId
                ]);
            }

            $user->syncRoles("Station Admin");
            activityLog('Station Admin created',"new station admin created with username: $user->username");
        } catch (QueryException $e) {
            return sendResponse("Error Occured.", [], [$e->getMessage()], 422);
        }
        return sendResponse("User created successfully.", [$user->load('roles', 'station_user.station')]);
    }

    /**
     * Create hub admin
     *
     * @OA\Post(
     *   path="/users/store_hub_admin",
     *   tags={"WMS"},
     *   summary="Create hub admin",
     *   description="Create a new hub administrator",
     *   operationId="createHubAdmin",
     *   security={
     *     {"bearerAuth": {}}
     *   },
     *   @OA\RequestBody(
     *     description="Hub admin creation data",
     *     required=true,
     *     @OA\JsonContent(
     *       required={
     *         "name",
     *         "email",
     *         "password",
     *         "hub_id"
     *       },
     *       @OA\Property(property="name", type="string", example="Hub Admin"),
     *       @OA\Property(property="email", type="string", format="email", example="hub@example.com"),
     *       @OA\Property(property="password", type="string", format="password"),
     *       @OA\Property(
     *         property="hub_id",
     *         type="array",
     *         @OA\Items(type="integer", format="int64"),
     *         example="[1]"
     *       )
     *     )
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Hub admin created successfully",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="User created successfully."),
     *       @OA\Property(
     *         property="data",
     *         type="object",
     *         @OA\Property(property="id", type="integer", format="int64"),
     *         @OA\Property(property="name", type="string"),
     *         @OA\Property(property="email", type="string"),
     *         @OA\Property(property="roles", type="array", @OA\Items(type="object")),
     *         @OA\Property(property="hub_user", type="object")
     *       )
     *     )
     *   ),
     *   @OA\Response(
     *     response=422,
     *     description="Validation error",
     *     @OA\JsonContent(
     *       @OA\Property(property="message", type="string", example="Error Occured."),
     *       @OA\Property(property="errors", type="array", @OA\Items(type="string"))
     *     )
     *   )
     * )
     */
    public function store_hub_admin(Request $request)
    {
        $request->validate(
            [
                "name" => "required",
                "email" => "nullable|email|unique:users,email",
                "phone" => "required|phone:AUTO",
                'password' => [
                    'required',
                    'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/',
                    Password::defaults()
                ],
                "hub_id" => "required|array",
                "hub_id.*" => "exists:hubs,id"
            ],
            [
                'password.regex' => 'The password must contain at least one uppercase letter, one lowercase letter, and one number.',
            ]
        );
        try {
            $phoneSplit = splitPhoneNumber($request->phone);
            // $last4 = substr($phoneSplit['national_number'], -4);
            // $nameWithPhone = "{$last4} {$request->name}";
            $user = User::create([
                'owner_id' => $request->hub_id,
                'owner_type' => Hub::class,
                'username' => $request->username,

                'name' => $request->name,
                'email' => $request->email,
                'country_code' => $phoneSplit['country_code'],
                'phone' => $phoneSplit['national_number'],
                'password' => Hash::make($request->password),
            ]);

            foreach ($request->hub_id as $hubId) {
                HubUser::create([
                    "user_id" => $user->id,
                    "hub_id" => $hubId
                ]);
            }

            $user->syncRoles("Hub Admin");
            activityLog('Hub Admin created',"new hub admin created with username: $user->username");
        } catch (QueryException $e) {
            return sendResponse("Error Occured.", [], [$e->getMessage()], 422);
        }
        return sendResponse("User created successfully.", [$user->load('roles', 'hub_user.hub')]);
    }

    /**
     * Create driver
     *
     * @OA\Post(
     *   path="/users/store_driver",
     *   tags={"WMS"},
     *   summary="Create driver",
     *   description="Create a new driver with associated documents and relatives",
     *   operationId="createDriver",
     *   security={
     *     {"bearerAuth": {}}
     *   },
     *   @OA\RequestBody(
     *     description="Driver creation data",
     *     required=true,
     *     @OA\JsonContent(
     *       required={
     *         "name",
     *         "email",
     *         "password",
     *         "company_id"
     *       },
     *       @OA\Property(property="name", type="string", example="Driver Name"),
     *       @OA\Property(property="email", type="string", format="email", example="driver@example.com"),
     *       @OA\Property(property="password", type="string", format="password"),
     *       @OA\Property(property="company_id", type="integer", format="int64", example=1),
     *       @OA\Property(property="phone", type="string", example="+1234567890"),
     *       @OA\Property(
     *         property="relative_name",
     *         type="array",
     *         @OA\Items(type="string")
     *       ),
     *       @OA\Property(
     *         property="relative_phone",
     *         type="array",
     *         @OA\Items(type="string")
     *       ),
     *       @OA\Property(
     *         property="relation",
     *         type="array",
     *         @OA\Items(type="string")
     *       )
     *     )
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Driver created successfully",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="Driver created successfully."),
     *       @OA\Property(
     *         property="data",
     *         type="object",
     *         @OA\Property(property="id", type="integer", format="int64"),
     *         @OA\Property(property="name", type="string"),
     *         @OA\Property(property="email", type="string"),
     *         @OA\Property(property="roles", type="array", @OA\Items(type="object")),
     *         @OA\Property(property="driver", type="object")
     *       )
     *     )
     *   ),
     *   @OA\Response(
     *     response=422,
     *     description="Validation error",
     *     @OA\JsonContent(
     *       @OA\Property(property="message", type="string", example="Error Occured."),
     *       @OA\Property(property="errors", type="array", @OA\Items(type="string"))
     *     )
     *   )
     * )
     */
    public function store_driver(Request $request)
    {
        $request->validate([
            "name" => "required",
            "username" => "required|unique:users,username",
            "email" => "nullable|email|unique:users,email",
            "phone" => [
                "required",
                "phone:AUTO",
                function ($attribute, $value, $fail) {
                    $phoneSplit = splitPhoneNumber($value);
                    $countryCode = $phoneSplit['country_code'] ?? null;
                    $phoneNumber = $phoneSplit['national_number'] ?? null;

                    if (!$countryCode || !$phoneNumber) {
                        return;
                    }

                    // Check if a user with the same phone number and Driver role exists
                    $exists = User::where('country_code', $countryCode)
                        ->where('phone', $phoneNumber)
                        ->whereHas('roles', function ($query) {
                            $query->where('name', 'Driver');
                        })
                        ->exists();

                    if ($exists) {
                        $fail('The phone number has already been taken for a driver account.');
                    }
                }
            ],
            'password' => [
                'required',
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/',
                Password::defaults()
            ],
            "company_id" => "required",
        ], [
            'password.regex' => 'The password must contain at least one uppercase letter, one lowercase letter, and one number.',
            'phone' => __('validation.phone'),
        ]);

        DB::beginTransaction();
        try {
            $facilityId=$request->facility_id ?? facility("id");
            $facilityType=$request->facility_type ?? facility("type");

            $phoneSplit = splitPhoneNumber($request->phone);
            // splitPhoneNumber now automatically preserves leading zeros for countries that use them

            $last4 = substr($phoneSplit['national_number'], -4);
            $nameWithPhone = "{$last4} {$request->name}";
            // 👇 اسم المستخدم بيفضل زي ما اليوزر كتبه بدون آخر 4
            $user = User::create([
                'name' => $nameWithPhone,
                'username' => $request->username,
                'email' => $request->email,
                'country_code' => $phoneSplit['country_code'],
                'phone' => $phoneSplit['national_number'],
                'password' => Hash::make($request->password),
                'owner_id' => $facilityId,
                'owner_type' => $facilityType,
            ]);



            Driver::create([
                "user_id" => $user->id,
                "company_id" => $request->company_id,
                'country_code' => $phoneSplit['country_code'],
                'phone' => $phoneSplit['national_number'],
                "id_card" => $request->id_card ? uploadFile($request->id_card, 'public/driver_files') : "",
                "license" => $request->license ? uploadFile($request->license, 'public/driver_files') : "",
                "car_ownership_id" => $request->car_ownership_id ? uploadFile($request->car_ownership_id, 'public/driver_files') : "",
            ]);

            // Assign Default Driver Bonuses (Delivery/Pickup) from Templates
            if (!$facilityId && $request->header('X-Workspace-Key')) {
                $val = $request->header('X-Workspace-Key');
                if (is_numeric($val)) {
                    $facilityId = $val;
                } else {
                    try {
                        $facilityId = \Crypt::decryptString($val);
                    } catch (\Throwable $e) {
                    }
                }
            }
            if (!$facilityType && $request->header('X-Workspace-Type')) {
                $val = $request->header('X-Workspace-Type');
                $facilityType = self::normalizeWorkspaceType($val);
            }

            if ($facilityId && $facilityType) {
                // Ensure driver has owner if it was created with null (because facility() was null)
                $driverRecord = Driver::where('user_id', $user->id)->first();
                if ($driverRecord && !$driverRecord->owner_id) {
                    $driverRecord->update([
                        'owner_id' => $facilityId,
                        'owner_type' => $facilityType
                    ]);
                }

                $states = State::select('id')->get();
                $templates = DriverBonusTemplate::where('owner_id', $facilityId)
                    ->where('owner_type', $facilityType)
                    ->get();

                $globalTemplate = $templates->where('state_id', null)->first();
                $stateTemplates = $templates->whereNotNull('state_id')->keyBy('state_id');

                foreach ($states as $state) {
                    $tpl = $stateTemplates->get($state->id) ?? $globalTemplate;

                    if ($tpl) {
                        DriverBonus::updateOrCreate(
                            [
                                'driver_id' => $user->id,
                                'state_id' => $state->id,
                            ],
                            [
                                'delivery_bonus' => $tpl->delivery_bonus,
                                'pickup_bonus' => $tpl->pickup_bonus,
                                'owner_id' => $facilityId,
                                'owner_type' => $facilityType,
                            ]
                        );
                    }
                }
            }

            if ($request->has('relative_name') && is_array($request->relative_name)) {
                foreach ($request->relative_name as $id => $name) {
                    $relative_phone = $request->input("relative_phone.$id");
                    $relation = $request->input("relation.$id");
                    if ($name && $relative_phone && $relation) {
                        DriverRelative::create([
                            'driver_id' => $user->id,
                            'name' => $name,
                            'phone' => $relative_phone,
                            'relation' => $relation,
                        ]);
                    }
                }
            }

            if ($request->has('file_name') && is_array($request->file_name)) {
                $uploadedFiles = $request->file('files', []);
                foreach ($request->file_name as $index => $name) {
                    $file = $uploadedFiles[$index] ?? null;
                    if ($file && $file->isValid() && $name) {
                        $uploadedPath = uploadFile($file, 'public/driver_files');
                        DriverFile::create([
                            'driver_id' => $user->id,
                            'name' => $name,
                            'file' => $uploadedPath
                        ]);
                    }
                }
            }

            Account::create([
                "accountable_id" => $user->id,
                "accountable_type" => User::class,
            ]);

            $user->syncRoles("Driver");

            $states = State::select('id')->get();
            $defaultMerchantDeliveryBonuses = Setting::where('key', 'default_driver_delivery_bonuses')->first()->value ?? 1;
            $defaultMerchantPickupyBonuses = Setting::where('key', 'default_driver_pickup_bonuses')->first()->value ?? 1;

            foreach ($states as $state) {
                DriverBonus::create([
                    'driver_id' => $user->id,
                    'state_id' => $state->id,
                    'delivery_bonus' => $defaultMerchantDeliveryBonuses,
                    'pickup_bonus' => $defaultMerchantPickupyBonuses,
                ]);
            }

            DB::commit();
            activityLog('driver created',"new driver added with username: {$user->username}");

            $notificationContent = "🚗 Welcome to Parcel Express!\n" .
                "👋 Hello {$user->name},\n" .
                "✅ Your driver account has been successfully created.\n" .
                "🔑 You can now log in using your email and password.\n" .
                "📱 Download our driver app to get started!";

            create_notification(
                $user,
                "🚗 Welcome to Parcel Express!",
                $notificationContent,
                [
                    'driver_id' => $user->driver->id,
                    'driver_name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->driver->phone ?? 'Not provided',
                    'company' => $user->driver->company->name ?? 'Not assigned',
                    'timestamp' => now()->toDateTimeString(),
                    'priority' => 'high',
                    'action_url' => '/driver/app-download'
                ],
                'driver_account_created',
                false
            );
        } catch (QueryException $e) {
            DB::rollBack();
            return sendResponse("Error Occured.", [], false, [$e->getMessage()], 422);
        }

        return sendResponse("Driver created successfully.", [$user->load('roles', 'driver.owner')]);
    }


    /**
     * Get all users
     *
     * @OA\Get(
     *   path="/users/all",
     *   tags={"WMS"},
     *   summary="Get all users",
     *   description="Retrieve all users without pagination",
     *   operationId="getAllUsers",
     *   security={
     *     {"bearerAuth": {}}
     *   },
     *   @OA\Response(
     *     response=200,
     *     description="Users retrieved successfully",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="All Users"),
     *       @OA\Property(
     *         property="data",
     *         type="array",
     *         @OA\Items(
     *             @OA\Property(property="id", type="integer", format="int64"),
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="email", type="string"),
     *             @OA\Property(property="roles", type="array", @OA\Items(type="object"))
     *         )
     *       )
     *     )
     *   )
     * )
     */
    public function all()
    {
        return sendResponse("All Users", new UserResource(User::byOwner()->get()));
    }
    /**
     * Get all drivers
     *
     * @OA\Get(
     *   path="/users/get-all-drivers",
     *   tags={"WMS"},
     *   summary="Get all drivers",
     *   description="Retrieve list of all drivers with basic information",
     *   operationId="getAllDrivers",
     *   security={
     *     {"bearerAuth": {}}
     *   },
     *   @OA\Response(
     *     response=200,
     *     description="Drivers retrieved successfully",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="Drivers"),
     *       @OA\Property(
     *         property="data",
     *         type="array",
     *         @OA\Items(
     *             @OA\Property(property="id", type="integer", format="int64"),
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="driver", type="object", @OA\Property(property="phone", type="string"))
     *         )
     *       )
     *     )
     *   )
     * )
     */
    public function getAllDrivers()
    {
        return sendResponse("Drivers", new UserResource(User::byOwner()->role("Driver")->select('id', 'name')->with('driver:user_id,country_code,phone')->get()));
    }
public function getAllDriversWithBonuses(DriverService $driverService)
{
    $warehouse_id = facility()->id;
    $accountableClasses = facility()->type;

    $drivers = User::where('owner_id', $warehouse_id)
        ->where('owner_type', $accountableClasses)
        ->role('Driver')
        ->select('id', 'name')
        ->with(['driver:user_id,country_code,phone'])
        ->get()
        ->map(function ($driver) use ($driverService) {

            $totalBonus = (float) Transaction::where('to_id', $driver->id)
                ->where('to_type', User::class)
                ->where('type', 'bonus_credit')
                ->whereNull('settled_at')
                ->sum('amount');

            $isPaid = 0;
            $totalSettlementDue = $driverService->calculateSettlementDue(
                $driver,
                null,
                null,
                $isPaid
            );

            return [
                'id' => $driver->id,
                'name' => $driver->name,
                'country_code' => optional($driver->driver)->country_code,
                'phone' => optional($driver->driver)->phone,
                'total_bonus' => $totalBonus,
                'total_settlement_due' => $totalSettlementDue,
            ];
        });

    return sendResponse("Drivers", $drivers);
}

    public function getAllMerchantsWithBalances()
    {
        $warehouse_id=facility()->id;
        $accountableClasses = facility()->type;
        $hasValue = Schema::hasColumn('shipments', 'value');
        $hasReturnFee = Schema::hasColumn('shipments', 'return_fee');
        $deliveryFeeExpr = "COALESCE(o.delivery_fee,0)";
        $returnFeeExpr = $hasReturnFee ? "COALESCE(o.return_fee,0)" : "0";
        $feesExpr = "($deliveryFeeExpr + $returnFeeExpr)";
        $codExpr = "CASE
            WHEN UPPER(o.payment_type)='COD' THEN
                CASE WHEN $hasValue = 1 THEN COALESCE(o.value,0) ELSE COALESCE(o.total_cod,0) END
            ELSE 0
        END";
        $merchants = User::where('owner_id', $warehouse_id)
            ->where('owner_type', $accountableClasses)
            ->role('Merchant')
            ->select('id', 'name')
            ->with(['merchant:user_id,country_code,contact_no'])
            ->get()
            ->map(function ($merchant) use ($codExpr, $deliveryFeeExpr, $returnFeeExpr) {
                $shipments = DB::table('shipments as o')
                    ->where(function ($q) use ($merchant) {
                        $q->where('o.merchant_id', $merchant->id)
                            ->orWhere('o.shipper_id', $merchant->id);
                    })
                    ->selectRaw("
                        SUM($codExpr) as total_cod,
                        SUM(CASE WHEN LOWER(o.fee_payer)='merchant' THEN $deliveryFeeExpr ELSE 0 END) as total_delivery_fee,
                        SUM(CASE WHEN LOWER(o.fee_payer)='merchant' THEN $returnFeeExpr ELSE 0 END) as total_return_fee
                    ")
                    ->first();
                $totalCod = (float) ($shipments->total_cod ?? 0);
                $totalFees = (float) ($shipments->total_delivery_fee ?? 0) + (float) ($shipments->total_return_fee ?? 0);

                $currentBalance = \App\Models\MerchantTransaction::forMerchant($merchant->id)
                ->completed()
                ->sum('amount');

                $totalBonus = (float) Transaction::where('to_id', $merchant->id)
                    ->where('to_type', User::class)
                    ->where('type', 'bonus_credit')
                    ->whereNull('settled_at')
                    ->sum('amount');
                return [
                    'id' => $merchant->id,
                    'name' => $merchant->name,
                    'country_code' => $merchant->merchant ? $merchant->merchant->country_code : null,
                    'phone' => $merchant->merchant ? $merchant->merchant->contact_no : null,
                    'total_bonus' => $totalBonus,
                    'current_balance' =>(float) $currentBalance,
                ];
            });

        return sendResponse("Merchants", $merchants);
    }
    /**
     * Get CRM agents
     *
     * @OA\Get(
     *   path="/users/crm-agents",
     *   tags={"WMS"},
     *   summary="Get CRM agents",
     *   description="Retrieve list of CRM agents",
     *   operationId="getCrmAgents",
     *   security={
     *     {"bearerAuth": {}}
     *   },
     *   @OA\Response(
     *     response=200,
     *     description="CRM agents retrieved successfully",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="CRM Agents"),
     *       @OA\Property(
     *         property="data",
     *         type="array",
     *         @OA\Items(
     *             @OA\Property(property="id", type="integer", format="int64"),
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="email", type="string"),
     *             @OA\Property(property="roles", type="array", @OA\Items(type="object"))
     *         )
     *       )
     *     )
     *   )
     * )
     */
    public function crm_agents()
    {
        return sendResponse("CRM Agents", new UserResource(User::all()));
    }

    public function employees()
    {
        $users = User::whereDoesntHave('roles', function ($query) {
            $query->whereIn('name', ['Driver', 'Shipper', 'Merchant']);
        })->get();

        return sendResponse("Employees", UserResource::collection($users));
    }

    public function warehouse_managers()
    {
        return sendResponse("Warehouse Managers", new UserResource(User::role("Warehouse Admin")->get()));
    }

    /**
     * Import users from Excel file
     *
     * @OA\Post(
     *   path="/users/import",
     *   tags={"WMS"},
     *   summary="Import users from Excel",
     *   description="Import users from Excel file with validation",
     *   operationId="importUsers",
     *   security={
     *     {"bearerAuth": {}}
     *   },
     *   @OA\RequestBody(
     *     description="Excel file containing users data",
     *     required=true,
     *     @OA\MediaType(
     *       mediaType="multipart/form-data",
     *       @OA\Schema(
     *         @OA\Property(
     *           property="file",
     *           description="Excel file",
     *           type="string",
     *           format="binary"
     *         )
     *       )
     *     )
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Users imported successfully",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="Users imported successfully."),
     *       @OA\Property(property="data", type="array", @OA\Items(type="string"))
     *     )
     *   ),
     *   @OA\Response(
     *     response=422,
     *     description="Validation error",
     *     @OA\JsonContent(
     *       @OA\Property(property="message", type="string", example="Error Occured."),
     *       @OA\Property(property="errors", type="array", @OA\Items(type="string"))
     *     )
     *   )
     * )
     */
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240'
        ]);

        DB::beginTransaction();
        try {
            $file = $request->file('file');
            $import = new UsersImport();
            Excel::import($import, $file);

            DB::commit();

            if (!empty($import->getErrors())) {
                return sendResponse(
                    "Import completed with some errors",
                    ['imported_count' => $import->getImportedCount(), 'errors' => $import->getErrors()],
                    $import->getErrors(),
                    207
                );
            }

            return sendResponse(
                "Users imported successfully",
                ['imported_count' => $import->getImportedCount()]
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return sendResponse("Error occurred during import", [], [$e->getMessage()], 422);
        }
    }
    /**
     * Export users to Excel file
     *
     * @OA\Get(
     *   path="/users/export",
     *   tags={"WMS"},
     *   summary="Export users to Excel",
     *   description="Export users data to Excel file",
     *   operationId="exportUsers",
     *   security={
     *     {"bearerAuth": {}}
     *   },
     *   @OA\Parameter(
     *     name="include_all",
     *     in="query",
     *     description="Include all users or only current page",
     *     required=false,
     *     @OA\Schema(
     *         type="boolean",
     *         default=false
     *     )
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Excel file download",
     *     @OA\MediaType(
     *       mediaType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
     *     )
     *   ),
     *   @OA\Response(
     *     response=422,
     *     description="Export error",
     *     @OA\JsonContent(
     *       @OA\Property(property="message", type="string", example="Error Occured."),
     *       @OA\Property(property="errors", type="array", @OA\Items(type="string"))
     *     )
     *   )
     * )
     */
    public function export(Request $request)
    {
        try {
            $includeAll = $request->boolean('include_all', false);

            if ($includeAll) {
                $users = User::byOwner()
                    ->whereDoesntHave('driver')
                    ->with('roles', 'owner', 'branch_users.branch', 'station_users.station', 'hub_users.hub')
                    ->orderBy('id', 'desc')
                    ->get();
            } else {
                // Get users from current page (similar to index method)
                $users = User::byOwner()
                    ->whereDoesntHave('driver')
                    ->with('roles', 'owner', 'branch_user.branch', 'station_user.station', 'hub_user.hub')
                    ->orderBy('id', 'desc')
                    ->paginate(50);
                $users = $users->getCollection();
            }

            $export = new UsersExport($users);

            $fileName = 'users_export_' . date('Y-m-d_H-i-s') . '.xlsx';

            return Excel::download($export, $fileName);
        } catch (\Exception $e) {
            return sendResponse("Error occurred during export", [], [$e->getMessage()], 422);
        }
    }
}
