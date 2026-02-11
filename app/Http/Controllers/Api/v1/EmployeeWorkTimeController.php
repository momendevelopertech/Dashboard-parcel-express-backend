<?php

namespace App\Http\Controllers\Api\v1;
use App\Http\Controllers\Controller;


use App\Http\Requests\StoreWorkTimeRequest;
use App\Http\Requests\UpdateWorkTimeRequest;
use App\Http\Resources\WorkTimeResource;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use App\Models\WorkTime;
use Carbon\Carbon;

/**
 * @OA\Tag(name="HRM", description="Manage employee work times")
 * @OA\Controller(description="Employee Work Time Controller")
 */
class EmployeeWorkTimeController extends Controller
{
    /**
     * @OA\Get(
     *     path="/work_times",
     *     summary="Get a list of work times",
     *     description="Retrieve a list of employee work times. Optionally, use the 'query' parameter to retrieve all work times.",
     *     tags={"HRM"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="WorkTime retrieved successfully",
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *     ),
     * )
     */
    public function index()
    {
        $workTimes = WorkTime::query()->with('employee');

        if (request()->has('query')) {
            // $query = request()->input('query');
            $workTimes = $workTimes
                ->orderBy('id', 'desc')
                ->get();
        } else {
            $workTimes = $workTimes->orderBy('id', 'desc')->paginate(8);
        }
        return sendResponse("WorkTime reterived successfully.", WorkTimeResource::collection($workTimes)->response()->getData(), []);
    }

    /**
     * @OA\Get(
     *     path="/work_times/show/{id}",
     *     summary="Get a work time by ID",
     *     description="Retrieve a single work time by its ID.",
     *     tags={"HRM"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of the work time to retrieve",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="WorkTime retrieved successfully",
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Work time not found",
     *     ),
     * )
     */
    public function show($id)
    {
        $workTime = WorkTime::with("employee")->find($id);

        if (!$workTime) {
            return sendResponse("Work time not found.", [], ["error"]);
        }

        return sendResponse("WorkTime reterived successfully.", new WorkTimeResource($workTime));
    }

    /**
     * @OA\Post(
     *     path="/work_times/start",
     *     summary="Start a new work time",
     *     description="Create a new work time for an employee.",
     *     tags={"HRM"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="employee_id", type="integer", description="ID of the employee", example=1),
     *         ),
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="WorkTime created successfully",
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error or error occurred while creating work time",
     *     ),
     * )
     */
    public function store(StoreWorkTimeRequest $request)
    {
        try {
            $data = $request->validated();
            $existNotFinishedWorkTime = WorkTime::where('employee_id', $data['employee_id'])
                ->whereNull('check_out')->count();

            if ($existNotFinishedWorkTime > 0) {
                return sendResponse("You can't start a new work time if you have an unfinished last work time.", [], ["error"]);
            } else {
                $data['check_in'] = now();
                $workTime = WorkTime::create($data);
                return sendResponse("WorkTime created successfully.", new WorkTimeResource($workTime));
            }
        } catch (QueryException $e) {
            return sendResponse("Error occurred while creating work time.", [], [$e->getMessage()], 422);
        }
    }

    /**
     * @OA\Post(
     *     path="/work_times/end",
     *     summary="End a work time",
     *     description="End an existing work time for an employee.",
     *     tags={"HRM"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="employee_id", type="integer", description="ID of the employee", example=1),
     *         ),
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="WorkTime updated successfully",
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error or error occurred while updating work time",
     *     ),
     * )
     */
    public function update(UpdateWorkTimeRequest $request)
    {
        try {
            $data = $request->validated();
            $workTime = WorkTime::where('employee_id', $data['employee_id'])
                ->whereNull('check_out')->first();

            if (!$workTime) {
                return sendResponse("You can't update this work time because it's not found or finished.", [], ["error"]);
            }
            $now = Carbon::now();
            $start = $workTime->check_in;
            $totalHours = $start->diffInSeconds($now) / 3600;
            $workTime->check_out = $now;
            $workTime->total_hours = round($totalHours, 4);
            $workTime->save();
            return sendResponse("WorkTime updated successfully.", new WorkTimeResource($workTime));
        } catch (QueryException $e) {
            return sendResponse("Error occurred while updating work time.", [], [$e->getMessage()], 422);
        }
    }

    /**
     * @OA\Post(
     *     path="/work_times/delete",
     *     summary="Delete a work time",
     *     description="Delete a work time by its ID.",
     *     tags={"HRM"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", description="ID of the work time to delete"),
     *         ),
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="WorkTime deleted successfully",
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error occurred while deleting work time",
     *     ),
     * )
     */
    public function delete(Request $request)
    {
        try {
            WorkTime::where('id', $request->id)->delete();
        } catch (QueryException $e) {
            return sendResponse("Error Occured.", [], [$e->getMessage()], 422);
        }
        return sendResponse("WorkTime deleted successfully.", []);
    }

    /**
     * @OA\Get(
     *     path="/work_times/all",
     *     summary="Get all work times",
     *     description="Retrieve all employee work times.",
     *     tags={"HRM"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="WorkTimes retrieved successfully",
     *     ),
     * )
     */
    public function all()
    {
        return sendResponse("WorkTimes", WorkTimeResource::collection(WorkTime::all()));
    }
}
