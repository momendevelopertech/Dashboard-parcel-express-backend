<?php

namespace App\Http\Controllers\Api\v1;
use App\Http\Controllers\Controller;


use App\Http\Requests\StoreEmployeeRequest;
use App\Http\Requests\UpdateEmployeeRequest;
use App\Http\Resources\EmployeeResource;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use App\Models\Employee;

/**
 * @OA\Tag(name="HRM", description="Employee Management")
 * @OA\Controller(description="Manage employees")
 */
class EmployeeController extends Controller
{
    /**
     * @OA\Get(
     *     path="/employees",
     *     summary="Get a list of employees",
     *     description="Retrieve a list of employees with pagination. You can search by employee name using the 'query' parameter.",
     *     tags={"HRM"},
     *     @OA\Parameter(
     *         name="query",
     *         in="query",
     *         description="Search query for employee name",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Employees retrieved successfully",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function index()
    {
        $query = request()->input('query');
        $employees = Employee::with([
            'user:id,name,email',
            'directManager:id,name,email',
            'position:id,title',
            'department:id,name',
            'level:id,role_name,level'
        ])->orderBy('id', 'desc');
        if ($query) {
            $employees = $employees->whereHas('user', function ($q) use ($query) {
                $q->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($query) . '%']);
            });
        }
        $employees = $employees->paginate(10);
        return sendResponse("Employees retrieved successfully.", EmployeeResource::collection($employees));
    }

    /**
     * @OA\Get(
     *     path="/employees/show/{id}",
     *     summary="Get an employee by ID",
     *     description="Retrieve a specific employee by their ID.",
     *     tags={"HRM"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of the employee",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Employee retrieved successfully",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Employee not found"
     *     ),
     *      @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function show($id)
    {
        $employee = Employee::with(['user:id,name,email','directManager', 'position:id,title', 'department:id,name', 'level:id,role_name,level'])->find($id);

        if (!$employee) {
            return sendResponse("Employee not found.", [], ["error"], 404);
        }

        return sendResponse("Employee retrieved successfully.", new EmployeeResource($employee));
    }

    /**
     * @OA\Post(
     *     path="/employees/store",
     *     summary="Create a new employee",
     *     description="Create a new employee.",
     *     tags={"HRM"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="user_id", type="integer", description="ID of the user, required, exists:users,id", example=1),
     *             @OA\Property(property="department_id", type="integer", description="ID of the department, required, exists:employee_departments,id", example=1),
     *             @OA\Property(property="position_id", type="integer", description="ID of the position, required, exists:employee_positions,id", example=1),
     *             @OA\Property(property="level_id", type="integer", description="ID of the level, required, exists:hierarchy_levels,id", example=1),
     *             @OA\Property(property="direct_manager_id", type="integer", description="ID of the direct manager, nullable, exists:users,id", example=1),
     *             @OA\Property(property="basic_salary", type="number", format="float", description="Basic salary, required, numeric, min:0", example=1),
     *             @OA\Property(property="date_of_joining", type="string", format="date", description="Date of joining, required, date", example="2025-01-01"),
     *             @OA\Property(property="base_hours", type="number", format="float", description="Base hours, required, numeric, min:0", example=1),
     *             @OA\Property(property="overtime_hour_salary", type="number", format="float", description="Overtime hour salary, required, numeric, min:0", example=1),
     *             @OA\Property(property="country_id", type="integer", description="ID of the country, nullable, exists:countries,id", example=1)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Employee created successfully",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function store(StoreEmployeeRequest $request)
    {
        try {
            $employee = Employee::create($request->validated());
            return sendResponse("Employee created successfully.", new EmployeeResource($employee));
        } catch (QueryException $e) {
            return sendResponse("Error occurred while creating employee.", [], [$e->getMessage()], 422);
        }
    }

    /**
     * @OA\Post(
     *     path="/employees/update",
     *     summary="Update an employee",
     *     description="Update an existing employee.",
     *     tags={"HRM"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", description="ID of the employee to update, required", example=1),
     *             @OA\Property(property="first_name", type="string", description="First name, sometimes, string, max:255", example=1),
     *             @OA\Property(property="last_name", type="string", description="Last name, sometimes, string, max:255", example=1),
     *             @OA\Property(property="email", type="string", format="email", description="Email, sometimes, email, unique:employees,email,", example=1),
     *             @OA\Property(property="phone", type="string", description="Phone, sometimes, string, max:20", example=1),
     *             @OA\Property(property="department_id", type="integer", description="ID of the department, sometimes, exists:employee_departments,id", example=1),
     *             @OA\Property(property="position_id", type="integer", description="ID of the position, sometimes, exists:employee_positions,id", example=1),
     *             @OA\Property(property="level_id", type="integer", description="ID of the level, sometimes, exists:hierarchy_levels,id", example=1),
     *             @OA\Property(property="direct_manager_id", type="integer", description="ID of the direct manager, nullable, exists:users,id", example=1),
     *             @OA\Property(property="salary", type="number", format="float", description="Salary, sometimes, numeric, min:0", example=1),
     *             @OA\Property(property="basic_salary", type="number", format="float", description="Basic salary, required, numeric, min:0", example=1),
     *             @OA\Property(property="date_of_joining", type="string", format="date", description="Date of joining, required, date", example="2025-01-01"),
     *             @OA\Property(property="base_hours", type="number", format="float", description="Base hours, required, numeric, min:0", example=1),
     *             @OA\Property(property="overtime_hour_salary", type="number", format="float", description="Overtime hour salary, required, numeric, min:0", example=1),
     *             @OA\Property(property="country_id", type="integer", description="ID of the country, nullable, exists:countries,id", example=1),
     *             @OA\Property(property="hire_date", type="string", format="date", description="Hire date, sometimes, date", example=1),
     *             @OA\Property(property="status", type="string", description="Status, sometimes, in:active,inactive,terminated,on_leave, example=1")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Employee updated successfully",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function update(UpdateEmployeeRequest $request)
    {
        try {
            $id = $request->input("id");
            $employee = Employee::findOrFail($id);
            $employee->update($request->validated());
            return sendResponse("Employee updated successfully.", new EmployeeResource($employee));
        } catch (QueryException $e) {
            return sendResponse("Error occurred while updating employee.", [], [$e->getMessage()], 422);
        }
    }

    /**
     * @OA\Post(
     *     path="/employees/delete",
     *     summary="Delete an employee",
     *     description="Delete an existing employee.",
     *     tags={"HRM"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", description="ID of the employee to delete, required")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Employee deleted successfully",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error occurred while deleting employee",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function delete(Request $request)
    {
        $id = $request->id;
        try {
            $employee = Employee::findOrFail($id);
            $employee->delete();
            return sendResponse("Employee deleted successfully.", []);
        } catch (QueryException $e) {
            return sendResponse("Error occurred while deleting employee.", [], [$e->getMessage()], 422);
        }
    }

    /**
     * @OA\Get(
     *     path="/employees/all",
     *     summary="Get all employees",
     *     description="Retrieve all employees without pagination.",
     *     tags={"HRM"},
     *     @OA\Response(
     *         response=200,
     *         description="Employees retrieved successfully",
     *         @OA\JsonContent()
     *     ),
     *      @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function all()
    {
        $employees = Employee::with(['user:id,name,email','directManager', 'position:id,title', 'department:id,name', 'level:id,role_name,level'])->orderBy('id', 'desc')->get();perPage: 
        return sendResponse("Employees retrieved successfully.", EmployeeResource::collection($employees));
    }
}