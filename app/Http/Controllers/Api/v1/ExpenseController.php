<?php

namespace App\Http\Controllers\Api\v1;
use App\Http\Controllers\Controller;


use App\Http\Requests\StoreExpenseRequest;
use App\Http\Requests\UpdateExpenseRequest;
use App\Http\Resources\ExpenseResource;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use App\Models\Expense;
use Illuminate\Support\Facades\Auth;

/**
 * @OA\Tag(name="WMS", description="Warehouse Management System")
 * @OA\Controller(description="Expense Management Controller")
 */
class ExpenseController extends Controller
{

    /**
     * @OA\Get(
     *     path="/expenses",
     *     summary="Get a list of expenses",
     *     description="Retrieve a list of expenses. Optionally search by amount using the 'query' parameter.",
     *     tags={"WMS"},
     *     @OA\Parameter(
     *         name="query",
     *         in="query",
     *         description="Search expenses by amount",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Expenses retrieved successfully",
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *     ),
     *     security={{ "bearerAuth": {} }}
     * )
     */
    public function index()
    {
        $expenses = Expense::query();
        $perPage = request()->query('per_page', 8);
        if (request()->has('query')) {
            $query = request()->input('query');
            $expenses = $expenses
                ->whereRaw('LOWER(amount) LIKE ?', ['%' . strtolower($query) . '%'])
                ->orderBy('id', 'desc')
                ->get();
        } else {
            $expenses = $expenses->orderBy('id', 'desc')->paginate($perPage);
        }
        return sendResponse("Expenses retrieved successfully.", new ExpenseResource($expenses), []);
    }
    /**
     * @OA\Post(
     *     path="/expenses/store",
     *     summary="Create a new expense",
     *     description="Create a new expense.",
     *     tags={"WMS"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="amount", type="number", description="Amount of the expense", example=100),
     *         ),
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Expense created successfully",
     *     ),
     *      @OA\Response(
     *          response=422,
     *          description="Validation error or error occurred while creating expense",
     *      ),
     *     security={{ "bearerAuth": {} }}
     * )
     */
    public function store(StoreExpenseRequest $request)
    {
        try {
            $data=$request->validated();
            $data["owner_id"]=Auth::user()->owner_id;
            $data["owner_type"]=Auth::user()->owner_type;
            $expense = Expense::create($data);
            activityLog('expense create',"new expense created called {$expense->name} with id {$expense->id}");
            return sendResponse("Expense created successfully.", new ExpenseResource($expense));
        } catch (QueryException $e) {
            return sendResponse("Error occurred while creating expense.", [], false, [$e->getMessage()], 422);
        }
    }
    /**
     * @OA\Post(
     *     path="/expenses/update",
     *     summary="Update an existing expense",
     *     description="Update an existing expense.",
     *     tags={"WMS"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", description="ID of the expense to update"),
     *             @OA\Property(property="amount", type="number", description="Amount of the expense", example=100),
     *         ),
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Expense updated successfully",
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error or error occurred while updating expense",
     *     ),
     *     security={{ "bearerAuth": {} }}
     * )
     */
    public function update(UpdateExpenseRequest $request)
    {
        try {
            $data=$request->validated();
            $data["owner_id"]=Auth::user()->owner_id;
            $data["owner_type"]=Auth::user()->owner_type;
            $expense = Expense::findOrFail($request->id);
            $expense->update($data);
            activityLog('expense update',"expense updated called {$expense->name} with id {$expense->id}");
            return sendResponse("Expense updated successfully.", new ExpenseResource($expense));
        } catch (QueryException $e) {
            return sendResponse("Error occurred while updating expense.", [], [$e->getMessage()], 422);
        }
    }
    /**
     * @OA\Post(
     *     path="/expenses/delete",
     *     summary="Delete an expense",
     *     description="Delete an expense.",
     *     tags={"WMS"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", description="ID of the expense to delete"),
     *         ),
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Expense deleted successfully",
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error occurred while deleting expense",
     *     ),
     *     security={{ "bearerAuth": {} }}
     * )
     */
    public function delete(Request $request)
    {
        try {
           $expense =Expense::findOrFail($request->id);
           $expense->delete();
           activityLog('expense delete',"expense deleted called {$expense->name} with id {$expense->id}");
        } catch (QueryException $e) {
            return sendResponse("Error occurred.", [], [$e->getMessage()], 422);
        }
        return sendResponse("Expense deleted successfully.", []);
    }
    /**
     * @OA\Get(
     *     path="/expenses/all",
     *     summary="Get all expenses",
     *     description="Retrieve all expenses.",
     *     tags={"WMS"},
     *     @OA\Response(
     *         response=200,
     *         description="Expenses retrieved successfully",
     *     ),
     *     security={{ "bearerAuth": {} }}
     * )
     */
    public function all()
    {
        return sendResponse("Expenses", new ExpenseResource(Expense::all()));
    }
}
