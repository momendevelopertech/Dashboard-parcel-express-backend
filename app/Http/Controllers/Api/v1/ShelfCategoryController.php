<?php

namespace App\Http\Controllers\Api\v1;

use Illuminate\Http\Request;
use App\Models\ShelfCategory;
use App\Http\Controllers\Controller;
use Illuminate\Database\QueryException;
use App\Http\Resources\ShelfCategoryResource;
use App\Http\Requests\StoreShelfCategoryRequest;

/**
 * @OA\Tag(name="WMS", description="Warehouse Management System")
 * @OA\Controller(description="Shelf Category Management Controller")
 */
class ShelfCategoryController extends Controller
{
    /**
     * @OA\Get(
     *     path="/shelf-categories",
     *     summary="Get all shelf categories",
     *     description="Returns a list of shelf categories. Optionally search using the 'query' parameter.",
     *     tags={"WMS"},
     *     @OA\Parameter(
     *         name="query",
     *         in="query",
     *         description="Search query for category name",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Category reterived successfully."
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error occurred while fetching categories."
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function index()
    {
        $perPage = request()->input('per_page', 8);
        $categories = ShelfCategory::query();
        if (request()->has('search')) {
            $search = request()->input('search');
            $categories = $categories
                ->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($search) . '%'])
                ->orderBy('id', 'desc')
                ->get();
        } else {
            $categories = $categories->orderBy('id', 'desc')->paginate($perPage);
        }
        return sendResponse("Category reterived successfully.", new ShelfCategoryResource($categories), []);
    }

    /**
     * @OA\Post(
     *     path="/shelf-categories/store",
     *     summary="Create a new shelf category",
     *     description="Creates a new shelf category.",
     *     tags={"WMS"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", description="Name of the category", example="Category Name"),
     *         ),
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Category created successfully."
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error occurred while creating Category."
     *     ),
     *     security={{"bearerAuth": {}}},
     * )
     */
    public function store(StoreShelfCategoryRequest $request)
    {
        try {
            $validatedData = $request->validated();
            $category = ShelfCategory::create([
                'name' => $validatedData['name'],
                'barcode' => generateCategoryfBarcode(),
            ]);
            activityLog('shelf category create',"new shelf category created called {$category->name}");

            return sendResponse("Category created successfully.", new ShelfCategoryResource($category));
        } catch (QueryException $e) {
            return sendResponse("Error occurred while creating Category.", [], [$e->getMessage()], 422);
        }
    }



    /**
     * @OA\Post(
     *     path="/shelf-categories/update",
     *     summary="Update an existing shelf category",
     *     description="Updates an existing shelf category.",
     *     tags={"WMS"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", description="ID of the category to update"),
     *             @OA\Property(property="name", type="string", description="New name of the category"),
     *         ),
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Category updated successfully."
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error occurred while updating category."
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function update(Request $request)
    {
        try {
            $category = ShelfCategory::findOrFail($request->id);
            $category->name = $request->name;
            $category->save();
            activityLog('shelf category update',"shelf category updated called {$category->name}");
            return sendResponse("Category updated successfully.", new ShelfCategoryResource($category));
        } catch (QueryException $e) {
            return sendResponse("Error occurred while updating category.", [], [$e->getMessage()], 422);
        }
    }

    /**
     * @OA\Get(
     *     path="/shelf-categories/getSingle",
     *     summary="Get a single shelf category",
     *     description="Returns a single shelf category by ID.",
     *     tags={"WMS"},
     *     @OA\Parameter(
     *         name="id",
     *         in="query",
     *         description="ID of the category to retrieve",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Category fetched successfully."
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Category not found."
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error occurred while fetching the shelf."
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function getSingle()
    {
        try {
            $categoryId = request()->id;

            $category = ShelfCategory::where('id', $categoryId)
                ->first();

            if (!$category) {
                return sendResponse("Category not found.", [], false, [], 404);
            }
        } catch (QueryException $e) {
            return sendResponse("Error occurred while fetching the shelf.", [], false, [$e->getMessage()], 422);
        }

        // Return the shelf details
        return sendResponse("Category fetched successfully.", new ShelfCategoryResource($category));
    }
    /**
     * @OA\Post(
     *     path="/shelf-categories/delete",
     *     summary="Delete a shelf category",
     *     description="Deletes a shelf category.",
     *     tags={"WMS"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", description="ID of the category to delete"),
     *         ),
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Category deleted successfully."
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error Occured."
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function delete(Request $request)
    {
        try {
            $category = ShelfCategory::findOrFail($request->id);
            $category->delete();
            activityLog('shelf category delete',"shelf category deleted called {$category->name}");
        } catch (QueryException $e) {
            return sendResponse("Error Occured.", [], [$e->getMessage()], 422);
        }
        return sendResponse("Category deleted successfully.", []);
    }

    /**
     * @OA\Get(
     *     path="/shelf-categories/all",
     *     summary="Get all shelf categories",
     *     description="Returns all shelf categories.",
     *     tags={"WMS"},
     *     @OA\Response(
     *         response=200,
     *         description="Categories"
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function all()
    {
        return sendResponse("Categories", new ShelfCategoryResource(ShelfCategory::all()));
    }

    /**
     * @OA\Get(
     *     path="/shelf-categories/printCategory",
     *     summary="Print a shelf category",
     *     description="Prints a shelf category.",
     *     tags={"WMS"},
     *     @OA\Parameter(
     *         name="id",
     *         in="query",
     *         description="ID of the category to print",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *      @OA\Response(
     *         response=200,
     *         description="Category print"
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function printCategory()
    {

        $id = request('id');
        $category = ShelfCategory::findOrFail($id);

        $html = view('printShelf', compact('category'))->render();
        return response($html);

    }
}
