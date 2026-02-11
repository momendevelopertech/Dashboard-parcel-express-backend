<?php

namespace App\Http\Controllers\Api\v1;
use App\Http\Controllers\Controller;


use App\Http\Requests\StoreHierarchyLevelRequest;
use App\Http\Requests\UpdateHierarchyLevelRequest;
use App\Http\Resources\HierarchyLevelResource;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use App\Models\HierarchyLevel;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * @OA\Tag(name="HRM", description="Human Resource Management")
 * @OA\Controller(description="Hierarchy Level Management")
 */
class HierarchyLevelController extends Controller
{
    /**
     * @OA\Get(
     *     path="/hierarchy_levels",
     *     summary="Get a list of hierarchy levels",
     *     description="Retrieve a list of hierarchy levels.  Supports pagination and searching.",
     *     tags={"HRM"},
     *     @OA\Parameter(
     *         name="query",
     *         in="query",
     *         description="Search query for role_name",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Hierarchy Level retrieved successfully."
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function index()
    {
        $hierarchyLevels = HierarchyLevel::query();
        if (request()->filled('query')) {
            $query = request()->input('query');
            $results = $hierarchyLevels
                ->whereRaw('LOWER(role_name) LIKE ?', ['%' . strtolower($query) . '%'])
                ->orderBy('id', 'desc')
                ->get();
            $page = request()->input('page', 1);
            $perPage = 8;
            $total = $results->count();
            $paginated = new LengthAwarePaginator(
                $results->forPage($page, $perPage),
                $total,
                $perPage,
                $page,
                ['path' => request()->url(), 'query' => request()->query()]
            );
            return sendResponse(
                "Hierarchy Level retrieved successfully.",
                HierarchyLevelResource::collection($paginated)->response()->getData(),
                []
            );
        } else {
            $hierarchyLevels = $hierarchyLevels
                ->orderBy('id', 'desc')
                ->paginate(8);
            return sendResponse(
                "Hierarchy Level retrieved successfully.",
                HierarchyLevelResource::collection($hierarchyLevels)->response()->getData(),
                []
            );
        }
    }

    /**
     * @OA\Post(
     *     path="/hierarchy_levels/store",
     *     summary="Create a new hierarchy level",
     *     tags={"HRM"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="role_name", type="string", description="Role name", example="Manager"),
     *             @OA\Property(property="level", type="integer", description="Level", example=1),
     *             @OA\Property(property="description", type="string", description="Description", example="Manages team members"),
     *         ),
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Hierarchy Level created successfully."
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error or Error occurred while creating hierarchy level."
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function store(StoreHierarchyLevelRequest $request)
    {
        try {
            $hierarchyLevel = HierarchyLevel::create($request->validated());
            return sendResponse("Hierarchy Level created successfully.", new HierarchyLevelResource($hierarchyLevel));
        } catch (QueryException $e) {
            return sendResponse("Error occurred while creating hierarchy level.", [], [$e->getMessage()], 422);
        }
    }

    /**
     * @OA\Post(
     *     path="/hierarchy_levels/update",
     *     summary="Update a hierarchy level",
     *     tags={"HRM"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", description="ID of the hierarchy level to update"),
     *             @OA\Property(property="role_name", type="string", description="Role name"),
     *             @OA\Property(property="level", type="integer", description="Level"),
     *             @OA\Property(property="description", type="string", description="Description"),
     *         ),
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Hierarchy Level updated successfully."
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error or Error occurred while updating hierarchy level."
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function update(UpdateHierarchyLevelRequest $request)
    {
        try {
            $hierarchyLevel = HierarchyLevel::findOrFail($request->id);
            $hierarchyLevel->update($request->validated());
            return sendResponse("Hierarchy Level updated successfully.", new HierarchyLevelResource($hierarchyLevel));
        } catch (QueryException $e) {
            return sendResponse("Error occurred while updating hierarchy level.", [], [$e->getMessage()], 422);
        }
    }

    /**
     * @OA\Post(
     *     path="/hierarchy_levels/delete",
     *     summary="Delete a hierarchy level",
     *     tags={"HRM"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", description="ID of the hierarchy level to delete"),
     *         ),
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Hierarchy Level deleted successfully."
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error occurred while deleting hierarchy level."
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function delete(Request $request)
    {
        try {
            HierarchyLevel::where('id', $request->id)->delete();
        } catch (QueryException $e) {
            return sendResponse("Error Occured.", [], [$e->getMessage()], 422);
        }
        return sendResponse("Hierarchy Level deleted successfully.", []);
    }

    /**
     * @OA\Get(
     *     path="/hierarchy_levels/all",
     *     summary="Get all hierarchy levels",
     *     description="Retrieve all hierarchy levels.",
     *     tags={"HRM"},
     *     @OA\Response(
     *         response=200,
     *         description="Hierarchy Levels"
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function all()
    {
        return sendResponse("Hierarchy Levels", new HierarchyLevelResource(HierarchyLevel::all()));
    }
}