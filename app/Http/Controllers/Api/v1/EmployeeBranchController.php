<?php

namespace App\Http\Controllers\Api\v1;
use App\Http\Controllers\Controller;


use App\Http\Requests\StoreEmployeeBranchRequest;
use App\Http\Requests\UpdateEmployeeBranchRequest;
use App\Http\Resources\EmployeeBranchResource;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use App\Models\EmployeeBranch;

/**
 * @OA\Tag(name="HRM", description="Human Resource Management")
 * @OA\Controller(description="Manages employee branches.")
 */
class EmployeeBranchController extends Controller
{
    /**
     * @OA\Get(
     *     path="/employee_branches",
     *     summary="Get a list of employee branches",
     *     description="Returns a paginated list of employee branches or all branches if a query parameter is provided.",
     *     tags={"HRM"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Employee Branch retrieved successfully.",
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error occurred while retrieving employee branches.",
     *     )
     * )
     */
    public function index()
    {
        $employeeBranches = EmployeeBranch::query()->with(['employee']);

        if (request()->has('query')) {
            // $query = request()->input('query');
            $employeeBranches = $employeeBranches
                ->orderBy('id', 'desc')
                ->get();
        } else {
            $employeeBranches = $employeeBranches->orderBy('id', 'desc')->paginate(8);
        }
        return sendResponse("Employee Branch reterived successfully.", EmployeeBranchResource::collection($employeeBranches)->response()->getData(), []);
    }

    /**
     * @OA\Post(
     *     path="/employee_branches/store",
     *     summary="Create a new employee branch",
     *     description="Creates a new employee branch.",
     *     tags={"HRM"},
     *     security={{"bearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="employee_id", type="integer", description="ID of the employee. |exists:employees,id", example=1),
     *             @OA\Property(property="morphable_id", type="integer", description="ID of the morphable entity. |integer|exists:{morphable_table},id", example=1),
     *             @OA\Property(property="morphable_type", type="string", description="Type of the morphable entity (Hub, Station, Branch). |string|in:App\\\\Models\\\\Hub,App\\\\Models\\\\Station,App\\\\Models\\\\Branch", example="App\\Models\\Branch"),
     *         ),
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Employee Branch created successfully.",
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error occurred while creating employee branch.",
     *     )
     * )
     */
    public function store(StoreEmployeeBranchRequest $request)
    {
        try {
            $EmployeeBranch = EmployeeBranch::create($request->validated());
            return sendResponse("Employee Branch created successfully.", new EmployeeBranchResource($EmployeeBranch));
        } catch (QueryException $e) {
            return sendResponse("Error occurred while creating employee Branch.", [], [$e->getMessage()], 422);
        }
    }

    /**
     * @OA\Post(
     *     path="/employee_branches/update",
     *     summary="Update an employee branch",
     *     description="Updates an existing employee branch.",
     *     tags={"HRM"},
     *     security={{"bearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", description="ID of the employee branch to update."),
     *             @OA\Property(property="employee_id", type="integer", description="ID of the employee. |exists:employees,id", example=1),
     *             @OA\Property(property="morphable_id", type="integer", description="ID of the morphable entity. |integer|exists:{morphable_table},id", example=1),
     *             @OA\Property(property="morphable_type", type="string", description="Type of the morphable entity (Hub, Station, Branch). |string|in:App\\\\Models\\\\Hub,App\\\\Models\\\\Station,App\\\\Models\\\\Branch", example="App\\Models\\Branch"),
     *         ),
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Employee Branch updated successfully.",
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error occurred while updating employee branch.",
     *     )
     * )
     */
    public function update(UpdateEmployeeBranchRequest $request)
    {
        try {
            $EmployeeBranch = EmployeeBranch::findOrFail($request->id);
            $EmployeeBranch->update($request->validated());
            return sendResponse("Employee Branch updated successfully.", new EmployeeBranchResource($EmployeeBranch));
        } catch (QueryException $e) {
            return sendResponse("Error occurred while updating employee Branch.", [], [$e->getMessage()], 422);
        }
    }

    /**
     * @OA\Post(
     *     path="/employee_branches/delete",
     *     summary="Delete an employee branch",
     *     description="Deletes an employee branch.",
     *     tags={"HRM"},
     *     security={{"bearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", description="ID of the employee branch to delete."),
     *         ),
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Employee Branch deleted successfully.",
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error occurred while deleting employee branch.",
     *     )
     * )
     */
    public function delete(Request $request)
    {
        try {
            EmployeeBranch::where('id', $request->id)->delete();
        } catch (QueryException $e) {
            return sendResponse("Error Occured.", [], [$e->getMessage()], 422);
        }
        return sendResponse("Employee Branch deleted successfully.", []);
    }

    /**
     * @OA\Get(
     *     path="/employee_branches/all",
     *     summary="Get all employee branches",
     *     description="Returns all employee branches.",
     *     tags={"HRM"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Employee Branches retrieved successfully.",
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error occurred while retrieving employee branches.",
     *     )
     * )
     */
    public function all()
    {
        $branches = EmployeeBranch::with(['employee'])->get();
        return sendResponse("Employee Branches", EmployeeBranchResource::collection($branches));
    }
}