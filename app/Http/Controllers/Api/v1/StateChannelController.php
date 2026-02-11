<?php

namespace App\Http\Controllers\Api\v1;
use App\Http\Controllers\Controller;


use App\Exports\StateChannelsExport;
use App\Http\Requests\StoreStateChannelRequest;
use App\Http\Resources\StateChannelResource;
use App\Models\StateChannel;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\StateChannelsTemplateExport;
use App\Imports\StateChannelsImport;
use Maatwebsite\Excel\Excel as ExcelWriter;
/**
 * @OA\Tag(name="Other", description="State Channel Management")
 * @OA\Server(url="/api")
 */
class StateChannelController extends Controller
{
    /**
     * @OA\Get(
     *     path="/state_channels/{shipper_id}",
     *     summary="Get all state channels for a given shipper",
     *     description="Retrieves a list of state channels associated with a specific shipper ID.",
     *     tags={"Other"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="shipper_id",
     *         in="path",
     *         description="ID of the shipper",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Channels retrieved successfully",
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Shipper not found"
     *     )
     * )
     */
    public function index($shipper_id)
    {
        $channels = StateChannel::where('shipper_id', $shipper_id);
        $channels = $channels->orderBy('id', 'desc')->get();
        return sendResponse("Channels reterived successfully.", new StateChannelResource($channels), []);
    }

    /**
     * @OA\Post(
     *     path="/state_channels/store",
     *     summary="Create a new state channel",
     *     description="Creates a new state channel.",
     *     tags={"Other"},
     *     security={{"bearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="shipper_id", type="integer", description="ID of the shipper", example=1),
     *             @OA\Property(property="internal_state_id", type="integer", description="ID of the internal state", example=1),
     *             @OA\Property(property="external_state_id", type="integer", description="ID of the external state (nullable)", example=null),
     *             @OA\Property(property="external_state_name", type="string", description="Name of the external state (nullable)", example="Pending"),
     *         ),
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Channel created successfully",
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad Request",
     *     ),
     *      @OA\Response(
     *         response=422,
     *         description="Validation Error",
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     )
     * )
     */
    public function store(StoreStateChannelRequest $request)
    {
        $request->validated();

        try {
            $channel = StateChannel::create($request->all());
            return sendResponse("Channel created successfully.", new StateChannelResource($channel));
        } catch (QueryException $e) {
            return sendResponse("Error occurred while creating channel.", [], false, [$e->getMessage()], 422);
        }
    }

    /**
     * @OA\Post(
     *     path="/state_channels/delete",
     *     summary="Delete a state channel",
     *     description="Deletes a state channel by ID.",
     *     tags={"Other"},
     *     security={{"bearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", description="ID of the state channel to delete", example=1),
     *         ),
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Channel deleted successfully",
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad Request",
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation Error",
     *     ),
     *      @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Channel not found"
     *     )
     * )
     */
    public function delete(Request $request)
    {
        try {
            StateChannel::where('id', $request->id)->delete();
        } catch (QueryException $e) {
            return sendResponse("Error Occured.", [], [$e->getMessage()], 422);
        }
        return sendResponse("Channel deleted successfully.", []);
    }
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,xlsx,xls|max:10240',
            'shipper_id' => 'required|integer|exists:shippers,id'
        ]);

        try {
            $import = new StateChannelsImport($request->shipper_id);
            Excel::import($import, $request->file('file'));

            $importedCount = $import->getImportedCount();
            $errors = $import->getErrors();

            if (!empty($errors)) {
                return sendResponse(
                    "Import completed with some errors",
                    [
                        'imported_count' => $importedCount,
                        'errors' => $errors
                    ],
                    true,
                    [],
                    207
                );
            }

            return sendResponse(
                "Successfully imported {$importedCount} state channels",
                ['imported_count' => $importedCount]
            );

        } catch (\Exception $e) {
            return sendResponse(
                "Error occurred during import",
                [],
                false,
                [$e->getMessage()],
                422
            );
        }
    }

    /**
     * @OA\Get(
     *     path="/state_channels/template",
     *     summary="Download import template",
     *     description="Download Excel template for state channels import",
     *     tags={"Other"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Template downloaded successfully"
     *     )
     * )
     */

    public function export(Request $request)
    {
        $validated = $request->validate([
            'shipper_id' => ['nullable', 'integer', 'exists:shippers,id'],
            'search' => ['nullable', 'string', 'max:255'],
            'sort_by' => ['nullable', 'string'],
            'sort_dir' => ['nullable', 'string'],
        ]);

        $shipperId = $validated['shipper_id'] ?? null;
        $search = $validated['search'] ?? null;
        $sortBy = $validated['sort_by'] ?? 'id';
        $sortDir = $validated['sort_dir'] ?? 'desc';

        $filename = 'state_channels'
            . ($shipperId ? "_shipper_{$shipperId}" : '_all')
            . '_' . now()->format('Ymd_His') . '.xlsx';

        return Excel::download(
            new StateChannelsExport($shipperId, $search, $sortBy, $sortDir),
            $filename
        );
    }
}
