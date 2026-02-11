<?php

namespace App\Http\Controllers\Api\v1;
use App\Http\Controllers\Controller;


use App\Http\Requests\StoreEmployeeHierarchyRequest;
use App\Http\Requests\UpdateEmployeeHierarchyRequest;
use App\Http\Resources\EmployeeHierarchyResource;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use App\Models\EmployeeHierarchy;

/**
 * @OA\Tag(name="HRM", description="Employee Hierarchy Management")
 * @OA\Controller(description="Manage employee hierarchies")
 */
class EmployeeHierarchyController extends Controller
{
    /**
     * @OA\Get(
     *     path="/employee_hierarchies",
     *     summary="Get a list of employee hierarchies",
     *     description="Retrieves a list of employee hierarchies. If a query parameter is provided, it returns all records; otherwise, it paginates the results.",
     *     tags={"HRM"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Employee Hierarchy retrieved successfully",
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error occurred while retrieving employee hierarchy",
     *     )
     * )
     */
    public function index()
    {
        $employeeBranches = EmployeeHierarchy::query()->with(['approver', 'employee', 'hierarchyLevel']);

        if (request()->has('query')) {
            $employeeBranches = $employeeBranches
                ->orderBy('id', 'desc')
                ->get();
        } else {
            $employeeBranches = $employeeBranches->orderBy('id', 'desc')->paginate(8);
        }
        return sendResponse("Employee Hierarchy reterived successfully.", EmployeeHierarchyResource::collection($employeeBranches)->response()->getData(), []);
    }

    /**
     * @OA\Post(
     *     path="/employee_hierarchies/store",
     *     summary="Create a new employee hierarchy",
     *     description="Creates a new employee hierarchy.",
     *     tags={"HRM"},
     *     security={{"bearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="employee_id", type="integer", description="ID of the employee", example=1),
     *             @OA\Property(property="approver_id", type="integer", description="ID of the approver", example=2),
     *             @OA\Property(property="hierarchy_level_id", type="integer", description="ID of the hierarchy level", example=1),
     *         ),
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Employee Branch created successfully",
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error occurred while creating employee Branch",
     *     ),
     * )
     */
    public function store(StoreEmployeeHierarchyRequest $request)
    {
        try {
            $EmployeeHierarchy = EmployeeHierarchy::create($request->validated());
            return sendResponse("Employee Branch created successfully.", new EmployeeHierarchyResource($EmployeeHierarchy));
        } catch (QueryException $e) {
            return sendResponse("Error occurred while creating employee Branch.", [], [$e->getMessage()], 422);
        }
    }

    /**
     * @OA\Post(
     *     path="/employee_hierarchies/update",
     *     summary="Update an employee hierarchy",
     *     description="Updates an existing employee hierarchy.",
     *     tags={"HRM"},
     *     security={{"bearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", description="ID of the employee hierarchy to update"),
     *             @OA\Property(property="employee_id", type="integer", description="ID of the employee", example=1),
     *             @OA\Property(property="approver_id", type="integer", description="ID of the approver", example=2),
     *             @OA\Property(property="hierarchy_level_id", type="integer", description="ID of the hierarchy level", example=1),
     *         ),
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Employee Branch updated successfully",
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error occurred while updating employee Branch",
     *     ),
     * )
     */
    public function update(UpdateEmployeeHierarchyRequest $request)
    {
        try {
            $EmployeeHierarchy = EmployeeHierarchy::findOrFail($request->id);
            $EmployeeHierarchy->update($request->validated());
            return sendResponse("Employee Branch updated successfully.", new EmployeeHierarchyResource($EmployeeHierarchy));
        } catch (QueryException $e) {
            return sendResponse("Error occurred while updating employee Branch.", [], [$e->getMessage()], 422);
        }
    }

    /**
     * @OA\Post(
     *     path="/employee_hierarchies/delete",
     *     summary="Delete an employee hierarchy",
     *     description="Deletes an existing employee hierarchy.",
     *     tags={"HRM"},
     *     security={{"bearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", description="ID of the employee hierarchy to delete"),
     *         ),
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Employee Branch deleted successfully",
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error occurred while deleting employee branch",
     *     ),
     * )
     */
    public function delete(Request $request)
    {
        try {
            EmployeeHierarchy::where('id', $request->id)->delete();
        } catch (QueryException $e) {
            return sendResponse("Error Occured.", [], [$e->getMessage()], 422);
        }
        return sendResponse("Employee Branch deleted successfully.", []);
    }

    /**
     * @OA\Get(
     *     path="/employee_hierarchies/all",
     *     summary="Get all employee hierarchies",
     *     description="Retrieves all employee hierarchies.",
     *     tags={"HRM"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Employee Branches",
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error occurred while retrieving employee hierarchies",
     *     )
     * )
     */
    public function all()
    {
        $hierarchies = EmployeeHierarchy::all();
        return sendResponse("Employee Branches", EmployeeHierarchyResource::collection($hierarchies));
    }
}