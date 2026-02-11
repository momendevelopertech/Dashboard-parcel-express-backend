<?php

namespace App\Http\Controllers\Api\v1;
use App\Http\Controllers\Controller;


use App\Http\Requests\StorePayrollRequest;
use App\Http\Requests\UpdatePayrollRequest;
use App\Http\Resources\PayrollResource;
use App\Models\Employee;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use App\Models\Payroll;
use App\Models\WorkTime;

/**
 * @OA\Tag(name="HRM", description="Employee Payroll Management")
 * @OA\Controller(description="Employee Payroll Controller")
 */
class EmployeePayrollController extends Controller
{
    /**
     * @OA\Get(
     *     path="/employee_payrolls",
     *     summary="Get a list of payrolls",
     *     description="Retrieves a list of payrolls. Allows searching by employee name using the 'query' parameter.",
     *     tags={"HRM"},
     *     @OA\Parameter(
     *         name="query",
     *         in="query",
     *         description="Search query for employee name",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Payroll retrieved successfully.",
     *         @OA\JsonContent()
     *     ),
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function index()
    {
        $payrolls = Payroll::query()
            ->with(['employee.user'])
            ->orderBy('id', 'desc');
        if (request()->filled('query')) {
            $searchQuery = request()->input('query');
            $payrolls = $payrolls->whereHas('employee.user', function ($q) use ($searchQuery) {
                $q->where('name', 'like', "%{$searchQuery}%");
            });
        }
        $paginated = $payrolls->paginate(8)->appends(request()->only('query'));
        return sendResponse("Payroll retrieved successfully.", PayrollResource::collection($paginated)->response()->getData(), []);
    }

    /**
     * @OA\Get(
     *     path="/employee_payrolls/{id}",
     *     summary="Get a payroll by ID",
     *     description="Retrieves a payroll by its ID.",
     *     tags={"HRM"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of the payroll",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Payroll reterived successfully.",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Payroll not found."
     *     ),
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function show($id)
    {
        $payroll = Payroll::with("employee")->find($id);

        if (!$payroll) {
            return sendResponse("Payroll not found.", [], ["error"]);
        }

        return sendResponse("Payroll reterived successfully.", new PayrollResource($payroll));
    }

    /**
     * @OA\Post(
     *     path="/employee_payrolls/store",
     *     summary="Create a new payroll",
     *     description="Creates a new payroll.",
     *     tags={"HRM"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="employee_id", type="integer", description="ID of the employee (required, exists:employees,id)", example=1),
     *             @OA\Property(property="period_start_date", type="string", format="date", description="Start date of the period (required, date)", example="2025-06-01"),
     *             @OA\Property(property="period_end_date", type="string", format="date", description="End date of the period (required, date)", example="2025-06-30"),
     *             @OA\Property(property="regular_hours", type="number", format="float", description="Regular hours worked (nullable, numeric)", example=1),
     *             @OA\Property(property="overtime_hours", type="number", format="float", description="Overtime hours worked (nullable, numeric)", example=1),
     *             @OA\Property(property="regular_pay", type="number", format="float", description="Regular pay (nullable, numeric)", example=1),
     *             @OA\Property(property="overtime_pay", type="number", format="float", description="Overtime pay (nullable, numeric)", example=1),
     *             @OA\Property(property="tax_deduction", type="number", format="float", description="Tax deduction (nullable, numeric)", example=1),
     *             @OA\Property(property="insurance_deduction", type="number", format="float", description="Insurance deduction (nullable, numeric)", example=1),
     *             @OA\Property(property="penalty_deductions", type="number", format="float", description="Penalty deductions (nullable, numeric)", example=1),
     *             @OA\Property(property="gross_pay", type="number", format="float", description="Gross pay (nullable, numeric)", example=1),
     *             @OA\Property(property="net_pay", type="number", format="float", description="Net pay (nullable, numeric)", example=1),
     *             @OA\Property(property="payment_date", type="string", format="date", description="Payment date (nullable, date)", example="2025-07-15")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Payroll created successfully.",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error occurred while creating payroll."
     *     ),
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function store(StorePayrollRequest $request)
    {
        try {
            $data = $request->validated();
            $employee = Employee::findOrFail($data['employee_id']);
            $workTimes = WorkTime::where('employee_id', $data['employee_id'])
                ->whereBetween('check_in', [$data['period_start_date'], $data['period_end_date']])
                ->get();
            $regularHours = 0;
            $overtimeHours = 0;
            $baseHours = $employee->base_hours; //base hours per day
            foreach ($workTimes as $workTime) {
                $totalHoursWorked = $workTime->total_hours;

                if ($totalHoursWorked <= $baseHours) {
                    $regularHours += $totalHoursWorked;
                } else {
                    $regularHours += $baseHours;
                    $overtimeHours += $totalHoursWorked - $baseHours;
                }
            }
            $regularPay = $regularHours * $employee->base_hour_salary;
            $overtimePay = $overtimeHours * $employee->overtime_hour_salary;
            $grossPay = $regularPay + $overtimePay;
            $taxDeduction = 0;
            $insuranceDeduction = 0;
            $penaltyDeductions = 0;
            $totalDeductions = $taxDeduction + $insuranceDeduction + $penaltyDeductions;
            $netPay = $grossPay - $totalDeductions;

            $data['regular_hours'] = $data['regular_hours'] ?? $regularHours;
            $data['overtime_hours'] = $data['overtime_hours'] ?? $overtimeHours;
            $data['regular_pay'] = $data['regular_pay'] ?? $regularPay;
            $data['overtime_pay'] = $data['overtime_pay'] ?? $overtimePay;
            $data['tax_deduction'] = $data['tax_deduction'] ?? $taxDeduction;
            $data['insurance_deduction'] = $data['insurance_deduction'] ?? $insuranceDeduction;
            $data['penalty_deductions'] = $data['penalty_deductions'] ?? $penaltyDeductions;
            $data['gross_pay'] = $data['gross_pay'] ?? $grossPay;
            $data['net_pay'] = $data['net_pay'] ?? $netPay;
            $data['payment_date'] = $data['payment_date'] ?? now();
            $payroll = Payroll::create($data);
            return sendResponse("Payroll created successfully.", new PayrollResource($payroll));
        } catch (QueryException $e) {
            return sendResponse("Error occurred while creating payroll.", [], [$e->getMessage()], 422);
        }
    }

    /**
     * @OA\Post(
     *     path="/employee_payrolls/update",
     *     summary="Update a payroll",
     *     description="Updates a payroll.",
     *     tags={"HRM"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", description="ID of the payroll to update (required)", example=1),
     *             @OA\Property(property="employee_id", type="integer", description="ID of the employee (required, exists:employees,id)", example=1),
     *             @OA\Property(property="period_start_date", type="string", format="date", description="Start date of the period (nullable, date)", example="2025-06-01"),
     *             @OA\Property(property="period_end_date", type="string", format="date", description="End date of the period (nullable, date)", example="2025-06-30"),
     *             @OA\Property(property="regular_hours", type="number", format="float", description="Regular hours worked (nullable, numeric)", example=1),
     *             @OA\Property(property="overtime_hours", type="number", format="float", description="Overtime hours worked (nullable, numeric)", example=1),
     *             @OA\Property(property="regular_pay", type="number", format="float", description="Regular pay (nullable, numeric)", example=1),
     *             @OA\Property(property="overtime_pay", type="number", format="float", description="Overtime pay (nullable, numeric)", example=1),
     *             @OA\Property(property="tax_deduction", type="number", format="float", description="Tax deduction (nullable, numeric)", example=1),
     *             @OA\Property(property="insurance_deduction", type="number", format="float", description="Insurance deduction (nullable, numeric)", example=1),
     *             @OA\Property(property="penalty_deductions", type="number", format="float", description="Penalty deductions (nullable, numeric)", example=1),
     *             @OA\Property(property="gross_pay", type="number", format="float", description="Gross pay (nullable, numeric)", example=1),
     *             @OA\Property(property="net_pay", type="number", format="float", description="Net pay (nullable, numeric)", example=1),
     *             @OA\Property(property="payment_date", type="string", format="date", description="Payment date (nullable, date)", example="2025-07-15")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Payroll updated successfully.",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error occurred while updating payroll."
     *     ),
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function update(UpdatePayrollRequest $request)
    {
        try {
            $payroll = Payroll::findOrFail($request->id);
            $payroll->update($request->validated());
            return sendResponse("Payroll updated successfully.", new PayrollResource($payroll));
        } catch (QueryException $e) {
            return sendResponse("Error occurred while updating payroll.", [], [$e->getMessage()], 422);
        }
    }

    /**
     * @OA\Post(
     *     path="/employee_payrolls/delete",
     *     summary="Delete a payroll",
     *     description="Deletes a payroll.",
     *     tags={"HRM"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", description="ID of the payroll to delete (required)")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Payroll deleted successfully."
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error Occured."
     *     ),
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function delete(Request $request)
    {
        try {
            Payroll::where('id', $request->id)->delete();
        } catch (QueryException $e) {
            return sendResponse("Error Occured.", [], [$e->getMessage()], 422);
        }
        return sendResponse("Payroll deleted successfully.", []);
    }

    /**
     * @OA\Get(
     *     path="/employee_payrolls/all",
     *     summary="Get all payrolls",
     *     description="Retrieves all payrolls.",
     *     tags={"HRM"},
     *     @OA\Response(
     *         response=200,
     *         description="Payrolls",
     *         @OA\JsonContent()
     *     ),
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function all()
    {
        return sendResponse("Payrolls", new PayrollResource(Payroll::all()));
    }
}
