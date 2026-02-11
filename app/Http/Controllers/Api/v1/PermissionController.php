<?php

namespace App\Http\Controllers\Api\v1;
use App\Http\Controllers\Controller;


use Carbon\Carbon;
use App\Models\Permission;
use Illuminate\Http\Request;
use App\Exports\GeneralExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Database\QueryException;
use App\Http\Resources\PermissionResource;
use App\Http\Requests\StorePermissionRequest;
use App\Http\Requests\UpdatePermissionRequest;

/**
 * @OA\Tag(
 *     name="WMS",
 *     description="Warehouse Management System related endpoints"
 * )
 */
class PermissionController extends Controller
{
    /**
     * List permissions
     *
     * @OA\Get(
     *   path="/permissions",
     *   tags={"WMS"},
     *   summary="Get paginated list of permissions with search",
     *   description="Get a list of permissions with optional search query",
     *   operationId="getPermissionsList",
     *   security={
     *     {"bearerAuth": {}}
     *   },
     *   @OA\Parameter(
     *     name="query",
     *     in="query",
     *     description="Search term for permission name",
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
     *       @OA\Property(property="message", type="string", example="Permissions retrieved successfully."),
     *       @OA\Property(
     *         property="data",
     *         type="array",
     *         @OA\Items(
     *             @OA\Property(property="id", type="integer", format="int64"),
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="guard_name", type="string", example="web"),
     *             @OA\Property(property="children", type="array", @OA\Items(
     *                 @OA\Property(property="id", type="integer", format="int64"),
     *                 @OA\Property(property="name", type="string"),
     *                 @OA\Property(property="guard_name", type="string", example="web")
     *             ))
     *         )
     *       )
     *     )
     *   )
     * )
     */
    public function index(Request $request)
    {
        $perPage = $request->query('per_page', 8);
        $query = $request->input("query");

        $permissions = Permission::byOwner()
            ->whereNotNull('parent_id')
            ->with('children');

        if ($query) {
            $permissions->where("name", "LIKE", "%{$query}%");
        }

        $permissions = $permissions->orderBy('name', 'asc')
            ->paginate($perPage);

        return sendResponse("Permissions retrieved successfully.", new PermissionResource($permissions), []);
    }


    public function store(StorePermissionRequest $request)
    {
        $request->validated();
        try {
            $permission = Permission::create([
                'name' => $request->name,
                'guard_name' => 'web'
            ]);
        } catch (QueryException $e) {
            return sendResponse("Error Occured.", [], [$e->getMessage()]);
        }
        return sendResponse("Permission created successfully.", new PermissionResource($permission));
    }

    public function update(UpdatePermissionRequest $request)
    {
        $request->validated();
        try {
            $permission = Permission::find($request->id);
            $permission->update(['name' => $request->name ? $request->name : $permission->name]);
        } catch (QueryException $e) {
            return sendResponse("Error Occured.", [], [$e->getMessage()]);
        }

        return sendResponse("Permission updated successfully.", new PermissionResource($permission));
    }

    public function delete(Request $request)
    {
        try {
            Permission::where('id', $request->id)->delete();
        } catch (QueryException $e) {
            return sendResponse("Error Occured.", [], [$e->getMessage()]);
        }

        return sendResponse("Permission deleted successfully.", []);
    }
    /**
     * Get all permissions
     *
     * @OA\Get(
     *   path="/permissions/all",
     *   tags={"WMS"},
     *   summary="Get all permissions without pagination",
     *   description="Retrieve all permissions without pagination",
     *   operationId="getAllPermissions",
     *   security={
     *     {"bearerAuth": {}}
     *   },
     *   @OA\Response(
     *     response=200,
     *     description="Permissions retrieved successfully",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="Permissions retrieved successfully."),
     *       @OA\Property(
     *         property="data",
     *         type="array",
     *         @OA\Items(
     *             @OA\Property(property="id", type="integer", format="int64"),
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="guard_name", type="string", example="web"),
     *             @OA\Property(property="children", type="array", @OA\Items(
     *                 @OA\Property(property="id", type="integer", format="int64"),
     *                 @OA\Property(property="name", type="string"),
     *                 @OA\Property(property="guard_name", type="string", example="web")
     *             ))
     *         )
     *       )
     *     )
     *   )
     * )
     */
    public function all()
    {
        $permissions = Permission::query()
            ->withoutGlobalScopes()
            ->whereNull('parent_id')
            ->with('children')
            ->orderBy('name', 'asc')
            ->get();

        $collection = collect($permissions);
        $uniquePermissions = $collection->unique('name')->values()->all();

        $uniquePermissions = $this->ShipmentPermissionSpecificPage($uniquePermissions);
        $uniquePermissions = $this->CustomerCreatedShipmentPermissionSpecificPage($uniquePermissions);
        $uniquePermissions = $this->ReprintShipmentPermissionSpecificPage($uniquePermissions);

        return sendResponse("Permissions retrieved successfully.", $permissions, []);
    }

    public function ShipmentPermissionSpecificPage($permissions)
    {
        // Ensure specific Shipment children (print, import, import_template, export) are under 'Shipment' and at the tail
        $list = collect($permissions);

        $shipmentParent = $list->first(function ($perm) {
            return isset($perm->name) && $perm->name === 'Shipment';
        });

        if (!$shipmentParent) {
            return $list->values()->all();
        }

        $tailNames = [
            'Shipment print',
            'Shipment import',
            'Shipment import_template',
            'Shipment export',
        ];

        $children = $shipmentParent->relationLoaded('children')
            ? ($shipmentParent->children instanceof \Illuminate\Support\Collection ? $shipmentParent->children : collect($shipmentParent->children))
            : collect();

        // Attach any missing tail items by fetching from DB (if available for this type)
        $ensuredTail = collect($tailNames)->map(function ($name) use ($children, $shipmentParent) {
            $existing = $children->firstWhere('name', $name);
            if ($existing) {
                return $existing;
            }
            return Permission::where('name', $name)
                ->where('guard_name', $shipmentParent->guard_name ?? 'web')
                ->where(function ($q) use ($shipmentParent) {
                    if (isset($shipmentParent->type)) {
                        $q->where('type', $shipmentParent->type);
                    }
                })
                ->first();
        })->filter();

        // Rebuild children: non-tail first, then tail in shipment
        $nonTail = $children->filter(function ($child) use ($tailNames) {
            return !in_array($child->name, $tailNames, true);
        })->values();

        $shipmentParent->setRelation('children', $nonTail->merge($ensuredTail)->values());

        // Replace the modified parent back into the list
        $updated = $list->map(function ($perm) use ($shipmentParent) {
            return ($perm->id === $shipmentParent->id) ? $shipmentParent : $perm;
        });

        return $updated->values()->all();
    }

    public function CustomerCreatedShipmentPermissionSpecificPage($permissions)
    {
        $list = collect($permissions);

        $parent = $list->first(function ($perm) {
            return isset($perm->name) && $perm->name === 'Customer Created Shipments';
        });

        if (!$parent) {
            return $list->values()->all();
        }

        $shipmentedChildrenNames = [
            'Customer Created Shipments access',
            'Customer Created Shipments create',
            'Customer Created Shipments update',
            'Customer Created Shipments delete',
        ];

        $children = $parent->relationLoaded('children')
            ? ($parent->children instanceof \Illuminate\Support\Collection ? $parent->children : collect($parent->children))
            : collect();

        $ensured = collect($shipmentedChildrenNames)->map(function ($name) use ($children, $parent) {
            $existing = $children->firstWhere('name', $name);
            if ($existing) {
                return $existing;
            }
            return Permission::where('name', $name)
                ->where('guard_name', $parent->guard_name ?? 'web')
                ->where(function ($q) use ($parent) {
                    if (isset($parent->type)) {
                        $q->where('type', $parent->type);
                    }
                })
                ->first();
        })->filter();

        // keep existing non-specified children at the beginning in current shipment
        $nonSpecified = $children->filter(function ($child) use ($shipmentedChildrenNames) {
            return !in_array($child->name, $shipmentedChildrenNames, true);
        })->values();

        $parent->setRelation('children', $nonSpecified->merge($ensured)->values());

        $updated = $list->map(function ($perm) use ($parent) {
            return ($perm->id === $parent->id) ? $parent : $perm;
        });

        return $updated->values()->all();
    }

    public function ReprintShipmentPermissionSpecificPage($permissions)
    {
        $list = collect($permissions);

        $parent = $list->first(function ($perm) {
            return isset($perm->name) && $perm->name === 'Reprint';
        });

        if (!$parent) {
            return $list->values()->all();
        }

        $shipmentedChildrenNames = [
            'Reprint access',
        ];

        $children = $parent->relationLoaded('children')
            ? ($parent->children instanceof \Illuminate\Support\Collection ? $parent->children : collect($parent->children))
            : collect();

        $ensured = collect($shipmentedChildrenNames)->map(function ($name) use ($children, $parent) {
            $existing = $children->firstWhere('name', $name);
            if ($existing) {
                return $existing;
            }
            return Permission::where('name', $name)
                ->where('guard_name', $parent->guard_name ?? 'web')
                ->where(function ($q) use ($parent) {
                    if (isset($parent->type)) {
                        $q->where('type', $parent->type);
                    }
                })
                ->first();
        })->filter();

        $nonSpecified = $children->filter(function ($child) use ($shipmentedChildrenNames) {
            return !in_array($child->name, $shipmentedChildrenNames, true);
        })->values();

        $parent->setRelation('children', $nonSpecified->merge($ensured)->values());

        $updated = $list->map(function ($perm) use ($parent) {
            return ($perm->id === $parent->id) ? $parent : $perm;
        });

        return $updated->values()->all();
    }

    /**
     * Export permissions
     *
     * @OA\Post(
     *   path="/permissions/export",
     *   tags={"WMS"},
     *   summary="Export permissions data",
     *   description="Export permissions data in CSV or PDF format with selectable columns and date filters",
     *   operationId="exportPermissions",
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
     *       type="object",
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

        $query = Permission::with(array_unique($relationships));

        $query = Permission::query();
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

        $permissions = $query->get();

        if ($format === 'pdf') {
            $html = view('exports.general_export', [
                'name' => "Permissions",
                'rows' => $permissions,
                'columns' => $columns,
                'date' => now()->format('Y-m-d H:i:s')
            ])->render();
            return response()->json(['html' => $html]);
        }

        $name = 'permissions.' . $format;
        return Excel::download(new GeneralExport($permissions, $columns), $name);
    }
}
