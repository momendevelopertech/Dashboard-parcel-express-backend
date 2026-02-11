<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Requests\StorePlaceRequest;
use App\Http\Requests\UpdatePlaceRequest;
use App\Http\Resources\PlaceResource;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use App\Models\Place;
use App\Http\Controllers\Controller;


/**
 * @OA\Tag(
 *     name="WMS",
 *     description="Warehouse Management System related endpoints"
 * )
 */
class PlaceController extends Controller
{
    /**
     * List places
     * 
     * @OA\Get(
     *   path="/places",
     *   tags={"WMS"},
     *   summary="Get paginated list of places with search",
     *   description="Get a list of places with optional search query",
     *   operationId="getPlacesList",
     *   security={
     *     {"bearerAuth": {}}
     *   },
     *   @OA\Parameter(
     *     name="query",
     *     in="query",
     *     description="Search term for place name (English or Arabic)",
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
     *       @OA\Property(property="message", type="string", example="Places retrieved successfully."),
     *       @OA\Property(
     *         property="data",
     *         type="array",
     *         @OA\Items(
     *             @OA\Property(property="id", type="integer", format="int64"),
     *             @OA\Property(property="en_name", type="string"),
     *             @OA\Property(property="ar_name", type="string"),
     *             @OA\Property(property="state", type="object"),
     *             @OA\Property(property="created_at", type="string", format="date-time"),
     *             @OA\Property(property="updated_at", type="string", format="date-time")
     *         )
     *       )
     *     )
     *   )
     * )
     */
    /**
     * @OA\Get(
     *     path="/api/places/index",
     *     summary="Get list of places with pagination",
     *     tags={"Places"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="query",
     *         in="query",
     *         description="Search query for place names (English or Arabic)",
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
     *         description="Places retrieved successfully"
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
        
        $places = Place::query()
            ->with([
                'state:id,en_name,ar_name,governorate_id,country_id',
                'state.governorate:id,en_name,ar_name,country_id',
                'state.country:id,name,is_active'
            ])
            ->when($searchQuery, function ($query) use ($searchQuery) {
                return $query->where(function($q) use ($searchQuery) {
                    $q->where('en_name', 'like', "%{$searchQuery}%")
                      ->orWhere('ar_name', 'like', "%{$searchQuery}%");
                });
            })
            ->when(!request()->has('show_inactive'), function ($query) {
                return $query->where('isActive', 1);
            })
            ->orderBy('id', 'desc')
            ->paginate($perPage);
            
        if ($places->isEmpty()) {
            return sendResponse("No places found.", [], false, ['No places found']);
        }

        return sendResponse(
            "Places retrieved successfully.",
            $places,
            true,
            []
        );
    }


    /**
     * Create place
     * 
     * @OA\Post(
     *   path="/places/store",
     *   tags={"WMS"},
     *   summary="Create a new place",
     *   description="Create a new place with specified details",
     *   operationId="createPlace",
     *   security={
     *     {"bearerAuth": {}}
     *   },
     *   @OA\RequestBody(
     *     description="Place creation data",
     *     required=true,
     *     @OA\JsonContent(
     *       required={
     *         "en_name",
     *         "ar_name",
     *         "state_id"
     *       },
     *       @OA\Property(property="en_name", type="string", example="Place Name", maxLength=255),
     *       @OA\Property(property="ar_name", type="string", example="اسم المكان", maxLength=255),
     *       @OA\Property(property="state_id", type="integer", example=1)
     *     )
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Place created successfully",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="Place created successfully."),
     *       @OA\Property(
     *         property="data",
     *         type="object",
     *         @OA\Property(property="id", type="integer", format="int64"),
     *         @OA\Property(property="en_name", type="string"),
     *         @OA\Property(property="ar_name", type="string"),
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
     *       @OA\Property(property="message", type="string", example="Error occurred."),
     *       @OA\Property(property="errors", type="array", @OA\Items(type="string"))
     *     )
     *   )
     * )
     */
    public function store(StorePlaceRequest $request)
    {
        $request->validated();
        try {
            $data = $request->all();
            if (!isset($data['isActive'])) {
                $data['isActive'] = 1;
            }
            $place = Place::create($data);
            activityLog('place create',"new place created called {$place->en_name}");
        } catch (QueryException $e) {
            return sendResponse("Error occurred.", [], [$e->getMessage()], 422);
        }
        return sendResponse("Place created successfully.", new PlaceResource($place));
    }

    /**
     * Update place
     * 
     * @OA\Post(
     *   path="/places/update",
     *   tags={"WMS"},
     *   summary="Update place details",
     *   description="Update an existing place's details",
     *   operationId="updatePlace",
     *   security={
     *     {"bearerAuth": {}}
     *   },
     *   @OA\RequestBody(
     *     description="Place update data",
     *     required=true,
     *     @OA\JsonContent(
     *       required={
     *         "id",
     *         "en_name",
     *         "ar_name"
     *       },
     *       @OA\Property(property="id", type="integer", format="int64", example=1),
     *       @OA\Property(property="en_name", type="string", example="Updated Place Name", maxLength=255),
     *       @OA\Property(property="ar_name", type="string", example="اسم المكان المحدث", maxLength=255),
     *       @OA\Property(property="state_id", type="integer", example=1)
     *     )
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Place updated successfully",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="Place updated successfully."),
     *       @OA\Property(
     *         property="data",
     *         type="object",
     *         @OA\Property(property="id", type="integer", format="int64"),
     *         @OA\Property(property="en_name", type="string"),
     *         @OA\Property(property="ar_name", type="string"),
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
     *       @OA\Property(property="message", type="string", example="Error occurred."),
     *       @OA\Property(property="errors", type="array", @OA\Items(type="string"))
     *     )
     *   )
     * )
     */
    public function update(UpdatePlaceRequest $request)
    {
        $request->validated();
        try {
            $place = Place::find($request->id);
            $data = $request->all();
            $place->update($data);
            activityLog('place update',"place updated called {$place->en_name}");
        } catch (QueryException $e) {
            return sendResponse("Error occurred.", [], [$e->getMessage()], 422);
        }
        return sendResponse("Place updated successfully.", new PlaceResource($place));
    }

    /**
     * Delete place
     * 
     * @OA\Post(
     *   path="/places/delete",
     *   tags={"WMS"},
     *   summary="Delete a place",
     *   description="Delete a place by its ID",
     *   operationId="deletePlace",
     *   security={
     *     {"bearerAuth": {}}
     *   },
     *   @OA\RequestBody(
     *     description="Place deletion data",
     *     required=true,
     *     @OA\JsonContent(
     *       required={"id"},
     *       @OA\Property(property="id", type="integer", format="int64")
     *     )
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Place deleted successfully",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="Place deleted successfully."),
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
     *       @OA\Property(property="message", type="string", example="Error occurred."),
     *       @OA\Property(property="errors", type="array", @OA\Items(type="string"))
     *     )
     *   )
     * )
     */
    public function delete(Request $request)
    {
        try {
           $place =Place::findOrFail($request->id);
           $place->delete();
           activityLog('place delete',"place deleted called {$place->en_name}");
        } catch (QueryException $e) {
            return sendResponse("Error occurred.", [], [$e->getMessage()], 422);
        }
        return sendResponse("Place deleted successfully.", []);
    }

    /**
     * Get all places
     * 
     * @OA\Get(
     *   path="/places/all",
     *   tags={"WMS"},
     *   summary="Get all places",
     *   description="Retrieve all places without pagination",
     *   operationId="getAllPlaces",
     *   security={
     *     {"bearerAuth": {}}
     *   },
     *   @OA\Response(
     *     response=200,
     *     description="Places retrieved successfully",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="Places"),
     *       @OA\Property(
     *         property="data",
     *         type="array",
     *         @OA\Items(
     *             @OA\Property(property="id", type="integer", format="int64"),
     *             @OA\Property(property="en_name", type="string"),
     *             @OA\Property(property="ar_name", type="string"),
     *             @OA\Property(property="state_id", type="integer"),
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
    $places = Place::query();

    if ($request->timestamp) {
        $places = $places->where('updated_at', '<', $request->timestamp);
        if ($places->count() === 0) {
            return sendResponse("Places", []);
        }
    }
    $places->where('isActive', 1);

    return sendResponse(
        "Places",
        $places->select(
            "id",
            "en_name",
            "ar_name",
            "state_id",
            "updated_at",
            "isActive"
        )->get()
    );
}

}
