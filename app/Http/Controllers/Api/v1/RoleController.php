<?php

namespace App\Http\Controllers\Api\v1;
use App\Http\Controllers\Controller;


use App\Exports\GeneralExport;
use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use App\Http\Resources\RoleResource;
use App\Http\Resources\UserRoleResource;
use App\Imports\RolesImport;
use App\Models\Hub;
use App\Models\Role;
use App\Models\Station;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\Models\Permission;

/**
 * @OA\Tag(
 *     name="WMS",
 *     description="Warehouse Management System related endpoints"
 * )
 */
class RoleController extends Controller
{
    /**
     * List roles
     *
     * @OA\Get(
     *   path="/roles",
     *   tags={"WMS"},
     *   summary="Get paginated list of roles with search",
     *   description="Get a list of roles with optional search query",
     *   operationId="getRolesList",
     *   security={
     *     {"bearerAuth": {}}
     *   },
     *   @OA\Parameter(
     *     name="query",
     *     in="query",
     *     description="Search term for role name",
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
     *       @OA\Property(property="message", type="string", example="Roles reterived successfully."),
     *       @OA\Property(
     *         property="data",
     *         type="array",
     *         @OA\Items(
     *             @OA\Property(property="id", type="integer", format="int64"),
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="guard_name", type="string", example="web"),
     *             @OA\Property(property="permissions", type="array", @OA\Items(
     *                 @OA\Property(property="id", type="integer", format="int64"),
     *                 @OA\Property(property="name", type="string"),
     *                 @OA\Property(property="guard_name", type="string", example="web")
     *             )),
     *             @OA\Property(property="created_at", type="string", format="date-time"),
     *             @OA\Property(property="updated_at", type="string", format="date-time")
     *         )
     *       )
     *     )
     *   )
     * )
     */
    public function index()
    {
        $roles = Role::query();
        $roles = Role::byUser();
        $perPage = request()->query('per_page', 8);
        if (request()->has('query')) {
            $query = request()->input('query');
            $roles = $roles
                ->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($query) . '%'])
                ->where('name', '!=', 'Super Admin')
                ->with('permissions')
                ->orderBy('id', 'desc')
                ->get();
        } else {
            $roles = $roles
                ->where('name', '!=', 'Super Admin')
                ->with('permissions')
                ->orderBy('id', 'desc')
                ->paginate($perPage);
        }

        return sendResponse("Roles reterived successfully.", new RoleResource($roles), []);
    }

    /**
     * Create role
     *
     * @OA\Post(
     *   path="/roles/store",
     *   tags={"WMS"},
     *   summary="Create a new role",
     *   description="Create a new role with specified details",
     *   operationId="createRole",
     *   security={
     *     {"bearerAuth": {}}
     *   },
     *   @OA\RequestBody(
     *     description="Role creation data",
     *     required=true,
     *     @OA\JsonContent(
     *       required={"name"},
     *       @OA\Property(property="name", type="string", example="New Role"),
     *       @OA\Property(
     *         property="permissions",
     *         type="array",
     *         items={
     *           @OA\Property(type="integer", format="int64")
     *         },
     *         example={1, 2, 3}
     *       )
     *     )
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Role created successfully",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="Role created successfully."),
     *       @OA\Property(
     *         property="data",
     *         type="array",
     *         @OA\Items(
     *             @OA\Property(property="id", type="integer", format="int64"),
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="guard_name", type="string", example="web"),
     *             @OA\Property(property="permissions", type="array", @OA\Items(
     *                 @OA\Property(property="id", type="integer", format="int64"),
     *                 @OA\Property(property="name", type="string"),
     *                 @OA\Property(property="guard_name", type="string", example="web")
     *             )),
     *             @OA\Property(property="created_at", type="string", format="date-time"),
     *             @OA\Property(property="updated_at", type="string", format="date-time")
     *         )
     *       )
     *     )
     *   ),
     *   @OA\Response(
     *     response=400,
     *     description="Validation error",
     *     @OA\JsonContent(
     *       @OA\Property(property="message", type="string", example="Error Occured."),
     *       @OA\Property(property="errors", type="array", @OA\Items(type="string"))
     *     )
     *   )
     * )
     */

    public function store(StoreRoleRequest $request)
    {
        // Validate request
        $request->validated();

        return DB::transaction(function () use ($request) {
            try {
                // Determine the facility and hub
                $facilityType = facility('type');
                $facilityId = facility('id');

                if ($facilityType === Station::class) {
                    // If facility is a station, get its hub
                    $station = Station::with('hub')->find($facilityId);
                    $hubId = $station?->hub_id;

                    if (!$hubId) {
                        return sendResponse("Station does not belong to any hub.", [], [], 422);
                    }

                    $roleableType = Hub::class;
                    $roleableId = $hubId;
                } else {
                    $roleableType = $facilityType;
                    $roleableId = $facilityId;
                }

                // Check for duplicate role name for this hub/facility
                $existingRole = Role::where([
                    'roleable_id' => $roleableId,
                    'roleable_type' => $roleableType,
                    'name' => $request->name,
                ])->first();

                if ($existingRole) {
                    return sendResponse("Role with this name already exists for this hub/facility.", [], [], 422);
                }

                // Create the role
                try {
                    $role = Role::create([
                        'roleable_id' => $roleableId,
                        'roleable_type' => $roleableType,
                        'name' => $request->name,
                        'company_id' => Auth::id(),
                        'guard_name' => 'web',
                    ]);
                } catch (\Illuminate\Database\QueryException $e) {
                    if (str_contains($e->getMessage(), 'unique_role_per_facility')) {
                        return sendResponse("Role with this name already exists for this hub/station.", [], [], 422);
                    }
                    throw $e;
                }

                // Sync permissions
                $permissions = $request->permissions ? Permission::whereIn('id', $request->permissions)->get() : [];
                $role->syncPermissions($permissions);

                // If the role is for a hub, propagate it to all stations
                if ($roleableType === Hub::class) {
                    $hub = Hub::with('stations')->find($roleableId);

                    foreach ($hub->stations as $station) {
                        $stationRole = Role::firstOrCreate([
                            'roleable_id' => $station->id,
                            'roleable_type' => Station::class,
                            'name' => $request->name,
                        ], [
                            'company_id' => Auth::id(),
                            'guard_name' => 'web',
                        ]);

                        $stationRole->syncPermissions($permissions);
                    }
                }

                // Log activity
                activityLog('role created', "New role created: {$role->name}");

                return sendResponse("Role created successfully.", [$role->load('permissions')]);

            } catch (\Illuminate\Database\QueryException $e) {
                return sendResponse("Error occurred.", [], [$e->getMessage()], 400);
            }
        });
    }


    /**
     * Update role
     *
     * @OA\Post(
     *   path="/roles/update",
     *   tags={"WMS"},
     *   summary="Update role details",
     *   description="Update an existing role's details",
     *   operationId="updateRole",
     *   security={
     *     {"bearerAuth": {}}
     *   },
     *   @OA\RequestBody(
     *     description="Role update data",
     *     required=true,
     *     @OA\JsonContent(
     *       required={"id"},
     *       @OA\Property(property="id", type="integer", format="int64"),
     *       @OA\Property(property="name", type="string", example="Updated Role"),
     *       @OA\Property(
     *         property="permissions",
     *         type="array",
     *         items={
     *           @OA\Property(type="integer", format="int64")
     *         },
     *         example={1, 2, 3}
     *       )
     *     )
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Role updated successfully",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="Role updated successfully."),
     *       @OA\Property(
     *         property="data",
     *         type="array",
     *         @OA\Items(
     *             @OA\Property(property="id", type="integer", format="int64"),
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="guard_name", type="string", example="web"),
     *             @OA\Property(property="permissions", type="array", @OA\Items(
     *                 @OA\Property(property="id", type="integer", format="int64"),
     *                 @OA\Property(property="name", type="string"),
     *                 @OA\Property(property="guard_name", type="string", example="web")
     *             )),
     *             @OA\Property(property="created_at", type="string", format="date-time"),
     *             @OA\Property(property="updated_at", type="string", format="date-time")
     *         )
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
    public function update(UpdateRoleRequest $request)
    {
        $request->validated();

        try {
            $role = Role::findOrFail($request->id);

            // نحدث فقط الاسم
            $role->update([
                'name' => $request->name,
            ]);

            // نتأكد أن permissions جاية كـ array (match UserController approach)
            $permissionIds = array_filter((array) $request->input('permissions', []));

            // نفلتر null أو IDs غير موجودة
            $validPermissionIds = Permission::whereIn('id', $permissionIds)->pluck('id')->toArray();

            // نعمل sync
            $role->syncPermissions($validPermissionIds);
            activityLog('role updated', "role updated called {$role->name}");
            return sendResponse("Role updated successfully.", [
                $role->load('permissions')
            ]);
        } catch (\Exception $e) {
            return sendResponse("Error Occured.", [], false, [$e->getMessage()]);
        }
    }


    /**
     * Get role details
     *
     * @OA\Get(
     *   path="/roles/show/{id}",
     *   tags={"WMS"},
     *   summary="Get role details",
     *   description="Get detailed information about a specific role",
     *   operationId="getRoleDetails",
     *   security={
     *     {"bearerAuth": {}}
     *   },
     *   @OA\Parameter(
     *     name="id",
     *     in="path",
     *     description="Role ID to fetch",
     *     required=true,
     *     @OA\Schema(
     *         type="integer"
     *     )
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Role retrieved successfully",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="Role"),
     *       @OA\Property(
     *         property="data",
     *         type="object",
     *         @OA\Property(property="id", type="integer", format="int64"),
     *         @OA\Property(property="name", type="string"),
     *         @OA\Property(property="guard_name", type="string", example="web"),
     *         @OA\Property(property="permissions", type="array", @OA\Items(
     *             @OA\Property(property="id", type="integer", format="int64"),
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="guard_name", type="string", example="web")
     *         )),
     *         @OA\Property(property="created_at", type="string", format="date-time"),
     *         @OA\Property(property="updated_at", type="string", format="date-time")
     *       )
     *     )
     *   )
     * )
     */
    public function show($id)
    {
        $role = Role::with('permissions')->find($id);
        return sendResponse("Role", new UserRoleResource($role));
    }

    /**
     * Delete role
     *
     * @OA\Post(
     *   path="/roles/delete",
     *   tags={"WMS"},
     *   summary="Delete a role",
     *   description="Delete a role by its ID",
     *   operationId="deleteRole",
     *   security={
     *     {"bearerAuth": {}}
     *   },
     *   @OA\RequestBody(
     *     description="Role deletion data",
     *     required=true,
     *     @OA\JsonContent(
     *       required={"id"},
     *       @OA\Property(property="id", type="integer", format="int64")
     *     )
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Role deleted successfully",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="Role deleted successfully."),
     *       @OA\Property(
     *         property="data",
     *         type="array",
     *         items={
     *           @OA\Property(type="string")
     *         }
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
    public function delete(Request $request)
    {
        try {
            $role=Role::findOrFail($request->input('id'));
            $role->delete();
            activityLog('role deleted', "role deleted called {$role->name}");
        } catch (QueryException $e) {
            return sendResponse("Error Occured.", [], [$e->getMessage()]);
        }
        return sendResponse("Role deleted successfully.", []);
    }

    public function perms()
    {
        return sendResponse("Permissions", Permission::byOwner());
    }

    /**
     * Export roles
     *
     * @OA\Post(
     *   path="/roles/export",
     *   tags={"WMS"},
     *   summary="Export roles data",
     *   description="Export roles data in CSV or PDF format with selectable columns and date filters",
     *   operationId="exportRoles",
     *   security={
     *     {"bearerAuth": {}}
     *   },
     *   @OA\RequestBody(
     *     description="Export parameters",
     *     required=true,
     *     @OA\JsonContent(
     *       required={"format", "columns"},
     *       @OA\Property(
     *         property="format",
     *         type="string",
     *         enum={"csv", "pdf"},
     *         example="csv"
     *       ),
     *       @OA\Property(
     *         property="columns",
     *         type="array",
     *         items={
     *           @OA\Property(type="string", enum={"id", "name", "created_at", "updated_at"})
     *         },
     *         example={"id", "name", "created_at"}
     *       ),
     *       @OA\Property(
     *         property="from_date",
     *         type="string",
     *         format="date",
     *         description="Start date for filtering",
     *         example="2025-01-01"
     *       ),
     *       @OA\Property(
     *         property="to_date",
     *         type="string",
     *         format="date",
     *         description="End date for filtering",
     *         example="2025-12-31"
     *       )
     *     )
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="File download",
     *     @OA\Header(
     *       header="Content-Disposition",
     *       description="File name",
     *       @OA\Schema(type="string")
     *     )
     *   ),
     *   @OA\Response(
     *     response=422,
     *     description="Invalid format specified",
     *     @OA\JsonContent(
     *       @OA\Property(property="message", type="string", example="Invalid format specified"),
     *       @OA\Property(property="success", type="boolean", example=false),
     *       @OA\Property(
     *         property="data",
     *         type="array",
     *         items={
     *           @OA\Property(type="string")
     *         }
     *       ),
     *       @OA\Property(property="errors", type="boolean", example=false)
     *     )
     *   )
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
            'name',
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

        $query = Role::with(array_unique($relationships));

        $query = Role::query();
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

        $role = $query->get();

        if ($format === 'pdf') {
            $html = view('exports.general_export', [
                'name' => "Roles",
                'rows' => $role,
                'columns' => $columns,
                'date' => now()->format('Y-m-d H:i:s')
            ])->render();
            return response()->json(['html' => $html]);
        }

        $name = 'role.' . $format;
        return Excel::download(new GeneralExport($role, $columns), $name);
    }

    /**
     * Get all roles
     *
     * @OA\Get(
     *   path="/roles/all",
     *   tags={"WMS"},
     *   summary="Get all roles",
     *   description="Retrieve all roles without pagination",
     *   operationId="getAllRoles",
     *   security={
     *     {"bearerAuth": {}}
     *   },
     *   @OA\Response(
     *     response=200,
     *     description="Roles retrieved successfully",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="Roles"),
     *       @OA\Property(
     *         property="data",
     *         type="array",
     *         @OA\Items(
     *             @OA\Property(property="id", type="integer", format="int64"),
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="guard_name", type="string", example="web"),
     *             @OA\Property(property="permissions", type="array", @OA\Items(
     *                 @OA\Property(property="id", type="integer", format="int64"),
     *                 @OA\Property(property="name", type="string"),
     *                 @OA\Property(property="guard_name", type="string", example="web")
     *             )),
     *             @OA\Property(property="created_at", type="string", format="date-time"),
     *             @OA\Property(property="updated_at", type="string", format="date-time")
     *         )
     *       )
     *     )
     *   )
     * )
     */
    public function all()
    {
        // return sendResponse("Roles", Role::byUser()->get());
        return sendResponse("Roles", Role::all());
    }

    /**
     * Import roles from Excel file
     *
     * @OA\Post(
     *   path="/roles/import",
     *   tags={"WMS"},
     *   summary="Import roles from Excel",
     *   description="Import roles from Excel file with validation",
     *   operationId="importRoles",
     *   security={
     *     {"bearerAuth": {}}
     *   },
     *   @OA\RequestBody(
     *     description="Excel file containing roles data",
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
     *     description="Roles imported successfully",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="Roles imported successfully."),
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
            $import = new RolesImport();
            Excel::import($import, $file);

            DB::commit();

            if (!empty($import->getErrors())) {
                return sendResponse(
                    "Import completed with some errors",
                    ['imported_count' => $import->getImportedCount(), 'errors' => $import->getErrors()],
                    $import->getErrors(),
                    207 // Partial content
                );
            }

            return sendResponse(
                "Roles imported successfully",
                ['imported_count' => $import->getImportedCount()]
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return sendResponse("Error occurred during import", [], [$e->getMessage()], 422);
        }
    }

    /**
     * Export roles to Excel file
     *
     * @OA\Get(
     *   path="/roles/export",
     *   tags={"WMS"},
     *   summary="Export roles to Excel",
     *   description="Export roles data to Excel file",
     *   operationId="exportRoles",
     *   security={
     *     {"bearerAuth": {}}
     *   },
     *   @OA\Parameter(
     *     name="include_all",
     *     in="query",
     *     description="Include all roles or only current page",
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
    // public function export(Request $request)
    // {
    //     try {
    //         $includeAll = $request->boolean('include_all', false);

    //         if ($includeAll) {
    //             $roles = Role::byUser()
    //                 ->with('permissions')
    //                 ->orderBy('id', 'desc')
    //                 ->get();
    //         } else {
    //             // Get roles from current page
    //             $roles = Role::byUser()
    //                 ->with('permissions')
    //                 ->orderBy('id', 'desc')
    //                 ->paginate(50);
    //             $roles = $roles->getCollection();
    //         }

    //         $export = new RolesExport($roles);

    //         $fileName = 'roles_export_' . date('Y-m-d_H-i-s') . '.xlsx';

    //         return Excel::download($export, $fileName);
    //     } catch (\Exception $e) {
    //         return sendResponse("Error occurred during export", [], [$e->getMessage()], 422);
    //     }
    // }
}
