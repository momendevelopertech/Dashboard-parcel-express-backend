<?php

namespace App\Http\Controllers\Api\v1;
use App\Http\Controllers\Controller;


use App\Http\Requests\StoreEmployeePositionRequest;
use App\Http\Requests\UpdateEmployeePositionRequest;
use App\Http\Resources\EmployeePositionResource;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use App\Models\EmployeePosition;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * @OA\Tag(name="HRM", description="Human Resource Management APIs")
 * @OA\Server(url="api/")
 */
class EmployeePositionController extends Controller
{
    /**
     * @OA\Get(
     *     path="/employee_positions",
     *     summary="Get a list of employee positions",
     *     description="Retrieves a list of employee positions. Supports pagination and searching.",
     *     tags={"HRM"},
     *     @OA\Parameter(
     *         name="query",
     *         in="query",
     *         description="Search query for employee position title",
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
     *         description="Employee Positions retrieved successfully",
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation Error"
     *     ),
     *     security={"bearerAuth": {}}
     * )
     */
    public function index()
    {
        $employeePositions = EmployeePosition::query()->byOwner();

        if (request()->filled('query')) {
            $query = request()->input('query');
            $results = $employeePositions
                ->whereRaw('LOWER(title) LIKE ?', ['%' . strtolower($query) . '%'])
                ->orderBy('id', 'desc')
                ->with('department')
                ->get();
            $page = request()->input('page', 1);
            $perPage = 100;
            $total = $results->count();
            $paginated = new LengthAwarePaginator(
                $results->forPage($page, $perPage),
                $total,
                $perPage,
                $page,
                ['path' => request()->url(), 'query' => request()->query()]
            );
            return sendResponse(
                "Employee Position retrieved successfully.",
                EmployeePositionResource::collection($paginated)->response()->getData(),
                []
            );
        } else {
            $employeePositions = $employeePositions
                ->with('department')
                ->orderBy('id', 'desc')
                ->paginate(100);
            return sendResponse(
                "Employee Position retrieved successfully.",
                EmployeePositionResource::collection($employeePositions)->response()->getData(),
                []
            );
        }
    }

    /**
     * @OA\Post(
     *     path="/employee_positions/store",
     *     summary="Create a new employee position",
     *     description="Creates a new employee position.",
     *     tags={"HRM"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="title", type="string", description="Title of the employee position (required, min:2, max:100, unique)", example="Software Engineer"),
     *             @OA\Property(property="department_id", type="integer", description="ID of the department (required, exists:employee_departments,id)", example=1),
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Employee Position created successfully",
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation Error or Error occurred while creating employee Position",
     *     ),
     *     security={"bearerAuth": {}}
     * )
     */
    public function store(StoreEmployeePositionRequest $request)
    {
        try {
            $EmployeePosition = EmployeePosition::create($request->validated());
            return sendResponse("Employee Position created successfully.", new EmployeePositionResource($EmployeePosition));
        } catch (QueryException $e) {
            return sendResponse("Error occurred while creating employee Position.", [], [$e->getMessage()], 422);
        }
    }

    /**
     * @OA\Post(
     *     path="/employee_positions/update",
     *     summary="Update an existing employee position",
     *     description="Updates an existing employee position.",
     *     tags={"HRM"},
     *      @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", description="ID of the employee position to update (required)"),
     *             @OA\Property(property="title", type="string", description="Title of the employee position (required, min:2, max:100, unique)", example="Senior Software Engineer"),
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Employee Position updated successfully",
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation Error or Error occurred while updating employee Position",
     *     ),
     *     security={"bearerAuth": {}}
     * )
     */
    public function update(UpdateEmployeePositionRequest $request)
    {
        try {
            $EmployeePosition = EmployeePosition::findOrFail($request->id);
            $EmployeePosition->update($request->validated());
            return sendResponse("Employee Position updated successfully.", new EmployeePositionResource($EmployeePosition));
        } catch (QueryException $e) {
            return sendResponse("Error occurred while updating employee Position.", [], [$e->getMessage()], 422);
        }
    }

    /**
     * @OA\Post(
     *     path="/employee_positions/delete",
     *     summary="Delete an employee position",
     *     description="Deletes an employee position.",
     *     tags={"HRM"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", description="ID of the employee position to delete (required)"),
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Employee Position deleted successfully",
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error Occured",
     *     ),
     *     security={"bearerAuth": {}}
     * )
     */
    public function delete(Request $request)
    {
        try {
            EmployeePosition::where('id', $request->id)->delete();
        } catch (QueryException $e) {
            return sendResponse("Error Occured.", [], [$e->getMessage()], 422);
        }
        return sendResponse("Employee Position deleted successfully.", []);
    }

    /**
     * @OA\Get(
     *     path="/employee_positions/all",
     *     summary="Get all employee positions",
     *     description="Retrieves all employee positions.",
     *     tags={"HRM"},
     *     @OA\Response(
     *         response=200,
     *         description="Employee Positions",
     *     ),
     *     security={"bearerAuth": {}}
     * )
     */
    public function all()
    {
        return sendResponse("Employee Positions", new EmployeePositionResource(EmployeePosition::all()));
    }

    /**
     * @OA\Get(
     *     path="/employee_positions/positions/{department_id}",
     *     summary="Get employee positions by department ID",
     *     description="Retrieves employee positions associated with a specific department ID.",
     *     tags={"HRM"},
     *     @OA\Parameter(
     *         name="department_id",
     *         in="path",
     *         description="ID of the department",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Employee positions retrieved successfully",
     *     ),
     *     security={"bearerAuth": {}}
     * )
     */
    public function getByDepartment($department_id)
    {
        $positions = EmployeePosition::where('department_id', $department_id)->get();

        return response()->json([
            'success' => true,
            'positions' => $positions
        ]);
    }
}