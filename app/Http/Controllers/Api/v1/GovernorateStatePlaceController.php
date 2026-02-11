<?php

namespace App\Http\Controllers\Api\v1;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\GovernorateStatePlaceImport;
use Illuminate\Database\QueryException;
/**
 * @OA\Tag(
 *     name="WMS",
 *     description="Warehouse Management System related endpoints"
 * )
 */
class GovernorateStatePlaceController extends Controller
{
    /**
     * Import governorates, states, and places
     * 
     * @OA\Post(
     *   path="/import-governorate-state-place",
     *   tags={"WMS"},
     *   summary="Import governorates, states, and places from Excel/CSV file",
     *   description="Import data for governorates, states, and places from an Excel or CSV file",
     *   operationId="importGovernorateStatePlace",
     *   security={
     *     {"bearerAuth": {}}
     *   },
     *   @OA\RequestBody(
     *     description="File to import",
     *     required=true,
     *     @OA\JsonContent(
     *       required={"file"},
     *       @OA\Property(
     *         property="file",
     *         type="string",
     *         format="binary",
     *         description="Excel or CSV file containing governorates, states, and places data"
     *       )
     *     )
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Import successful",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="Places created successfully."),
     *       @OA\Property(
     *         property="data",
     *         type="array",
     *         items={
     *           @OA\Property(type="string")
     *         }
     *       )
     *     )
     *   ),
     *   @OA\Response(
     *     response=422,
     *     description="Validation error",
     *     @OA\JsonContent(
     *       @OA\Property(property="message", type="string", example="Error Occurred."),
     *       @OA\Property(property="errors", type="array", @OA\Items(type="string"))
     *     )
     *   )
     * )
     */
    public function import(Request $request)
    {
        try {
            $request->validate([
                'file' => 'required|mimes:xlsx,csv'
            ]);

            // Import the file
            Excel::import(new GovernorateStatePlaceImport, $request->file('file'));
            return sendResponse("Places created successfully.", [], true);
        } catch (QueryException $e) {
            return sendResponse("Error Occurred.", [], false, [$e->getMessage()], 422);
        }

    }
}
