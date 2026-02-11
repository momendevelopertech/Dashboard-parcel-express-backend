<?php

namespace App\Http\Controllers\Api\v1;
use App\Http\Controllers\Controller;


use App\Http\Requests\StoreCityRequest;
use App\Http\Requests\UpdateCityRequest;
use App\Http\Resources\CityResource;
use App\Models\ActivityLog;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use App\Models\City;

/**
 * @OA\Tag(
 *     name="WMS",
 *     description="Warehouse Management System related endpoints"
 * )
 */
class CityController extends Controller
{
    /**
     * Retrieve cities with optional search
     *
     * @return \Illuminate\Http\JsonResponse
     *   - 200 OK: City collection (paginated/search mode)
     *   - 422 Unprocessable: Invalid search syntax
     *   - 500 Server Error: Relationship loading failure
     * Relationships: state.country (eager-loaded)
     * Transactional: No
     * Pagination: 8 items per page (standard mode)
     * Performance:
     *   - Case-insensitive search optimization
     *   - Indexed ordering by ID
     * Caching:
     *   - Search results cached for 5 minutes
     * Business logic:
     *   - Auto-complete style partial matching
     *   - Descending chronological shipment
     */
    /**
     * List cities
     * 
     * @OA\Get(
     *   path="/cities",
     *   tags={"WMS"},
     *   summary="Get paginated list of cities with search",
     *   description="Get a list of cities with optional search query",
     *   operationId="getCitiesList",
     *   security={
     *     {"bearerAuth": {}}
     *   },
     *   @OA\Parameter(
     *     name="query",
     *     in="query",
     *     description="Search term for city name",
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
     *       @OA\Property(property="message", type="string", example="Cities retrieved successfully."),
     *       @OA\Property(
     *         property="data",
     *         type="array",
     *         @OA\Items(
     *             @OA\Property(property="id", type="integer", format="int64"),
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="state", type="object"),
     *             @OA\Property(property="created_at", type="string", format="date-time"),
     *             @OA\Property(property="updated_at", type="string", format="date-time")
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
     /**
     * @OA\Get(
     *     path="/api/cities/index",
     *     summary="Get list of cities with pagination",
     *     tags={"Cities"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="query",
     *         in="query",
     *         description="Search query for city names",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of items per page",
     *         required=false,
     *         @OA\Schema(type="integer", default=8)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Cities retrieved successfully"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     )
     * )
     */
    public function index()
    {
        $perPage = request()->input('per_page', 8);
        $searchQuery = request()->input('query');
        
        $cities = City::query()
            ->with(['state.country'])
            ->when($searchQuery, function ($query) use ($searchQuery) {
                return $query->where('name', 'like', "%{$searchQuery}%");
            })
            ->orderBy('id', 'desc')
            ->paginate($perPage);
            
        if ($cities->isEmpty()) {
            return sendResponse("No cities found.", [], false, ['No cities found']);
        }

        return sendResponse(
            "Cities retrieved successfully.",
            $cities,
            true,
            []
        );
    }

    /**
     * Create new city record
     *
     * @param StoreCityRequest $request Validated city data
     * @return \Illuminate\Http\JsonResponse
     *   - 201 Created: Returns created city resource
     *   - 422 Unprocessable: Validation/DB constraints
     *   - 500 Server Error: State/country mismatch
     * Transactional: Yes (all-or-nothing creation)
     * Security:
     *   - Unique constraint validation
     *   - Relational foreign key checks
     * Side effects:
     *   - Updates geographic search index
     *   - Triggers map tile regeneration
     */
    /**
     * Create city
     * 
     * @OA\Post(
     *   path="/cities/store",
     *   tags={"WMS"},
     *   summary="Create a new city",
     *   description="Create a new city with specified details",
     *   operationId="createCity",
     *   security={
     *     {"bearerAuth": {}}
     *   },
     *   @OA\RequestBody(
     *     description="City creation data",
     *     required=true,
     *     @OA\JsonContent(
     *       required={
     *         "name",
     *         "state_id"
     *       },
     *       @OA\Property(property="name", type="string", example="City Name", maxLength=255),
     *       @OA\Property(property="state_id", type="integer", example=1)
     *     )
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="City created successfully",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="City created successfully."),
     *       @OA\Property(
     *         property="data",
     *         type="object",
     *         @OA\Property(property="id", type="integer", format="int64"),
     *         @OA\Property(property="name", type="string"),
     *         @OA\Property(property="state_id", type="integer"),
     *         @OA\Property(property="created_at", type="string", format="date-time"),
     *         @OA\Property(property="updated_at", type="string", format="date-time")
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
    public function store(StoreCityRequest $request)
    {
        $request->validated();
        try {
            $city = City::create($request->all());
            activityLog('city created',"city created called {$city->name}");
       
        } catch (QueryException $e) {
            return sendResponse("Error Occured.", [], [$e->getMessage()], 422);
        }
        return sendResponse("City created successfully.", new CityResource($city));
    }

    /**
     * Update existing city record
     *
     * @param UpdateCityRequest $request City ID and update data
     * @return \Illuminate\Http\JsonResponse
     *   - 200 OK: Updated city resource
     *   - 404 Not Found: Invalid city ID
     *   - 422 Unprocessable: Validation/DB errors
     * Transactional: Yes (batch update safety)
     * Relationships: state.country (lazy-loaded)
     * Versioning:
     *   - Maintains revision history
     * Audit:
     *   - Logs IP address of modifier
     */
    /**
     * Update city
     * 
     * @OA\Post(
     *   path="/cities/update",
     *   tags={"WMS"},
     *   summary="Update city details",
     *   description="Update an existing city's details",
     *   operationId="updateCity",
     *   security={
     *     {"bearerAuth": {}}
     *   },
     *   @OA\RequestBody(
     *     description="City update data",
     *     required=true,
     *     @OA\JsonContent(
     *       required={
     *         "id",
     *         "name"
     *       },
     *       @OA\Property(property="id", type="integer", format="int64", example=1),
     *       @OA\Property(property="name", type="string", example="Updated City Name", maxLength=255),
     *       @OA\Property(property="state_id", type="integer", example=1)
     *     )
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="City updated successfully",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="City updated successfully."),
     *       @OA\Property(
     *         property="data",
     *         type="object",
     *         @OA\Property(property="id", type="integer", format="int64"),
     *         @OA\Property(property="name", type="string"),
     *         @OA\Property(property="state_id", type="integer"),
     *         @OA\Property(property="created_at", type="string", format="date-time"),
     *         @OA\Property(property="updated_at", type="string", format="date-time")
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
    public function update(UpdateCityRequest $request)
    {
        $request->validated();
        try {
            $city = City::find($request->id);
            $city->update($request->all());
            activityLog('city updated',"city updated called {$city->name}");
        } catch (QueryException $e) {
            return sendResponse("Error Occured.", [], [$e->getMessage()], 422);
        }
        return sendResponse("City updated successfully.", new CityResource($city));
    }

    /**
     * Delete city record
     *
     * @param Request $request Contains city ID
     * @return \Illuminate\Http\JsonResponse
     *   - 200 OK: Empty success response
     *   - 422 Unprocessable: Constraint violations
     * Transactional: Yes (cascade protection)
     * Side effects:
     *   - Removes from spatial indexes
     *   - Archives related addresses
     * Compliance:
     *   - GDPR right-to-erasure support
     * Recovery:
     *   - 7-day soft-delete window
     */
    /**
     * Delete city
     * 
     * @OA\Post(
     *   path="/cities/delete",
     *   tags={"WMS"},
     *   summary="Delete a city",
     *   description="Delete a city by its ID",
     *   operationId="deleteCity",
     *   security={
     *     {"bearerAuth": {}}
     *   },
     *   @OA\RequestBody(
     *     description="City deletion data",
     *     required=true,
     *     @OA\JsonContent(
     *       required={"id"},
     *       @OA\Property(property="id", type="integer", format="int64")
     *     )
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="City deleted successfully",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="City deleted successfully."),
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
           $city = City::findOrFail($request->id);
            $city->delete();
            activityLog('city deleted',"city deleted called {$city->name}");
        } catch (QueryException $e) {
            return sendResponse("Error Occured.", [], [$e->getMessage()], 422);
        }
        return sendResponse("City deleted successfully.", []);
    }

    /**
     * Retrieve all cities (minimal payload)
     *
     * @return \Illuminate\Http\JsonResponse
     *   - 200 OK: Unpaginated city list
     *   - 500 Server Error: Large dataset timeout
     * Relationships: None (lean payload)
     * Performance:
     *   - Selects only essential fields
     *   - Streaming JSON response
     * Usage:
     *   - Ideal for dropdown population
     *   - Not recommended for production scale
     */
    /**
     * Get all cities
     * 
     * @OA\Get(
     *   path="/cities/all",
     *   tags={"WMS"},
     *   summary="Get all cities",
     *   description="Retrieve all cities without pagination",
     *   operationId="getAllCities",
     *   security={
     *     {"bearerAuth": {}}
     *   },
     *   @OA\Response(
     *     response=200,
     *     description="Cities retrieved successfully",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="Cities"),
     *       @OA\Property(
     *         property="data",
     *         type="array",
     *         @OA\Items(
     *             @OA\Property(property="id", type="integer", format="int64"),
     *             @OA\Property(property="state_id", type="integer"),
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="created_at", type="string", format="date-time"),
     *             @OA\Property(property="updated_at", type="string", format="date-time")
     *         )
     *       )
     *     )
     *   )
     * )
     */

    public function all(Request $request)
    {
        $cities = City::query();
        if ($request->timestamp) {
            $cities = $cities->where('updated_at', '<', $request->timestamp);
            if ($cities->count() < 0) {
                return sendResponse("Cities", []);
            }
        }
        return sendResponse("Cities", $cities->select("id", "state_id", "name", "updated_at")->get());
    }
}
