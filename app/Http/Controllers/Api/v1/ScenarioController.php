<?php

namespace App\Http\Controllers\Api\v1;
use App\Http\Controllers\Controller;


use App\Models\Scenario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Helpers\helpers;
use Illuminate\Support\Facades\Log;

/**
 * @OA\Tag(name="Other", description="Scenario management")
 */
class ScenarioController extends Controller
{
    /**
     * @OA\Get(
     *     path="/scenarios",
     *     summary="Get a list of scenarios",
     *     description="Retrieve a list of scenarios with optional filtering and pagination.",
     *     tags={"Other"},
     *     @OA\Parameter(
     *         name="category",
     *         in="query",
     *         description="Filter scenarios by category",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="tags",
     *         in="query",
     *         description="Filter scenarios by tags (comma-separated)",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search scenarios by title, description, or category",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of scenarios per page",
     *         @OA\Schema(type="integer", default=15)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Scenarios retrieved successfully"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Failed to retrieve scenarios",
     *         @OA\JsonContent()
     *     ),
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function index(Request $request)
    {
        try {
            $query = Scenario::query();

            // Apply filters
            if ($request->has('category')) {
                $query->where('category', $request->category);
            }

            if ($request->has('tags')) {
                $tags = explode(',', $request->tags);
                $query->whereJsonContains('tags', $tags);
            }

            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('category', 'like', "%{$search}%");
                });
            }

            $scenarios = $query->paginate($request->get('per_page', 15));
            return sendResponse('Scenarios retrieved successfully', $scenarios);
        } catch (\Exception $e) {
            return sendResponse('Failed to retrieve scenarios', null, false, [$e->getMessage()], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/scenarios/{id}",
     *     summary="Get a scenario by ID",
     *     description="Retrieve a single scenario by its ID.",
     *     tags={"Other"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of the scenario",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Scenario retrieved successfully"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Scenario not found",
     *         @OA\JsonContent()
     *     ),
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function show($id)
    {
        try {
            $scenario = Scenario::findOrFail($id);
            return sendResponse('Scenario retrieved successfully', $scenario);
        } catch (\Exception $e) {
            return sendResponse('Scenario not found', null, false, [$e->getMessage()], 404);
        }
    }

    /**
     * @OA\Post(
     *     path="/scenarios",
     *     summary="Create a new scenario",
     *     description="Create a new scenario.",
     *     tags={"Other"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="title", type="string", description="Title of the scenario", example="My Scenario"),
     *             @OA\Property(property="description", type="string", description="Description of the scenario", example="This is my scenario"),
     *             @OA\Property(property="steps", type="array", description="Steps of the scenario", example="[]",
     *                 @OA\Items(type="object",
     *                     @OA\Property(property="name", type="string", description="Step name"),
     *                     @OA\Property(property="description", type="string", description="Step description")
     *                 )
     *             ),
     *             @OA\Property(property="tags", type="array", description="Tags for the scenario", example="[]",
     *                 @OA\Items(type="string", description="Tag name")
     *             ),
     *             @OA\Property(property="category", type="string", description="Category of the scenario", example="Category1")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Scenario created successfully"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Failed to create scenario",
     *         @OA\JsonContent()
     *     ),
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'description' => 'required|string',
                'steps' => 'nullable|array',
                'tags' => 'nullable|array',
                'category' => 'nullable|string|max:255'
            ]);

            $scenario = Scenario::create($validated);
            return sendResponse('Scenario created successfully', $scenario, true, [], 201);
        } catch (\Exception $e) {
            return sendResponse('Failed to create scenario', null, false, [$e->getMessage()], 422);
        }
    }

    /**
     * @OA\Put(
     *     path="/scenarios/{id}",
     *     summary="Update a scenario",
     *     description="Update an existing scenario.",
     *     tags={"Other"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of the scenario",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="title", type="string", description="Title of the scenario"),
     *             @OA\Property(property="description", type="string", description="Description of the scenario"),
     *             @OA\Property(property="steps", type="array", description="Steps of the scenario",
     *                 @OA\Items(type="object",
     *                     @OA\Property(property="name", type="string", description="Step name"),
     *                     @OA\Property(property="description", type="string", description="Step description")
     *                 )
     *             ),
     *             @OA\Property(property="tags", type="array", description="Tags for the scenario",
     *                 @OA\Items(type="string", description="Tag name")
     *             ),
     *             @OA\Property(property="category", type="string", description="Category of the scenario")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Scenario updated successfully"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Failed to update scenario",
     *         @OA\JsonContent()
     *     ),
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function update(Request $request, $id)
    {
        try {
            $scenario = Scenario::findOrFail($id);
            $validated = $request->validate([
                'title' => 'nullable|string|max:255',
                'description' => 'nullable|string',
                'steps' => 'nullable|array',
                'tags' => 'nullable|array',
                'category' => 'nullable|string|max:255'
            ]);

            // Log the incoming data
            Log::info('Updating scenario ' . $id . ' with data:', $validated);

            // Update the scenario
            $scenario->update($validated);

            // Refresh the model to get the updated data
            $scenario->refresh();

            // Log the updated data
            Log::info('Scenario updated successfully:', $scenario->toArray());

            return sendResponse('Scenario updated successfully', $scenario);
        } catch (\Exception $e) {
            Log::error('Failed to update scenario ' . $id . ':', [$e->getMessage()]);
            return sendResponse('Failed to update scenario', null, false, [$e->getMessage()], 422);
        }
    }

    /**
     * @OA\Post(
     *     path="/scenarios/delete",
     *     summary="Delete a scenario",
     *     description="Delete an existing scenario.",
     *     tags={"Other"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", description="ID of the scenario to delete")
     *         )
     *     ),
     *     @OA\Response(
     *         response=204,
     *         description="Scenario deleted successfully"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Failed to delete scenario",
     *         @OA\JsonContent()
     *     ),
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function destroy(Request $request)
    {
        try {
            $id = $request->input("id");
            $scenario = Scenario::findOrFail($id);
            $scenario->delete();
            return sendResponse('Scenario deleted successfully', null, true, [], 204);
        } catch (\Exception $e) {
            return sendResponse('Failed to delete scenario', null, false, [$e->getMessage()], 404);
        }
    }

    /**
     * @OA\Post(
     *     path="/scenarios/{id}/reviewed",
     *     summary="Mark a scenario as reviewed",
     *     description="Mark an existing scenario as reviewed.",
     *     tags={"Other"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of the scenario",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Scenario marked as reviewed"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Failed to mark scenario as reviewed",
     *         @OA\JsonContent()
     *     ),
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function markAsReviewed($id)
    {
        try {
            $scenario = Scenario::findOrFail($id);
            $scenario->is_reviewed = true;
            $scenario->save();
            return sendResponse('Scenario marked as reviewed', $scenario);
        } catch (\Exception $e) {
            return sendResponse('Failed to mark scenario as reviewed', null, false, [$e->getMessage()], 404);
        }
    }
}
