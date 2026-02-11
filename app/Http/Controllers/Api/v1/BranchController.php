<?php

namespace App\Http\Controllers\Api\v1;
use App\Http\Controllers\Controller;


use App\Models\Branch;
use Database\Seeders\FacilityRoleSeeder;
use Illuminate\Http\Request;
use App\Http\Resources\BranchResource;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * @OA\Tag(
 *     name="WMS",
 *     description="Warehouse Management System API Endpoints"
 * )
 */
/**
 * @OA\Schema(
 *     schema="Branch",
 *     type="object",
 *     @OA\Property(property="id", type="integer", format="int64"),
 *     @OA\Property(property="name", type="string"),
 *     @OA\Property(property="location", type="string"),
 *     @OA\Property(property="contact_number", type="string", nullable=true),
 *     @OA\Property(property="station", type="object",
 *         @OA\Property(property="id", type="integer", format="int64"),
 *         @OA\Property(property="name", type="string"),
 *         @OA\Property(property="hub", type="object",
 *             @OA\Property(property="name", type="string")
 *         )
 *     ),
 *     @OA\Property(property="lat", type="number", format="float"),
 *     @OA\Property(property="lng", type="number", format="float"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
class BranchController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/branches",
     *     tags={"Branches"},
     *     summary="List all branches",
     *     description="Get paginated list of branches with optional search",
     *     operationId="getBranchesList",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="query",
     *         in="query",
     *         description="Search term for branch name",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Branches retrieved successfully."),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", format="int64"),
     *                     @OA\Property(property="name", type="string"),
     *                     @OA\Property(property="location", type="string"),
     *                     @OA\Property(property="contact_number", type="string", nullable=true),
     *                     @OA\Property(
     *                         property="station",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", format="int64"),
     *                         @OA\Property(property="name", type="string"),
     *                         @OA\Property(
     *                             property="hub",
     *                             type="object",
     *                             @OA\Property(property="name", type="string")
     *                         )
     *                     ),
     *                     @OA\Property(property="lat", type="number", format="float"),
     *                     @OA\Property(property="lng", type="number", format="float"),
     *                     @OA\Property(property="created_at", type="string", format="date-time"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     )
     * )
     */
    public function index()
    {
        $perPage = request()->query('per_page', 8);
        $branches = Branch::query();
        if (request()->has('query')) {
            $query = request()->input('query');
            $branches = $branches
                ->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($query) . '%'])
                ->orderBy('id', 'desc')
                ->get();
        } else {
            $branches = $branches->with('station.hub')
                ->orderBy('id', 'desc')->paginate($perPage);
        }

        return sendResponse("Branches retrieved successfully.", new BranchResource(resource: $branches), []);
    }

    /**
     * Create a new branch
     *
     * @OA\Post(
     *   path="/branches/store",
     *   tags={"WMS"},
     *   summary="Create a new branch",
     *   description="Create a new branch with specified details",
     *   operationId="createBranch",
     *   security={
     *     {"bearerAuth": {}}
     *   },
     *   @OA\RequestBody(
     *     required=true,
     *     description="Branch creation data",
     *     @OA\JsonContent(
     *       required={"station_id", "hub_id", "name", "country_id", "governorate_id", "state_id", "lat", "lng", "location", "address"},
     *       @OA\Property(property="station_id", type="integer", example=1),
     *       @OA\Property(property="hub_id", type="integer", example=1),
     *       @OA\Property(property="name", type="string", example="Main Branch"),
     *       @OA\Property(property="country_id", type="integer", example=1),
     *       @OA\Property(property="governorate_id", type="integer", example=1),
     *       @OA\Property(property="state_id", type="integer", example=1),
     *       @OA\Property(property="lat", type="number", format="float", example=37.7749),
     *       @OA\Property(property="lng", type="number", format="float", example=-122.4194),
     *       @OA\Property(property="location", type="string", example="Central Location"),
     *       @OA\Property(property="address", type="string", example="Street 1, Building 2"),
     *       @OA\Property(property="contact_number", type="string", nullable=true, example="+1234567890")
     *     )
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Branch created successfully",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="Branch created successfully."),
     *       @OA\Property(
     *         property="data",
     *         type="object",
     *         @OA\Property(property="id", type="integer", format="int64"),
     *         @OA\Property(property="name", type="string"),
     *         @OA\Property(property="location", type="string"),
     *         @OA\Property(property="contact_number", type="string", nullable=true),
     *         @OA\Property(property="station", type="object",
     *             @OA\Property(property="id", type="integer", format="int64"),
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="hub", type="object",
     *                 @OA\Property(property="name", type="string")
     *             )
     *         ),
     *         @OA\Property(property="lat", type="number", format="float"),
     *         @OA\Property(property="lng", type="number", format="float"),
     *         @OA\Property(property="created_at", type="string", format="date-time"),
     *         @OA\Property(property="updated_at", type="string", format="date-time")
     *       )
     *     )
     *   ),
     *   @OA\Response(
     *     response=422,
     *     description="Validation error",
     *     @OA\JsonContent(
     *         @OA\Property(property="message", type="string", example="Error Occurred."),
     *         @OA\Property(property="errors", type="array", @OA\Items(type="string"))
     *     )
     *   )
     * )
     */




    public function store(Request $request)
    {
        $validated = $request->validate([
            'station_id' => 'required|exists:stations,id',
            'hub_id' => 'required|exists:hubs,id',
            'name' => 'required|string|max:255',
            'country_id' => 'required',
            'governorate_id' => 'required',
            'state_id' => 'required',
            'lat' => 'required',
            'lng' => 'required',
            'location' => 'required|string',
            'address' => 'required|string',
        ]);
        DB::beginTransaction();
        try {
            $branch = Branch::create($request->all());

            FacilityRoleSeeder::createRoles(Branch::class, $branch->id);
            activityLog('branch create',"new branch created called {$branch->name}");
            DB::commit();
        } catch (QueryException $e) {
            DB::rollBack();
            return sendResponse("Error Occurred.", [], [$e->getMessage()], 422);
        }

        return sendResponse("Branch created successfully.", new BranchResource($branch));
    }

    /**
     * Get specific branch details
     *
     * @OA\Get(
     *   path="/branches/{id}",
     *   tags={"WMS"},
     *   summary="Get specific branch details",
     *   description="Get detailed information about a specific branch",
     *   operationId="getBranchDetails",
     *   security={
     *     {"bearerAuth": {}}
     *   },
     *   @OA\Parameter(
     *     name="id",
     *     in="path",
     *     description="Branch ID to fetch",
     *     required=true,
     *     @OA\Schema(
     *         type="integer"
     *     )
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Branch fetched successfully",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="Branch fetched successfully."),
     *       @OA\Property(
     *         property="data",
     *         type="object",
     *         @OA\Property(property="id", type="integer", format="int64"),
     *         @OA\Property(property="name", type="string"),
     *         @OA\Property(property="location", type="string"),
     *         @OA\Property(property="contact_number", type="string", nullable=true),
     *         @OA\Property(property="station", type="object",
     *             @OA\Property(property="id", type="integer", format="int64"),
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="hub", type="object",
     *                 @OA\Property(property="name", type="string")
     *             )
     *         ),
     *         @OA\Property(property="lat", type="number", format="float"),
     *         @OA\Property(property="lng", type="number", format="float"),
     *         @OA\Property(property="created_at", type="string", format="date-time"),
     *         @OA\Property(property="updated_at", type="string", format="date-time")
     *       )
     *     )
     *   ),
     *   @OA\Response(
     *     response=422,
     *     description="Branch not found",
     *     @OA\JsonContent(
     *         @OA\Property(property="message", type="string", example="Error Occurred."),
     *         @OA\Property(property="errors", type="array", @OA\Items(type="string"))
     *     )
     *   )
     * )
     */
    public function show($id)
    {
        $branch = Branch::with([
            'country',
            'governorate',
            'state',
            'place',
            'city',
            'hub',
            'station'
        ])->find($id);

        if (!$branch) {
            return response()->json(['message' => 'branch not found.'], 404);
        }

        return sendResponse("Branch fetched successfully.", new BranchResource($branch));
    }

    /**
     * Update branch information
     *
     * @OA\Post(
     *   path="/branches/update",
     *   tags={"WMS"},
     *   summary="Update branch information",
     *   description="Update existing branch's details",
     *   operationId="updateBranch",
     *   security={
     *     {"bearerAuth": {}}
     *   },
     *   @OA\RequestBody(
     *     required=true,
     *     description="Branch update data",
     *     @OA\JsonContent(
     *       required={"id"},
     *       @OA\Property(property="id", type="integer", example=1),
     *       @OA\Property(property="station_id", type="integer", nullable=true, example=1),
     *       @OA\Property(property="name", type="string", nullable=true, example="Main Branch"),
     *       @OA\Property(property="location", type="string", nullable=true, example="Central Location"),
     *       @OA\Property(property="contact_number", type="string", nullable=true, example="+1234567890")
     *     )
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Branch updated successfully",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="Branch updated successfully."),
     *       @OA\Property(
     *         property="data",
     *         type="object",
     *         @OA\Property(property="id", type="integer", format="int64"),
     *         @OA\Property(property="name", type="string"),
     *         @OA\Property(property="location", type="string"),
     *         @OA\Property(property="contact_number", type="string", nullable=true),
     *         @OA\Property(property="station", type="object",
     *             @OA\Property(property="id", type="integer", format="int64"),
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="hub", type="object",
     *                 @OA\Property(property="name", type="string")
     *             )
     *         ),
     *         @OA\Property(property="lat", type="number", format="float"),
     *         @OA\Property(property="lng", type="number", format="float"),
     *         @OA\Property(property="created_at", type="string", format="date-time"),
     *         @OA\Property(property="updated_at", type="string", format="date-time")
     *       )
     *     )
     *   ),
     *   @OA\Response(
     *     response=404,
     *     description="Branch not found",
     *     @OA\JsonContent(
     *         @OA\Property(property="message", type="string", example="Branch not found")
     *     )
     *   )
     * )
     */
    public function update(Request $request)
    {
        $branch = Branch::find($request->id);

        if (!$branch) {
            return response()->json(['message' => 'Branch not found.'], 404);
        }

        $validated = $request->validate([
            'id' => 'sometimes|exists:branches,id',
            'station_id' => 'sometimes|exists:stations,id',
            'hub_id' => 'sometimes|exists:hubs,id',
            'name' => 'sometimes|string|max:255',
            'address' => 'sometimes|string',
            'location' => 'sometimes|string',
            'lat' => 'sometimes|numeric',
            'lng' => 'sometimes|numeric',
        ]);

        $branch->update($validated);
        activityLog('branch update',"branch updated called {$branch->name}");

        return sendResponse("Branch updated successfully.", new BranchResource($branch));
    }

    /**
     * Delete branch
     *
     * @OA\Post(
     *   path="/branches/delete",
     *   tags={"WMS"},
     *   summary="Delete branch",
     *   description="Delete a branch by its ID",
     *   operationId="deleteBranch",
     *   security={
     *     {"bearerAuth": {}}
     *   },
     *   @OA\RequestBody(
     *     required=true,
     *     description="Branch deletion data",
     *     @OA\JsonContent(
     *       required={"id"},
     *       @OA\Property(property="id", type="integer", example=1)
     *     )
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Branch deleted successfully",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="Branch deleted successfully.")
     *     )
     *   ),
     *   @OA\Response(
     *     response=404,
     *     description="Branch not found",
     *     @OA\JsonContent(
     *         @OA\Property(property="message", type="string", example="Branch not found")
     *     )
     *   )
     * )
     */
    public function delete(Request $request)
    {
        $branch = Branch::find($request->id);

        if (facility("id") == $branch->id && facility("type") == Branch::class) {
            $request->user()->currentAccessToken()->delete();
        }


        if (!$branch) {
            return sendResponse("Error Occurred.", [], ["Branch not found."], 404);
        }

        try {
            Branch::where('id', $request->id)->delete();
            activityLog('branch delete',"branch deleted called {$branch->name}");
        } catch (QueryException $e) {
            return sendResponse("Error Occurred.", [], ["Branch not found."], 404);
        }

        return sendResponse("Branch deleted successfully.", []);
    }
    /**
     * Get branches by station
     *
     * @OA\Get(
     *   path="/branches/getBranchesByStation",
     *   tags={"WMS"},
     *   summary="Get branches by station",
     *   description="Retrieve branches associated with a specific station",
     *   operationId="getBranchesByStation",
     *   security={
     *     {"bearerAuth": {}}
     *   },
     *   @OA\Parameter(
     *     name="stationId",
     *     in="query",
     *     description="ID of the station to fetch branches for",
     *     required=true,
     *     @OA\Schema(
     *         type="integer"
     *     )
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Branches retrieved successfully",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="Branches fetched successfully."),
     *       @OA\Property(
     *         property="data",
     *         type="array",
     *         @OA\Items(
     *             @OA\Property(property="id", type="integer", format="int64"),
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="location", type="string"),
     *             @OA\Property(property="contact_number", type="string", nullable=true),
     *             @OA\Property(property="station", type="object",
     *                 @OA\Property(property="id", type="integer", format="int64"),
     *                 @OA\Property(property="name", type="string"),
     *                 @OA\Property(property="hub", type="object",
     *                     @OA\Property(property="name", type="string")
     *                 )
     *             ),
     *             @OA\Property(property="lat", type="number", format="float"),
     *             @OA\Property(property="lng", type="number", format="float"),
     *             @OA\Property(property="created_at", type="string", format="date-time"),
     *             @OA\Property(property="updated_at", type="string", format="date-time")
     *         )
     *       )
     *     )
     *   ),
     *   @OA\Response(
     *     response=400,
     *     description="Station ID is required",
     *     @OA\JsonContent(
     *         @OA\Property(property="success", type="boolean", example=false),
     *         @OA\Property(property="message", type="string", example="Station ID is required")
     *     )
     *   ),
     *   @OA\Response(
     *     response=404,
     *     description="No branches found",
     *     @OA\JsonContent(
     *         @OA\Property(property="success", type="boolean", example=false),
     *         @OA\Property(property="message", type="string", example="No branches found for the given station.")
     *     )
     *   ),
     *   @OA\Response(
     *     response=500,
     *     description="Error fetching branches",
     *     @OA\JsonContent(
     *         @OA\Property(property="success", type="boolean", example=false),
     *         @OA\Property(property="message", type="string", example="Error fetching branches"),
     *         @OA\Property(property="error", type="string")
     *     )
     *   )
     * )
     */
    public function getBranchesByStation()
    {
        $stationId = request('stationId');

        if (!$stationId) {
            return response()->json([
                'success' => false,
                'message' => 'Station ID is required',
            ], 400);
        }

        try {
            $branches = Branch::where('station_id', $stationId)->get();

            if ($branches->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No branches found for the given station.',
                ], 404);
            }

            return sendResponse("Branches fetched successfully.", BranchResource::collection($branches));
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching branches',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get all branches
     *
     * @OA\Get(
     *   path="/branches/all",
     *   tags={"WMS"},
     *   summary="Get all branches",
     *   description="Retrieve all branches without pagination",
     *   operationId="getAllBranches",
     *   security={
     *     {"bearerAuth": {}}
     *   },
     *   @OA\Response(
     *     response=200,
     *     description="Branches retrieved successfully",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="Branch"),
     *       @OA\Property(
     *         property="data",
     *         type="array",
     *         @OA\Items(
     *             @OA\Property(property="id", type="integer", format="int64"),
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="location", type="string"),
     *             @OA\Property(property="contact_number", type="string", nullable=true),
     *             @OA\Property(property="station", type="object",
     *                 @OA\Property(property="id", type="integer", format="int64"),
     *                 @OA\Property(property="name", type="string"),
     *                 @OA\Property(property="hub", type="object",
     *                     @OA\Property(property="name", type="string")
     *                 )
     *             ),
     *             @OA\Property(property="lat", type="number", format="float"),
     *             @OA\Property(property="lng", type="number", format="float"),
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
        $branches = Branch::get();
        return sendResponse("Branch", new BranchResource($branches));
    }
}
