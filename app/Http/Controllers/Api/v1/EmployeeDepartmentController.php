<?php

namespace App\Http\Controllers\Api\v1;
use App\Http\Controllers\Controller;


use App\Http\Requests\StoreEmployeeDepartmentRequest;
use App\Http\Requests\UpdateEmployeeDepartmentRequest;
use App\Http\Resources\EmployeeDepartmentResource;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use App\Models\EmployeeDepartment;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * @OA\Tag(name="HRM", description="Employee Department Management")
 * @OA\Server(url="api/")
 */
class EmployeeDepartmentController extends Controller
{
    /**
     * @OA\Get(
     *     path="/employee_departments",
     *     summary="Get a list of employee departments",
     *     description="Retrieve a list of employee departments with pagination and search functionality.",
     *     tags={"HRM"},
     *     @OA\Parameter(
     *         name="query",
     *         in="query",
     *         description="Search query for employee department name",
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
     *         description="Employee Departments retrieved successfully",
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function index()
    {
        $employeeDepartments = EmployeeDepartment::query()->byOwner();

        if (request()->filled('query')) {
            $query = request()->input('query');

            // Get filtered results
            $results = $employeeDepartments
                ->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($query) . '%'])
                ->orderBy('id', 'desc')
                ->get();

            // Manually paginate
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
                "Employee Department retrieved successfully.",
                EmployeeDepartmentResource::collection($paginated)->response()->getData(),
                []
            );
        } else {
            $paginated = $employeeDepartments->orderBy('id', 'desc')->paginate(8);
            return sendResponse(
                "Employee Department retrieved successfully.",
                EmployeeDepartmentResource::collection($paginated)->response()->getData(),
                []
            );
        }
    }

    /**
     * @OA\Post(
     *     path="/employee_departments/store",
     *     summary="Create a new employee department",
     *     description="Create a new employee department.",
     *     tags={"HRM"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", description="Name of the employee department (required, min:2, max:100, unique)", example="Sales")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Employee Department created successfully",
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *     ),

     *     security={{"bearerAuth": {}}}
     * )
     */
    public function store(StoreEmployeeDepartmentRequest $request)
    {
        try {
            $EmployeeDepartment = EmployeeDepartment::create($request->validated());
            return sendResponse("Employee Department created successfully.", new EmployeeDepartmentResource($EmployeeDepartment));
        } catch (QueryException $e) {
            return sendResponse("Error occurred while creating employee department.", [], [$e->getMessage()], 422);
        }
    }

    /**
     * @OA\Post(
     *     path="/employee_departments/update",
     *     summary="Update an existing employee department",
     *     description="Update an existing employee department.",
     *     tags={"HRM"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", description="ID of the employee department to update (required)"),
     *             @OA\Property(property="name", type="string", description="Name of the employee department (required, min:3, max:100, unique)")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Employee Department updated successfully",
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *     ),

     *     security={{"bearerAuth": {}}}
     * )
     */
    public function update(UpdateEmployeeDepartmentRequest $request)
    {
        try {
            $EmployeeDepartment = EmployeeDepartment::findOrFail($request->id);
            $EmployeeDepartment->update($request->validated());
            return sendResponse("Employee Department updated successfully.", new EmployeeDepartmentResource($EmployeeDepartment));
        } catch (QueryException $e) {
            return sendResponse("Error occurred while updating employee department.", [], [$e->getMessage()], 422);
        }
    }

    /**
     * @OA\Post(
     *     path="/employee_departments/delete",
     *     summary="Delete an employee department",
     *     description="Delete an employee department.",
     *     tags={"HRM"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", description="ID of the employee department to delete (required)")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Employee Department deleted successfully",
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error Occured",
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function delete(Request $request)
    {
        try {
            EmployeeDepartment::where('id', $request->id)->delete();
        } catch (QueryException $e) {
            return sendResponse("Error Occured.", [], [$e->getMessage()], 422);
        }
        return sendResponse("Employee Department deleted successfully.", []);
    }

    /**
     * @OA\Get(
     *     path="/employee_departments/all",
     *     summary="Get all employee departments",
     *     description="Retrieve all employee departments.",
     *     tags={"HRM"},
     *     @OA\Response(
     *         response=200,
     *         description="Employee Departments",
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function all()
    {
        $employeeDepartments = EmployeeDepartment::byOwner()->get();
        return sendResponse("Employee Departments", new EmployeeDepartmentResource($employeeDepartments));
    }
} // End of EmployeeDepartmentController class